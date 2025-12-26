<?php

namespace Modules\Gym\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\Transaction;
use App\User;
use App\Utils\Util;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Gym\Entities\GymBooking;
use Modules\Gym\Entities\GymClass;
use Modules\Gym\Entities\GymCourt;
use Yajra\DataTables\Facades\DataTables;

class BookingController extends Controller
{
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display calendar view of bookings
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $filters = [
                'start' => request()->start,
                'end' => request()->end,
                'class_id' => request()->class_id,
                'court_id' => request()->court_id,
                'member_id' => request()->member_id,
            ];

            return $this->getBookingsForCalendar($business_id, $filters);
        }

        $business_locations = BusinessLocation::forDropdown($business_id);
        $members = Contact::customersDropdown($business_id, false);
        $classes = GymClass::forDropdown($business_id);
        $agents = User::forDropdown($business_id, false);
        $booking_statuses = GymBooking::getStatuses();
        $types = Contact::getContactTypes();
        $customer_groups = CustomerGroup::forDropdown($business_id);

        return view('gym::booking.index', compact(
            'business_locations',
            'members',
            'classes',
            'agents',
            'booking_statuses',
            'types',
            'customer_groups'
        ));
    }

    /**
     * Get bookings for calendar display
     */
    protected function getBookingsForCalendar($business_id, $filters)
    {
        $query = GymBooking::where('business_id', $business_id)
            ->with(['member', 'gymClass', 'court', 'agent']);

        if (!empty($filters['start']) && !empty($filters['end'])) {
            $query->inDateRange($filters['start'], $filters['end']);
        }

        if (!empty($filters['class_id'])) {
            $query->where('gym_class_id', $filters['class_id']);
        }

        if (!empty($filters['court_id'])) {
            $query->where('court_id', $filters['court_id']);
        }

        if (!empty($filters['member_id'])) {
            $query->where('contact_id', $filters['member_id']);
        }

        $bookings = $query->get();

        $events = [];
        foreach ($bookings as $booking) {
            // Use member name or walk-in customer name
            $customerName = $booking->member->name ?? $booking->walkin_name ?? __('gym::lang.walk_in');
            $title = $customerName;
            if ($booking->gymClass) {
                $title .= ' - ' . $booking->gymClass->name;
            }
            if ($booking->court) {
                $title .= ' (' . $booking->court->name . ')';
            }

            $color = $booking->gymClass->color ?? '#667eea';
            
            // Adjust color based on status
            if ($booking->booking_status === GymBooking::STATUS_CANCELLED) {
                $color = '#dc3545';
            } elseif ($booking->booking_status === GymBooking::STATUS_COMPLETED) {
                $color = '#28a745';
            } elseif ($booking->booking_status === GymBooking::STATUS_PENDING) {
                $color = '#ffc107';
            }

            $events[] = [
                'id' => $booking->id,
                'title' => $title,
                'start' => $booking->booking_start->format('Y-m-d H:i:s'),
                'end' => $booking->booking_end->format('Y-m-d H:i:s'),
                'color' => $color,
                'url' => action([self::class, 'show'], $booking->id),
                'extendedProps' => [
                    'status' => $booking->booking_status,
                    'member_name' => $customerName,
                    'class_name' => $booking->gymClass->name ?? '',
                    'court_name' => $booking->court->name ?? '',
                ],
            ];
        }

        return response()->json($events);
    }

    /**
     * Show booking form (modal)
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');

        $members = Contact::customersDropdown($business_id, false);
        $classes = GymClass::where('business_id', $business_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $agents = User::forDropdown($business_id, false);
        $booking_statuses = GymBooking::getStatuses();

        // Pre-fill date/time if provided
        $start_date = request()->start ?? now()->format('Y-m-d');
        $start_time = request()->start_time ?? '09:00';

        return view('gym::booking.create', compact(
            'members',
            'classes',
            'agents',
            'booking_statuses',
            'start_date',
            'start_time'
        ));
    }

    /**
     * Store a new booking
     */
    public function store(Request $request)
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            // Validate request
            $validator = \Validator::make($request->all(), [
                'gym_class_id' => 'required|exists:gym_classes,id',
                'booking_date' => 'required',
                'booking_time' => 'required',
                'duration_minutes' => 'required|integer|min:30',
                'walkin_name' => 'required_without:contact_id',
            ], [
                'walkin_name.required_without' => __('gym::lang.member_or_walkin_required'),
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'msg' => $validator->errors()->first(),
                ]);
            }

            $input = $request->all();

            // Parse booking times - handle multiple date formats
            $booking_date = $this->commonUtil->uf_date($input['booking_date']);
            $booking_time = $input['booking_time'] ?? '09:00';
            
            // Combine date and time
            $booking_start = Carbon::parse($booking_date . ' ' . $booking_time);

            $duration_minutes = $input['duration_minutes'] ?? 60;
            $booking_end = $booking_start->copy()->addMinutes((int)$duration_minutes);

            // Check for conflicts
            $court_id = $input['court_id'] ?? null;
            $class_id = $input['gym_class_id'] ?? null;

            // Auto-assign court if requested
            if (empty($court_id) && !empty($input['auto_assign_court']) && $class_id) {
                $availableCourts = GymCourt::getAvailable(
                    $business_id,
                    $class_id,
                    $booking_start->format('Y-m-d H:i:s'),
                    $booking_end->format('Y-m-d H:i:s')
                );
                
                if ($availableCourts->isNotEmpty()) {
                    $court_id = $availableCourts->first()->id;
                }
            }

            $conflict = GymBooking::hasConflict(
                $business_id,
                $booking_start->format('Y-m-d H:i:s'),
                $booking_end->format('Y-m-d H:i:s'),
                $class_id,
                $court_id
            );

            if ($conflict) {
                $time_range = $this->commonUtil->format_date($conflict->booking_start, true) . ' ~ ' .
                    $this->commonUtil->format_date($conflict->booking_end, true);

                return response()->json([
                    'success' => false,
                    'msg' => __('gym::lang.booking_conflict', [
                        'member_name' => $conflict->member->name ?? '',
                        'time_range' => $time_range,
                    ]),
                ]);
            }

            // Calculate hours to deduct from subscription
            $hours_deducted = 0;
            if (!empty($input['subscription_id'])) {
                $hours_deducted = $duration_minutes / 60;
            }

            // Calculate reschedule deadline
            $gymClass = $class_id ? GymClass::find($class_id) : null;
            $reschedule_deadline = null;
            if ($gymClass && $gymClass->reschedule_deadline_days) {
                $reschedule_deadline = $booking_start->copy()->subDays($gymClass->reschedule_deadline_days);
            }

            $bookingData = [
                'business_id' => $business_id,
                'location_id' => $input['location_id'] ?? null,
                'contact_id' => !empty($input['contact_id']) ? $input['contact_id'] : null,
                'walkin_name' => !empty($input['walkin_name']) ? $input['walkin_name'] : null,
                'walkin_phone' => !empty($input['walkin_phone']) ? $input['walkin_phone'] : null,
                'subscription_id' => $input['subscription_id'] ?? null,
                'gym_class_id' => $class_id,
                'court_id' => $court_id,
                'agent_id' => $input['agent_id'] ?? null,
                'booking_start' => $booking_start->format('Y-m-d H:i:s'),
                'booking_end' => $booking_end->format('Y-m-d H:i:s'),
                'duration_minutes' => $duration_minutes,
                'booking_status' => $input['booking_status'] ?? GymBooking::STATUS_CONFIRMED,
                'hours_deducted' => $hours_deducted,
                'max_reschedule' => $gymClass->max_reschedule ?? 2,
                'reschedule_deadline' => $reschedule_deadline,
                'booking_note' => $input['booking_note'] ?? null,
                'created_by' => $user_id,
            ];

            $booking = GymBooking::createBooking($bookingData);

            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.added_success'),
                'booking_id' => $booking->id,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'msg' => $e->validator->errors()->first(),
            ]);
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            \Log::emergency('Trace: ' . $e->getTraceAsString());

            // In development, show actual error
            $msg = config('app.debug') 
                ? 'Error: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')'
                : __('messages.something_went_wrong');

            return response()->json([
                'success' => false,
                'msg' => $msg,
            ]);
        }
    }

    /**
     * Show booking details (modal)
     */
    public function show($id)
    {
        $business_id = request()->session()->get('user.business_id');

        $booking = GymBooking::where('business_id', $business_id)
            ->with(['member', 'gymClass', 'court', 'agent', 'subscription', 'createdBy'])
            ->findOrFail($id);

        $booking_statuses = GymBooking::getStatuses();

        return view('gym::booking.show', compact('booking', 'booking_statuses'));
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');

        $booking = GymBooking::where('business_id', $business_id)
            ->with(['member', 'gymClass', 'court'])
            ->findOrFail($id);

        $members = Contact::customersDropdown($business_id, false);
        $classes = GymClass::where('business_id', $business_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
        $agents = User::forDropdown($business_id, false);
        $booking_statuses = GymBooking::getStatuses();

        $courts = [];
        if ($booking->gym_class_id) {
            $courts = GymCourt::forDropdown($business_id, $booking->gym_class_id);
        }

        return view('gym::booking.edit', compact(
            'booking',
            'members',
            'classes',
            'courts',
            'agents',
            'booking_statuses'
        ));
    }

    /**
     * Update booking
     */
    public function update(Request $request, $id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');

            $booking = GymBooking::where('business_id', $business_id)->findOrFail($id);

            $input = $request->all();

            // Parse booking times if provided
            if (isset($input['booking_date'])) {
                $booking_start = Carbon::parse($input['booking_date'] . ' ' . ($input['booking_time'] ?? '09:00'));
                $duration_minutes = $input['duration_minutes'] ?? $booking->duration_minutes;
                $booking_end = $booking_start->copy()->addMinutes($duration_minutes);

                // Check for conflicts (excluding current booking)
                $conflict = GymBooking::hasConflict(
                    $business_id,
                    $booking_start->format('Y-m-d H:i:s'),
                    $booking_end->format('Y-m-d H:i:s'),
                    $input['gym_class_id'] ?? $booking->gym_class_id,
                    $input['court_id'] ?? $booking->court_id,
                    $booking->id
                );

                if ($conflict) {
                    return response()->json([
                        'success' => false,
                        'msg' => __('gym::lang.booking_conflict', [
                            'member_name' => $conflict->member->name ?? '',
                            'time_range' => $this->commonUtil->format_date($conflict->booking_start, true) . ' ~ ' .
                                $this->commonUtil->format_date($conflict->booking_end, true),
                        ]),
                    ]);
                }

                $booking->booking_start = $booking_start;
                $booking->booking_end = $booking_end;
                $booking->duration_minutes = $duration_minutes;

                // Track reschedule
                if ($booking->isDirty('booking_start')) {
                    $booking->reschedule_count++;
                }
            }

            // Update other fields
            if (isset($input['contact_id'])) {
                $booking->contact_id = !empty($input['contact_id']) ? $input['contact_id'] : null;
                // Clear walk-in fields if member is selected
                if (!empty($input['contact_id'])) {
                    $booking->walkin_name = null;
                    $booking->walkin_phone = null;
                }
            }
            // Walk-in customer fields
            if (isset($input['walkin_name'])) {
                $booking->walkin_name = !empty($input['walkin_name']) ? $input['walkin_name'] : null;
            }
            if (isset($input['walkin_phone'])) {
                $booking->walkin_phone = !empty($input['walkin_phone']) ? $input['walkin_phone'] : null;
            }
            if (isset($input['gym_class_id'])) {
                $booking->gym_class_id = $input['gym_class_id'];
            }
            if (isset($input['court_id'])) {
                $booking->court_id = $input['court_id'];
            }
            if (isset($input['agent_id'])) {
                $booking->agent_id = $input['agent_id'];
            }
            if (isset($input['subscription_id'])) {
                $booking->subscription_id = $input['subscription_id'];
            }
            if (isset($input['booking_status'])) {
                $booking->booking_status = $input['booking_status'];
            }
            if (isset($input['booking_note'])) {
                $booking->booking_note = $input['booking_note'];
            }

            $booking->save();

            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.updated_success'),
            ]);
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }

    /**
     * Delete booking
     */
    public function destroy($id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');

            $booking = GymBooking::where('business_id', $business_id)->findOrFail($id);
            
            // Restore hours to subscription if applicable
            // TODO: Implement hour restoration logic

            $booking->delete();

            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.deleted_success'),
            ]);
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }

    /**
     * Get today's bookings list
     */
    public function getTodaysBookings()
    {
        $business_id = request()->session()->get('user.business_id');

        $query = GymBooking::where('business_id', $business_id)
            ->whereDate('booking_start', today())
            ->whereIn('booking_status', [GymBooking::STATUS_PENDING, GymBooking::STATUS_CONFIRMED])
            ->with(['member', 'gymClass', 'court', 'agent']);

        if (!empty(request()->class_id)) {
            $query->where('gym_class_id', request()->class_id);
        }

        return DataTables::of($query)
            ->editColumn('member', function ($row) {
                return $row->member->name ?? '--';
            })
            ->editColumn('class', function ($row) {
                return $row->gymClass->name ?? '--';
            })
            ->editColumn('court', function ($row) {
                return $row->court->name ?? '--';
            })
            ->editColumn('agent', function ($row) {
                return $row->agent->user_full_name ?? '--';
            })
            ->editColumn('booking_start', function ($row) {
                return $this->commonUtil->format_date($row->booking_start, true);
            })
            ->editColumn('booking_end', function ($row) {
                return $this->commonUtil->format_date($row->booking_end, true);
            })
            ->editColumn('duration', function ($row) {
                return $row->formatted_duration;
            })
            ->editColumn('status', function ($row) {
                $statuses = GymBooking::getStatuses();
                return '<span class="badge ' . $row->getStatusBadgeClass() . '">' . 
                    ($statuses[$row->booking_status] ?? $row->booking_status) . '</span>';
            })
            ->addColumn('action', function ($row) {
                $html = '<button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info view-booking" data-href="' . 
                    action([self::class, 'show'], $row->id) . '"><i class="fa fa-eye"></i></button> ';
                $html .= '<button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit-booking" data-href="' . 
                    action([self::class, 'edit'], $row->id) . '"><i class="fa fa-edit"></i></button> ';
                $html .= '<button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete-booking" data-href="' . 
                    action([self::class, 'destroy'], $row->id) . '"><i class="fa fa-trash"></i></button>';
                return $html;
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    /**
     * Get courts for a class (AJAX)
     */
    public function getCourts($class_id)
    {
        $business_id = request()->session()->get('user.business_id');

        $courts = GymCourt::where('business_id', $business_id)
            ->where('gym_class_id', $class_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'price_per_hour']);

        return response()->json($courts);
    }

    /**
     * Get member subscriptions (AJAX)
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
                'remaining_hours' => $sub->gym_remaining_minutes ? round($sub->gym_remaining_minutes / 60, 1) : null,
                'remaining_sessions' => $sub->gym_remaining_sessions,
            ];
        });

        return response()->json($result);
    }

    /**
     * Check availability (AJAX)
     */
    public function checkAvailability(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        $class_id = $request->class_id;
        $court_id = $request->court_id;
        $date = $request->date;
        $time = $request->time;
        $duration = $request->duration ?? 60;

        $start = Carbon::parse($date . ' ' . $time);
        $end = $start->copy()->addMinutes($duration);

        $conflict = GymBooking::hasConflict(
            $business_id,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s'),
            $class_id,
            $court_id,
            $request->exclude_booking_id
        );

        if ($conflict) {
            return response()->json([
                'available' => false,
                'conflict' => [
                    'member_name' => $conflict->member->name ?? '',
                    'start' => $this->commonUtil->format_date($conflict->booking_start, true),
                    'end' => $this->commonUtil->format_date($conflict->booking_end, true),
                ],
            ]);
        }

        // If class has courts, get available courts
        $available_courts = [];
        if ($class_id && !$court_id) {
            $gymClass = GymClass::find($class_id);
            if ($gymClass && $gymClass->has_courts) {
                $available_courts = GymCourt::getAvailable($business_id, $class_id, $start, $end)
                    ->map(function ($court) {
                        return ['id' => $court->id, 'name' => $court->name];
                    });
            }
        }

        return response()->json([
            'available' => true,
            'available_courts' => $available_courts,
        ]);
    }
}
