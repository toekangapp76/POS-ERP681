<?php

namespace Modules\Gym\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Carbon\Carbon;
use App\Utils\ModuleUtil;
use Modules\Gym\Entities\GymDeferredRevenue;
use Modules\Gym\Services\DeferredRevenueService;
use Yajra\DataTables\Facades\DataTables;
use App\Transaction;
use Modules\Gym\Entities\GymPackage;

class DeferredRevenueController extends Controller
{
    protected $moduleUtil;
    protected $deferredService;

    public function __construct(ModuleUtil $moduleUtil, DeferredRevenueService $deferredService)
    {
        $this->moduleUtil = $moduleUtil;
        $this->deferredService = $deferredService;
    }

    /**
     * Display a listing of the deferred revenue schedules.
     */
    public function index(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'gym_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $schedules = GymDeferredRevenue::forBusiness($business_id)
                ->with(['transaction.contact', 'gymPackage', 'depositAccount', 'revenueAccount'])
                ->select('gym_deferred_revenues.*');

            // Filter by status
            if ($request->status && $request->status !== 'all') {
                $schedules = $schedules->where('status', $request->status);
            }

            // Filter by date range
            if ($request->start_date && $request->end_date) {
                $start = Carbon::parse($request->start_date);
                $end = Carbon::parse($request->end_date);
                $schedules = $schedules->whereBetween('recognition_date', [$start, $end]);
            }

            $recognizedAmounts = GymDeferredRevenue::forBusiness($business_id)
                ->where('status', 'recognized')
                ->selectRaw('transaction_id, SUM(recognition_amount) as total_recognized')
                ->groupBy('transaction_id')
                ->pluck('total_recognized', 'transaction_id');

            return DataTables::of($schedules)
                ->addColumn('member_name', function ($row) {
                    return $row->transaction->contact->name ?? '-';
                })
                ->addColumn('package_name', function ($row) {
                    return $row->gymPackage->name ?? '-';
                })
                ->addColumn('total_membership', function ($row) {
                    // Nilai Total Membership (total_amount dari transaksi)
                    $totalMembership = $row->total_amount ?? ($row->transaction->final_total ?? 0);
                    return number_format($totalMembership, 2, ',', '.');
                })
                ->addColumn('remaining_membership', function ($row) use ($recognizedAmounts) {
                    // Nilai Sisa Membership = Total Membership - Akumulasi Pengakuan Revenue
                    $totalMembership = $row->total_amount ?? ($row->transaction->final_total ?? 0);
                    $accumulatedRecognized = $recognizedAmounts[$row->transaction_id] ?? 0;
                    $remaining = $totalMembership - $accumulatedRecognized;
                    return number_format($remaining, 2, ',', '.');
                })
                ->editColumn('recognition_date', function ($row) {
                    return $row->recognition_date->format('d/m/Y');
                })
                ->editColumn('period_start', function ($row) {
                    return $row->period_start->format('d/m/Y');
                })
                ->editColumn('period_end', function ($row) {
                    return $row->period_end->format('d/m/Y');
                })
                ->editColumn('recognition_amount', function ($row) {
                    return number_format($row->recognition_amount, 2, ',', '.');
                })
                ->editColumn('status', function ($row) {
                    $statusClass = [
                        'pending' => 'label-warning',
                        'recognized' => 'label-success',
                        'cancelled' => 'label-danger',
                    ];
                    $class = $statusClass[$row->status] ?? 'label-default';
                    return '<span class="label ' . $class . '">' . ucfirst($row->status) . '</span>';
                })
                ->addColumn('deposit_account_name', function ($row) {
                    return $row->depositAccount->gl_code ?? '-';
                })
                ->addColumn('revenue_account_name', function ($row) {
                    return $row->revenueAccount->gl_code ?? '-';
                })
                ->addColumn('action', function ($row) {
                    $html = '';
                    if ($row->status === 'pending') {
                        $html .= '<button class="btn btn-xs btn-success process-single" data-id="' . $row->id . '">
                            <i class="fa fa-check"></i> ' . __('gym::lang.process_recognition') . '
                        </button>';
                    }
                    return $html;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        // Get summary
        $summary = $this->deferredService->getSummary($business_id);

        return view('gym::deferred_revenue.index', compact('summary'));
    }

    /**
     * Process pending recognitions
     */
    public function process(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'gym_module'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $asOfDate = $request->as_of_date ? Carbon::parse($request->as_of_date) : Carbon::now();
            
            $processed = $this->deferredService->processRecognition(
                $business_id,
                auth()->user()->id,
                $asOfDate
            );

            $count = count($processed);
            $totalAmount = collect($processed)->sum('recognition_amount');

            return response()->json([
                'success' => true,
                'msg' => __('gym::lang.recognition_processed') . " ({$count} records, " . number_format($totalAmount, 2) . ")",
                'count' => $count,
                'total_amount' => $totalAmount,
            ]);
        } catch (\Exception $e) {
            \Log::error('Deferred Revenue Process Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ], 500);
        }
    }

    /**
     * Process single recognition
     */
    public function processSingle(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'gym_module'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $schedule = GymDeferredRevenue::forBusiness($business_id)
                ->pending()
                ->findOrFail($id);

            // Create GL entry
            $this->createRecognitionGLEntry($schedule, auth()->user()->id);
            
            // Mark as recognized
            $schedule->markAsRecognized(auth()->user()->id);

            return response()->json([
                'success' => true,
                'msg' => __('gym::lang.recognition_processed'),
            ]);
        } catch (\Exception $e) {
            \Log::error('Single Recognition Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ], 500);
        }
    }

    /**
     * Create GL entry for recognition
     */
    private function createRecognitionGLEntry(GymDeferredRevenue $schedule, int $userId): void
    {
        // Dr. Member Deposit (reduce liability)
        \Modules\Accounting\Entities\AccountingAccountsTransaction::create([
            'accounting_account_id' => $schedule->deposit_account_id,
            'transaction_id' => $schedule->transaction_id,
            'transaction_payment_id' => null,
            'amount' => $schedule->recognition_amount,
            'type' => 'debit',
            'sub_type' => 'deferred_revenue_recognition',
            'map_type' => 'deferred_deposit',
            'created_by' => $userId,
            'operation_date' => $schedule->recognition_date,
            'note' => 'Revenue recognition: ' . $schedule->period_start->format('d/m/Y') . ' - ' . $schedule->period_end->format('d/m/Y'),
        ]);

        // Cr. Membership Revenue (recognize income)
        \Modules\Accounting\Entities\AccountingAccountsTransaction::create([
            'accounting_account_id' => $schedule->revenue_account_id,
            'transaction_id' => $schedule->transaction_id,
            'transaction_payment_id' => null,
            'amount' => $schedule->recognition_amount,
            'type' => 'credit',
            'sub_type' => 'deferred_revenue_recognition',
            'map_type' => 'deferred_revenue',
            'created_by' => $userId,
            'operation_date' => $schedule->recognition_date,
            'note' => 'Revenue recognition: ' . $schedule->period_start->format('d/m/Y') . ' - ' . $schedule->period_end->format('d/m/Y'),
        ]);
    }

    /**
     * View schedule for a specific subscription
     */
    public function viewSchedule($transaction_id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'gym_module'))) {
            abort(403, 'Unauthorized action.');
        }

        $schedules = $this->deferredService->getScheduleByTransaction($transaction_id);
        
        $transaction = Transaction::with(['contact', 'gym_package'])
            ->where('business_id', $business_id)
            ->findOrFail($transaction_id);

        return view('gym::deferred_revenue.schedule', compact('schedules', 'transaction'));
    }

    /**
     * Generate schedules for existing subscriptions that don't have schedules yet
     */
    public function generateMissing(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'gym_module'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            // Get all gym_subscription transactions that don't have schedules yet
            $subscriptionIds = GymDeferredRevenue::where('business_id', $business_id)
                ->pluck('transaction_id')
                ->toArray();

            $transactions = Transaction::where('business_id', $business_id)
                ->where('type', 'gym_subscription')
                ->whereNotNull('gym_package_end_date') // Not lifetime
                ->whereNotIn('id', $subscriptionIds) // Don't have schedule yet
                ->with('gym_package')
                ->get();

            $generated = 0;
            $skipped = 0;
            $errors = [];

            foreach ($transactions as $transaction) {
                $package = $transaction->gym_package;
                
                if (!$package) {
                    $skipped++;
                    $errors[] = "Transaction #{$transaction->id}: Package not found";
                    continue;
                }

                if (!$package->enable_deferred_revenue) {
                    $skipped++;
                    $errors[] = "Transaction #{$transaction->id}: Package '{$package->name}' doesn't have deferred revenue enabled";
                    continue;
                }

                if (empty($package->deposit_account_id) || empty($package->revenue_account_id)) {
                    $skipped++;
                    $errors[] = "Transaction #{$transaction->id}: Package '{$package->name}' missing deposit or revenue account";
                    continue;
                }

                // Generate schedule
                $schedules = $this->deferredService->generateSchedule($transaction, auth()->user()->id);
                
                if (count($schedules) > 0) {
                    $generated++;
                }
            }

            return response()->json([
                'success' => true,
                'msg' => "Generated schedules for {$generated} subscriptions. Skipped: {$skipped}",
                'generated' => $generated,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            \Log::error('Generate Missing Schedules Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Diagnostic endpoint to check package settings and subscription status
     */
    public function diagnostic(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'gym_module'))) {
            abort(403, 'Unauthorized action.');
        }

        // Get all packages with their deferred revenue settings
        $packages = GymPackage::where('business_id', $business_id)
            ->select('id', 'name', 'enable_deferred_revenue', 'deposit_account_id', 'revenue_account_id', 'bank_account_id', 'tax_account_id')
            ->with(['depositAccount:id,name,gl_code', 'revenueAccount:id,name,gl_code', 'bankAccount:id,name,gl_code'])
            ->get();

        // Get subscriptions without schedules
        $subscriptionIds = GymDeferredRevenue::where('business_id', $business_id)
            ->pluck('transaction_id')
            ->toArray();

        $subscriptionsWithoutSchedule = Transaction::where('business_id', $business_id)
            ->where('type', 'gym_subscription')
            ->whereNotIn('id', $subscriptionIds)
            ->with('contact:id,name')
            ->select('id', 'contact_id', 'gym_package_id', 'gym_package_start_date', 'gym_package_end_date', 'final_total', 'payment_status', 'created_at')
            ->get();

        // Get summary
        $totalSubscriptions = Transaction::where('business_id', $business_id)
            ->where('type', 'gym_subscription')
            ->count();

        $subscriptionsWithSchedule = count($subscriptionIds);

        return response()->json([
            'success' => true,
            'packages' => $packages,
            'subscriptions_without_schedule' => $subscriptionsWithoutSchedule,
            'summary' => [
                'total_subscriptions' => $totalSubscriptions,
                'with_schedule' => $subscriptionsWithSchedule,
                'without_schedule' => count($subscriptionsWithoutSchedule),
            ],
        ]);
    }
}
