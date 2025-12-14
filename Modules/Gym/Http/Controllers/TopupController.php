<?php

namespace Modules\Gym\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use App\Transaction;
use App\Contact;
use Modules\Gym\Entities\GymHourTopup;
use App\Utils\Util;
use Yajra\DataTables\Facades\DataTables;

class TopupController extends Controller
{
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of topups
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $topups = GymHourTopup::where('gym_hour_topups.business_id', $business_id)
                ->leftJoin('contacts', 'gym_hour_topups.contact_id', '=', 'contacts.id')
                ->leftJoin('transactions as topup_trans', 'gym_hour_topups.transaction_id', '=', 'topup_trans.id')
                ->leftJoin('transactions as sub_trans', 'gym_hour_topups.subscription_id', '=', 'sub_trans.id')
                ->leftJoin('gym_packages', 'sub_trans.gym_package_id', '=', 'gym_packages.id')
                ->leftJoin('users', 'gym_hour_topups.created_by', '=', 'users.id')
                ->select(
                    'gym_hour_topups.*',
                    'contacts.name as member_name',
                    'gym_packages.name as package_name',
                    'users.first_name as created_by_name',
                    'topup_trans.ref_no as ref_no',
                    'topup_trans.payment_status'
                );

            return DataTables::of($topups)
                ->editColumn('created_at', '{{@format_datetime($created_at)}}')
                ->editColumn('hours_added', function ($row) {
                    return number_format($row->hours_added, 1) . ' ' . __('gym::lang.hours');
                })
                ->editColumn('amount', function ($row) {
                    return $this->commonUtil->num_f($row->amount, true);
                })
                ->addColumn('ref_number', function ($row) {
                    return $row->ref_no ?? '--';
                })
                ->addColumn('action', function ($row) {
                    $html = '<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete_topup" data-href="' . action([\Modules\Gym\Http\Controllers\TopupController::class, 'destroy'], $row->id) . '">
                        <i class="fa fa-trash"></i>
                    </button>';
                    return $html;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $members = Contact::where('business_id', $business_id)
            ->where('type', 'customer')
            ->whereIn('contact_id', function($query) use ($business_id) {
                $query->select('contact_id')
                    ->from('transactions')
                    ->where('business_id', $business_id)
                    ->where('type', 'gym_subscription');
            })
            ->pluck('name', 'id');

        return view('gym::topup.index', compact('members'));
    }

    /**
     * Show the form for creating a new topup
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');

        $members = Contact::where('business_id', $business_id)
            ->where('type', 'customer')
            ->pluck('name', 'id');

        return view('gym::topup.create', compact('members'));
    }

    /**
     * Store a newly created topup
     */
    public function store(Request $request)
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');
            $location_id = request()->session()->get('user.business_location_id');

            $input = $request->all();

            // Get subscription
            $subscription = Transaction::where('business_id', $business_id)
                ->where('id', $input['subscription_id'])
                ->where('type', 'gym_subscription')
                ->first();

            if (!$subscription) {
                return response()->json([
                    'success' => false,
                    'msg' => __('gym::lang.no_active_subscription'),
                ]);
            }

            DB::beginTransaction();

            // Generate reference number
            $ref_count = $this->commonUtil->setAndGetReferenceCount('gym_topup', $business_id);
            $ref_no = $this->commonUtil->generateReferenceNumber('gym_topup', $ref_count, $business_id);

            $amount = !empty($input['amount']) ? $this->commonUtil->num_uf($input['amount']) : 0;

            // Create transaction record
            $transaction = new Transaction();
            $transaction->business_id = $business_id;
            $transaction->location_id = $location_id;
            $transaction->type = 'gym_subscription';
            $transaction->sub_type = 'hour_topup';
            $transaction->status = 'final';
            $transaction->contact_id = $subscription->contact_id;
            $transaction->gym_package_id = $subscription->gym_package_id;
            $transaction->ref_no = $ref_no;
            $transaction->transaction_date = now();
            $transaction->total_before_tax = $amount;
            $transaction->final_total = $amount;
            $transaction->payment_status = $amount > 0 ? 'due' : 'paid';
            $transaction->created_by = $user_id;
            $transaction->additional_notes = $input['note'] ?? null;
            $transaction->save();

            // Create topup record linked to transaction
            $topup = GymHourTopup::create([
                'business_id' => $business_id,
                'transaction_id' => $transaction->id,
                'subscription_id' => $subscription->id,
                'contact_id' => $subscription->contact_id,
                'hours_added' => $this->commonUtil->num_uf($input['hours_added']),
                'amount' => $amount,
                'note' => $input['note'] ?? null,
                'created_by' => $user_id,
            ]);

            // Update subscription remaining minutes
            $hours_in_minutes = $this->commonUtil->num_uf($input['hours_added']) * 60;
            $subscription->gym_remaining_minutes = ($subscription->gym_remaining_minutes ?? 0) + $hours_in_minutes;
            
            // Update session status if was exhausted
            if ($subscription->gym_session_status === 'exhausted' && $subscription->gym_remaining_minutes > 0) {
                $subscription->gym_session_status = 'active';
            }
            $subscription->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.added_success'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }

    /**
     * Get member subscriptions for topup
     */
    public function getMemberSubscriptions($contact_id)
    {
        $business_id = request()->session()->get('user.business_id');

        $subscriptions = Transaction::where('business_id', $business_id)
            ->where('contact_id', $contact_id)
            ->where('type', 'gym_subscription')
            ->where('status', 'final')
            ->where(function ($q) {
                $q->whereNull('gym_package_end_date')
                  ->orWhere('gym_package_end_date', '>=', today());
            })
            ->with(['gym_package'])
            ->get();

        $result = $subscriptions->map(function ($sub) {
            return [
                'id' => $sub->id,
                'name' => $sub->gym_package->name ?? 'Subscription #' . $sub->id,
                'end_date' => $sub->gym_package_end_date ? $this->commonUtil->format_date($sub->gym_package_end_date) : __('gym::lang.unlimited'),
                'remaining_hours' => $sub->gym_remaining_minutes ? round($sub->gym_remaining_minutes / 60, 1) : 0,
            ];
        });

        return response()->json($result);
    }

    /**
     * Remove the specified topup
     */
    public function destroy($id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');

            $topup = GymHourTopup::where('business_id', $business_id)
                ->findOrFail($id);

            DB::beginTransaction();

            // Deduct the hours from subscription
            $subscription = Transaction::find($topup->subscription_id);
            if ($subscription) {
                $hours_in_minutes = $topup->hours_added * 60;
                $subscription->gym_remaining_minutes = max(0, ($subscription->gym_remaining_minutes ?? 0) - $hours_in_minutes);
                
                // Update session status if needed
                $package = $subscription->gym_package;
                if ($package && $package->session_limit_enabled && $subscription->gym_remaining_minutes <= 0) {
                    $subscription->gym_session_status = 'exhausted';
                }
                $subscription->save();
            }

            // Delete the transaction if exists
            if ($topup->transaction_id) {
                Transaction::where('id', $topup->transaction_id)
                    ->where('business_id', $business_id)
                    ->delete();
            }

            $topup->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.deleted_success'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }
}
