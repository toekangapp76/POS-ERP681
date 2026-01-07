<?php

namespace Modules\Gym\Services;

use Carbon\Carbon;
use App\Transaction;
use Illuminate\Support\Facades\DB;
use Modules\Gym\Entities\GymPackage;
use Modules\Gym\Entities\GymDeferredRevenue;
use Modules\Accounting\Entities\AccountingAccountsTransaction;

class DeferredRevenueService
{
    /**
     * Generate deferred revenue schedule saat membership dibeli
     * 
     * @param Transaction $transaction - gym_subscription transaction
     * @param int $userId
     * @return array - schedule yang dibuat
     */
    public function generateSchedule(Transaction $transaction, int $userId): array
    {
        $package = GymPackage::find($transaction->gym_package_id);
        
        if (!$package || !$package->enable_deferred_revenue) {
            return [];
        }

        if (empty($package->deposit_account_id) || empty($package->revenue_account_id)) {
            return [];
        }

        $startDate = Carbon::parse($transaction->gym_package_start_date);
        $endDate = $transaction->gym_package_end_date 
            ? Carbon::parse($transaction->gym_package_end_date) 
            : null;

        // Jika lifetime, tidak perlu deferred revenue
        if (!$endDate) {
            return [];
        }

        // Hitung nilai tanpa PPN
        $taxRate = $package->tax_rate ?? 11; // Default 11%
        $divisor = 1 + ($taxRate / 100); // 1.11 untuk PPN 11%
        
        $totalWithTax = $transaction->final_total;
        $totalExclTax = $totalWithTax / $divisor;
        
        // Hitung jumlah bulan membership
        $totalMonths = $this->calculateTotalMonths($startDate, $endDate);
        
        // Nilai per bulan penuh
        $monthlyAmount = $totalExclTax / $totalMonths;

        // Generate schedule untuk setiap periode
        $schedules = [];
        $currentDate = $startDate->copy();
        $remainingAmount = $totalExclTax;
        $periodNumber = 1;

        while ($currentDate <= $endDate && $remainingAmount > 0.01) {
            $periodStart = $currentDate->copy();
            
            // Akhir periode adalah akhir bulan atau end_date, mana yang lebih dulu
            $monthEnd = $currentDate->copy()->endOfMonth();
            $periodEnd = $monthEnd->lt($endDate) ? $monthEnd : $endDate->copy();
            
            // Hitung hari dalam periode
            $daysInMonth = $currentDate->daysInMonth;
            
            // Hitung hari aktif
            if ($periodNumber === 1) {
                // Bulan pertama: dari start_date sampai akhir bulan
                $activeDays = $periodEnd->diffInDays($periodStart) + 1;
            } else {
                // Bulan berikutnya: dari awal bulan
                $periodStart = $currentDate->copy()->startOfMonth();
                $activeDays = $periodEnd->diffInDays($periodStart) + 1;
            }

            // Hitung recognition amount
            $recognitionAmount = $monthlyAmount * ($activeDays / $daysInMonth);
            
            // Pastikan tidak melebihi sisa
            if ($recognitionAmount > $remainingAmount) {
                $recognitionAmount = $remainingAmount;
            }

            // Recognition date adalah akhir periode
            $recognitionDate = $periodEnd->copy();

            // Buat schedule record
            $schedule = GymDeferredRevenue::create([
                'business_id' => $transaction->business_id,
                'transaction_id' => $transaction->id,
                'contact_id' => $transaction->contact_id,
                'gym_package_id' => $transaction->gym_package_id,
                'recognition_date' => $recognitionDate,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'period_days' => $daysInMonth,
                'active_days' => $activeDays,
                'total_amount' => $totalExclTax,
                'monthly_amount' => $monthlyAmount,
                'recognition_amount' => $recognitionAmount,
                'status' => 'pending',
                'deposit_account_id' => $package->deposit_account_id,
                'revenue_account_id' => $package->revenue_account_id,
                'created_by' => $userId,
            ]);

            $schedules[] = $schedule;
            $remainingAmount -= $recognitionAmount;
            
            // Pindah ke bulan berikutnya
            $currentDate = $monthEnd->copy()->addDay()->startOfMonth();
            $periodNumber++;
        }

        return $schedules;
    }

    /**
     * Hitung total bulan dari start_date sampai end_date
     */
    private function calculateTotalMonths(Carbon $startDate, Carbon $endDate): float
    {
        // Hitung selisih bulan
        $months = $startDate->diffInMonths($endDate);
        
        // Tambahkan fraction dari hari yang tersisa
        $remainingDays = $startDate->copy()->addMonths($months)->diffInDays($endDate);
        $daysInLastMonth = $endDate->daysInMonth;
        
        return $months + ($remainingDays / $daysInLastMonth);
    }

    /**
     * Create initial GL entry saat payment (Bank Dr, Deposit Cr, PPN Cr)
     */
    public function createInitialGLEntry(Transaction $transaction, int $userId): bool
    {
        $package = GymPackage::find($transaction->gym_package_id);
        
        if (!$package || !$package->hasAccountingMapping()) {
            return false;
        }

        // Delete existing mapping
        AccountingAccountsTransaction::where('transaction_id', $transaction->id)
            ->whereIn('map_type', ['payment_account', 'deposit_to', 'ppn_account'])
            ->delete();

        $taxRate = $package->tax_rate ?? 11;
        $divisor = 1 + ($taxRate / 100);
        
        $totalWithTax = $transaction->final_total;
        $totalExclTax = $totalWithTax / $divisor;
        $taxAmount = $totalWithTax - $totalExclTax;

        $operationDate = $transaction->transaction_date ?? now();

        // Dr. Bank/Cash (full amount incl. tax)
        AccountingAccountsTransaction::updateOrCreateMapTransaction([
            'accounting_account_id' => $package->bank_account_id,
            'transaction_id' => $transaction->id,
            'transaction_payment_id' => null,
            'amount' => $totalWithTax,
            'type' => 'debit',
            'sub_type' => 'gym_subscription',
            'map_type' => 'payment_account',
            'created_by' => $userId,
            'operation_date' => $operationDate,
        ]);

        // Cr. Member Deposit (excl. tax) - Liability
        if (!empty($package->deposit_account_id) && $package->enable_deferred_revenue) {
            // Jika deferred revenue aktif, credit ke Deposit
            AccountingAccountsTransaction::updateOrCreateMapTransaction([
                'accounting_account_id' => $package->deposit_account_id,
                'transaction_id' => $transaction->id,
                'transaction_payment_id' => null,
                'amount' => $totalExclTax,
                'type' => 'credit',
                'sub_type' => 'gym_subscription',
                'map_type' => 'deposit_to',
                'created_by' => $userId,
                'operation_date' => $operationDate,
            ]);
        } else {
            // Jika tidak ada deferred revenue, langsung ke Revenue
            AccountingAccountsTransaction::updateOrCreateMapTransaction([
                'accounting_account_id' => $package->revenue_account_id,
                'transaction_id' => $transaction->id,
                'transaction_payment_id' => null,
                'amount' => $totalExclTax,
                'type' => 'credit',
                'sub_type' => 'gym_subscription',
                'map_type' => 'deposit_to',
                'created_by' => $userId,
                'operation_date' => $operationDate,
            ]);
        }

        // Cr. PPN Payable (tax amount)
        if (!empty($package->tax_account_id) && $taxAmount > 0) {
            AccountingAccountsTransaction::updateOrCreateMapTransaction([
                'accounting_account_id' => $package->tax_account_id,
                'transaction_id' => $transaction->id,
                'transaction_payment_id' => null,
                'amount' => $taxAmount,
                'type' => 'credit',
                'sub_type' => 'gym_subscription',
                'map_type' => 'ppn_account',
                'created_by' => $userId,
                'operation_date' => $operationDate,
            ]);
        }

        return true;
    }

    /**
     * Process revenue recognition untuk schedule yang sudah due
     * 
     * @param int $businessId
     * @param int $userId
     * @param Carbon|null $asOfDate - default: today
     * @return array - processed schedules
     */
    public function processRecognition(int $businessId, int $userId, ?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now();
        
        $dueSchedules = GymDeferredRevenue::forBusiness($businessId)
            ->pending()
            ->where('recognition_date', '<=', $asOfDate->toDateString())
            ->with(['transaction', 'depositAccount', 'revenueAccount'])
            ->get();

        $processed = [];

        foreach ($dueSchedules as $schedule) {
            try {
                DB::beginTransaction();
                
                // Create GL entry: Dr. Member Deposit, Cr. Revenue
                $this->createRecognitionGLEntry($schedule, $userId);
                
                // Mark as recognized
                $schedule->markAsRecognized($userId);
                
                DB::commit();
                
                $processed[] = $schedule;
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Deferred Revenue Recognition Error: ' . $e->getMessage(), [
                    'schedule_id' => $schedule->id,
                    'transaction_id' => $schedule->transaction_id,
                ]);
            }
        }

        return $processed;
    }

    /**
     * Create GL entry untuk recognition (Dr. Deposit, Cr. Revenue)
     * operation_date menggunakan period_start untuk mengikuti tanggal booking membership
     */
    private function createRecognitionGLEntry(GymDeferredRevenue $schedule, int $userId): void
    {
        // Dr. Member Deposit (reduce liability)
        // operation_date menggunakan period_start (awal periode booking membership)
        AccountingAccountsTransaction::create([
            'accounting_account_id' => $schedule->deposit_account_id,
            'transaction_id' => $schedule->transaction_id,
            'transaction_payment_id' => null,
            'amount' => $schedule->recognition_amount,
            'type' => 'debit',
            'sub_type' => 'deferred_revenue_recognition',
            'map_type' => 'deferred_deposit',
            'created_by' => $userId,
            'operation_date' => $schedule->period_start,
            'note' => 'Revenue recognition: ' . $schedule->period_start->format('d/m/Y') . ' - ' . $schedule->period_end->format('d/m/Y'),
        ]);

        // Cr. Membership Revenue (recognize income)
        // operation_date menggunakan period_start (awal periode booking membership)
        AccountingAccountsTransaction::create([
            'accounting_account_id' => $schedule->revenue_account_id,
            'transaction_id' => $schedule->transaction_id,
            'transaction_payment_id' => null,
            'amount' => $schedule->recognition_amount,
            'type' => 'credit',
            'sub_type' => 'deferred_revenue_recognition',
            'map_type' => 'deferred_revenue',
            'created_by' => $userId,
            'operation_date' => $schedule->period_start,
            'note' => 'Revenue recognition: ' . $schedule->period_start->format('d/m/Y') . ' - ' . $schedule->period_end->format('d/m/Y'),
        ]);
    }

    /**
     * Get summary of deferred revenue by business
     */
    public function getSummary(int $businessId, ?Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now();

        $pending = GymDeferredRevenue::forBusiness($businessId)
            ->pending()
            ->sum('recognition_amount');

        $recognized = GymDeferredRevenue::forBusiness($businessId)
            ->recognized()
            ->sum('recognition_amount');

        $dueNow = GymDeferredRevenue::forBusiness($businessId)
            ->pending()
            ->where('recognition_date', '<=', $asOfDate->toDateString())
            ->sum('recognition_amount');

        return [
            'total_pending' => $pending,
            'total_recognized' => $recognized,
            'due_for_recognition' => $dueNow,
        ];
    }

    /**
     * Get schedule for a specific transaction
     */
    public function getScheduleByTransaction(int $transactionId): \Illuminate\Database\Eloquent\Collection
    {
        return GymDeferredRevenue::where('transaction_id', $transactionId)
            ->orderBy('recognition_date')
            ->get();
    }

    /**
     * Cancel all pending schedules for a transaction (used when subscription is deleted/cancelled)
     */
    public function cancelSchedules(int $transactionId): int
    {
        return GymDeferredRevenue::where('transaction_id', $transactionId)
            ->pending()
            ->update(['status' => 'cancelled']);
    }
}
