<?php

namespace Modules\Gym\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Transaction;
use App\Contact;
use App\Business;
use App\User;
use Modules\Accounting\Entities\AccountingAccount;

class GymDeferredRevenue extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'recognition_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'recognized_at' => 'datetime',
        'total_amount' => 'decimal:4',
        'monthly_amount' => 'decimal:4',
        'recognition_amount' => 'decimal:4',
    ];

    /**
     * Get the business for this deferred revenue
     */
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    /**
     * Get the subscription transaction
     */
    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * Get the member/contact
     */
    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Get the gym package
     */
    public function gymPackage()
    {
        return $this->belongsTo(GymPackage::class, 'gym_package_id');
    }

    /**
     * Get the deposit account (Liability - Member Deposit)
     */
    public function depositAccount()
    {
        return $this->belongsTo(AccountingAccount::class, 'deposit_account_id');
    }

    /**
     * Get the revenue account (Income - Membership Revenue)
     */
    public function revenueAccount()
    {
        return $this->belongsTo(AccountingAccount::class, 'revenue_account_id');
    }

    /**
     * Get the user who recognized this revenue
     */
    public function recognizedBy()
    {
        return $this->belongsTo(User::class, 'recognized_by');
    }

    /**
     * Get the user who created this record
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for pending recognitions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for recognized
     */
    public function scopeRecognized($query)
    {
        return $query->where('status', 'recognized');
    }

    /**
     * Scope for due recognitions (recognition_date <= today)
     */
    public function scopeDue($query)
    {
        return $query->where('recognition_date', '<=', now()->toDateString());
    }

    /**
     * Scope by business
     */
    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Scope by month
     */
    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('recognition_date', $year)
                     ->whereMonth('recognition_date', $month);
    }

    /**
     * Check if this recognition is due
     */
    public function isDue(): bool
    {
        return $this->recognition_date <= now()->toDateString() && $this->status === 'pending';
    }

    /**
     * Mark as recognized
     */
    public function markAsRecognized($userId, $journalEntryId = null)
    {
        $this->update([
            'status' => 'recognized',
            'recognized_at' => now(),
            'recognized_by' => $userId,
            'journal_entry_id' => $journalEntryId,
        ]);
    }

    /**
     * Cancel this recognition
     */
    public function cancel()
    {
        $this->update(['status' => 'cancelled']);
    }
}
