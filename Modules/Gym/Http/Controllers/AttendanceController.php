<?php

namespace Modules\Gym\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Contact;
use App\Transaction;
use App\Business;
use Yajra\DataTables\Facades\DataTables;
use App\Utils\ContactUtil;
use App\Utils\Util;
use App\Utils\ModuleUtil;
use Carbon\Carbon;
use Modules\Gym\Entities\GymAttendance;
use Modules\Gym\Entities\GymPackage;
use Modules\Gym\Services\SessionTrackingService;
use Illuminate\Support\Facades\Crypt;


class AttendanceController extends Controller
{
    protected $contactUtil;
    protected $commonUtil;
    protected $moduleUtil;
    protected $sessionTrackingService;

    public function __construct(
        ContactUtil $contactUtil,
        Util $commonUtil,
        ModuleUtil $moduleUtil,
        SessionTrackingService $sessionTrackingService,
    ) {
        $this->contactUtil = $contactUtil;
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
        $this->sessionTrackingService = $sessionTrackingService;
    }
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'gym_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (!auth()->user()->can('gym.manage_attendance')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            // Get filter date or default to today (expects Y-m-d format from datepicker)
            $filterDate = $request->filter_date ?? Carbon::today()->toDateString();
            
            // Validate date format
            try {
                $filterDate = Carbon::parse($filterDate)->toDateString();
            } catch (\Exception $e) {
                $filterDate = Carbon::today()->toDateString();
            }

            $query = GymAttendance::where('gym_attendances.contact_id', '!=', null)
                ->join('contacts', 'gym_attendances.contact_id', '=', 'contacts.id')
                ->where('contacts.business_id', $business_id)
                ->whereDate('gym_attendances.date', $filterDate)
                ->select([
                    'gym_attendances.*',
                    'contacts.name',
                    'contacts.email',
                    'contacts.mobile',
                ])
                ->orderBy('gym_attendances.id', 'desc');

            return Datatables::of($query)
                ->editColumn('date', function($row) {
                    return $this->commonUtil->format_date($row->date);
                })
                ->addColumn('in', function ($row) {
                    return $row->in_time ? $this->commonUtil->format_time($row->in_time) : '-';
                })
                ->addColumn('out', function ($row) {
                    return $row->out_time ? $this->commonUtil->format_time($row->out_time) : '-';
                })
                ->addColumn('duration', function ($row) {
                    if ($row->duration_minutes) {
                        $hours = floor($row->duration_minutes / 60);
                        $mins = $row->duration_minutes % 60;
                        return $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
                    }
                    return '-';
                })
                ->addColumn('action', function ($row) {
                    // Edit check-in button
                    $html = '<a type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-success btn-modal-in" href="' . action([\Modules\Gym\Http\Controllers\AttendanceController::class, 'get_in'], ['id' => $row->contact_id]) . '">
                        In
                    </a> ';
                    // Edit check-out button
                    $html .= '<a type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-warning btn-modal-out" href="' . action([\Modules\Gym\Http\Controllers\AttendanceController::class, 'get_out'], ['id' => $row->contact_id]) . '">
                        Out
                    </a> ';
                    // Delete button
                    $html .= '<button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete-attendance" data-id="' . $row->id . '">
                        <i class="fa fa-trash"></i>
                    </button>';
                    return $html;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
        return view('gym::attendance.index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('gym::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('gym::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('gym::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }

    public function get_in($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'gym_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if (!auth()->user()->can('gym.manage_attendance')) {
            abort(403, 'Unauthorized action.');
        }
        $member = Contact::findOrFail($id);
        
        $attendance = GymAttendance::where('contact_id', $id)->whereDate('date', Carbon::today())->first();

        return view('gym::attendance.in_time', compact('member', 'attendance'));
    }

    public function add_edit_in_time(Request $request)
    {

        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'gym_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'gym.manage_attendance')){
            abort(403, 'Unauthorized action.');
        }


        try {
            // Check if member has active subscription first
            $sessionStatus = $this->sessionTrackingService->getMemberSessionStatus($request->contact_id, $business_id);
            
            if (!$sessionStatus['has_active_subscription']) {
                return response()->json([
                    'success' => false,
                    'msg' => __('gym::lang.no_active_subscription_checkin_denied'),
                ]);
            }
            
            // Check if quota is exhausted
            if ($sessionStatus['quota_exhausted'] ?? false) {
                return response()->json([
                    'success' => false,
                    'msg' => __('gym::lang.quota_exhausted_checkin_denied'),
                ]);
            }
            
            $inTime = $this->commonUtil->uf_time($request->in_time);
            
            // Use session tracking service for check-in
            $result = $this->sessionTrackingService->processCheckIn(
                $request->contact_id,
                $business_id,
                $inTime
            );

            $response = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            // Add session info to response
            if ($result['has_active_subscription']) {
                $response['has_subscription'] = true;
                $response['has_session_limit'] = $result['has_session_limit'] ?? false;
                $response['has_session_count_limit'] = $result['has_session_count_limit'] ?? false;
                if (isset($result['remaining_minutes'])) {
                    $response['remaining_minutes'] = $result['remaining_minutes'];
                }
                if (isset($result['remaining_sessions'])) {
                    $response['remaining_sessions'] = $result['remaining_sessions'];
                }
            } else {
                $response['has_subscription'] = false;
                $response['warning'] = __('gym::lang.no_active_subscription');
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    public function get_out($id)
    {
        $business_id = request()->session()->get('user.business_id');
        
        $member = Contact::findOrFail($id);

        $attendance = GymAttendance::where('contact_id', $id)->whereDate('date', Carbon::today())->first();

        // Get session status for display
        $sessionStatus = $this->sessionTrackingService->getMemberSessionStatus($id, $business_id);

        return view('gym::attendance.out_time', compact('member', 'attendance', 'sessionStatus'));
    }

    public function add_edit_out_time(Request $request)
    {

        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'gym_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can( 'gym.manage_attendance')){
            abort(403, 'Unauthorized action.');
        }

        
        try {
            $outTime = $this->commonUtil->uf_time($request->out_time);
            
            // Use session tracking service for check-out
            $result = $this->sessionTrackingService->processCheckOut(
                $request->contact_id,
                $business_id,
                $outTime
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? __('messages.something_went_wrong'),
                ]);
            }

            $response = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
                'duration_minutes' => $result['duration_minutes'] ?? 0,
            ];

            // Add session tracking info
            if ($result['session_deducted']) {
                $response['session_deducted'] = true;
                
                if (isset($result['remaining_minutes'])) {
                    $response['remaining_minutes'] = $result['remaining_minutes'];
                }
                if (isset($result['remaining_sessions'])) {
                    $response['remaining_sessions'] = $result['remaining_sessions'];
                }
                
                if (isset($result['package_exhausted']) && $result['package_exhausted']) {
                    $response['package_exhausted'] = true;
                    $response['warning'] = __('gym::lang.package_exhausted');
                }

                if (isset($result['overtime_minutes']) && $result['overtime_minutes'] > 0) {
                    $response['overtime_minutes'] = $result['overtime_minutes'];
                    $response['overtime_handled'] = $result['overtime_handled'] ?? 'recorded';
                }
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete attendance record
     */
    public function deleteAttendance(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') || $this->moduleUtil->hasThePermissionInSubscription($business_id, 'gym_module'))) {
            abort(403, 'Unauthorized action.');
        }

        if(!auth()->user()->can('gym.manage_attendance')){
            abort(403, 'Unauthorized action.');
        }

        try {
            $attendance = GymAttendance::findOrFail($request->id);
            $attendance->delete();

            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.success'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Public attendance scanner page
     */
    public function publicScanner()
    {
        return view('gym::attendance.public_scanner');
    }

    /**
     * Process public check-in/check-out via QR code
     */
    public function publicCheckInOut(Request $request)
    {
        try {
            $memberId = Crypt::decryptString($request->member_id);
            $contact = Contact::findOrFail($memberId);
            $business_id = $contact->business_id;

            // Get business timezone
            $business = Business::find($business_id);
            $timezone = $business->time_zone ?? config('app.timezone');
            
            $today = Carbon::now($timezone)->toDateString();
            $now = Carbon::now($timezone)->format('H:i:s');

            // Get the latest attendance for today
            $latestAttendance = GymAttendance::where('contact_id', $memberId)
                ->whereDate('date', $today)
                ->orderBy('id', 'desc')
                ->first();

            $action = '';
            $message = '';

            // Determine action: check-in or check-out
            if (!$latestAttendance || ($latestAttendance->in_time && $latestAttendance->out_time)) {
                // No attendance today OR last session completed -> New check-in
                $action = 'check_in';
                
                // Check if member has active subscription first
                $sessionStatus = $this->sessionTrackingService->getMemberSessionStatus($memberId, $business_id);
                
                if (!$sessionStatus['has_active_subscription']) {
                    return response()->json([
                        'success' => false,
                        'action' => 'check_in_denied',
                        'message' => __('gym::lang.no_active_subscription_checkin_denied'),
                        'member_name' => $contact->name,
                        'time' => Carbon::now($timezone)->format('H:i'),
                    ]);
                }
                
                // Check if quota is exhausted
                if ($sessionStatus['quota_exhausted'] ?? false) {
                    return response()->json([
                        'success' => false,
                        'action' => 'check_in_denied',
                        'message' => __('gym::lang.quota_exhausted_checkin_denied'),
                        'member_name' => $contact->name,
                        'time' => Carbon::now($timezone)->format('H:i'),
                    ]);
                }
                
                $result = $this->sessionTrackingService->processCheckIn(
                    $memberId,
                    $business_id,
                    $now,
                    $today
                );

                $message = __('gym::lang.check_in_success');

                // Get session info
                $sessionInfo = null;
                if ($result['has_active_subscription']) {
                    $infoArr = [];
                    if (isset($result['remaining_minutes'])) {
                        $hours = floor($result['remaining_minutes'] / 60);
                        $mins = $result['remaining_minutes'] % 60;
                        $infoArr[] = "{$hours}h {$mins}m";
                    }
                    if (isset($result['remaining_sessions']) && $result['remaining_sessions'] !== null) {
                        $infoArr[] = $result['remaining_sessions'] . ' ' . __('gym::lang.sessions');
                    }
                    if (!empty($infoArr)) {
                        $sessionInfo = implode(' | ', $infoArr) . ' ' . __('gym::lang.remaining');
                    }
                }

                return response()->json([
                    'success' => true,
                    'action' => $action,
                    'message' => $message,
                    'member_name' => $contact->name,
                    'time' => $this->commonUtil->format_time($now),
                    'has_subscription' => $result['has_active_subscription'],
                    'session_info' => $sessionInfo,
                ]);

            } elseif ($latestAttendance->in_time && !$latestAttendance->out_time) {
                // Has check-in but no check-out -> Check-out
                $action = 'check_out';

                $result = $this->sessionTrackingService->processCheckOut(
                    $memberId,
                    $business_id,
                    $now,
                    $today
                );

                $message = __('gym::lang.check_out_success');

                // Calculate duration
                $durationInfo = null;
                if (isset($result['duration_minutes'])) {
                    $hours = floor($result['duration_minutes'] / 60);
                    $mins = $result['duration_minutes'] % 60;
                    $durationInfo = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
                }

                // Remaining info
                $remainingInfo = null;
                $infoArr = [];
                if (isset($result['remaining_minutes'])) {
                    $hours = floor($result['remaining_minutes'] / 60);
                    $mins = $result['remaining_minutes'] % 60;
                    $infoArr[] = "{$hours}h {$mins}m";
                }
                if (isset($result['remaining_sessions']) && $result['remaining_sessions'] !== null) {
                    $infoArr[] = $result['remaining_sessions'] . ' ' . __('gym::lang.sessions');
                }
                if (!empty($infoArr)) {
                    $remainingInfo = implode(' | ', $infoArr) . ' ' . __('gym::lang.remaining');
                }

                // Get active subscription info for display
                $subscription = $this->sessionTrackingService->getActiveSubscription($memberId, $business_id);
                $hasSubscription = $subscription !== null;
                
                return response()->json([
                    'success' => true,
                    'action' => $action,
                    'message' => $message,
                    'member_name' => $contact->name,
                    'time' => $this->commonUtil->format_time($now),
                    'duration' => $durationInfo,
                    'remaining_info' => $remainingInfo,
                    'has_subscription' => $hasSubscription,
                    'package_exhausted' => $result['package_exhausted'] ?? false,
                ]);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get member attendance status for display
     */
    public function getMemberStatus(Request $request)
    {
        try {
            $memberId = Crypt::decryptString($request->member_id);
            $contact = Contact::findOrFail($memberId);
            $business_id = $contact->business_id;

            $today = Carbon::today()->toDateString();

            // Get today's attendances
            $todayAttendances = GymAttendance::where('contact_id', $memberId)
                ->whereDate('date', $today)
                ->orderBy('id', 'desc')
                ->get();

            // Get session status
            $sessionStatus = $this->sessionTrackingService->getMemberSessionStatus($memberId, $business_id);

            // Determine current status
            $latestAttendance = $todayAttendances->first();
            $currentStatus = 'not_checked_in';
            
            if ($latestAttendance) {
                if ($latestAttendance->in_time && !$latestAttendance->out_time) {
                    $currentStatus = 'checked_in';
                } elseif ($latestAttendance->in_time && $latestAttendance->out_time) {
                    $currentStatus = 'checked_out';
                }
            }

            return response()->json([
                'success' => true,
                'member_name' => $contact->name,
                'member_photo' => $contact->gym_member_profile_photo,
                'current_status' => $currentStatus,
                'today_sessions' => $todayAttendances->count(),
                'session_status' => $sessionStatus,
                'next_action' => $currentStatus === 'checked_in' ? 'check_out' : 'check_in',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ]);
        }
    }
}
