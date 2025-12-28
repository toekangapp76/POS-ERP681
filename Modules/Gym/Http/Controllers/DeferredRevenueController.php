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

            return DataTables::of($schedules)
                ->addColumn('member_name', function ($row) {
                    return $row->transaction->contact->name ?? '-';
                })
                ->addColumn('package_name', function ($row) {
                    return $row->gymPackage->name ?? '-';
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
        
        $transaction = \App\Transaction::with(['contact', 'gymPackage'])
            ->where('business_id', $business_id)
            ->findOrFail($transaction_id);

        return view('gym::deferred_revenue.schedule', compact('schedules', 'transaction'));
    }
}
