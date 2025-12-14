<?php

namespace Modules\Gym\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Gym\Entities\GymClass;
use Modules\Gym\Entities\GymCourt;
use App\Utils\Util;
use Yajra\DataTables\Facades\DataTables;

class CourtController extends Controller
{
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of courts
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $courts = GymCourt::where('gym_courts.business_id', $business_id)
                ->leftJoin('gym_classes', 'gym_courts.gym_class_id', '=', 'gym_classes.id')
                ->select('gym_courts.*', 'gym_classes.name as gym_class_name');

            return DataTables::of($courts)
                ->editColumn('gym_class', function ($row) {
                    return $row->gym_class_name ?? '--';
                })
                ->editColumn('price_per_hour', function ($row) {
                    return $this->commonUtil->num_f($row->price_per_hour);
                })
                ->editColumn('is_active', function ($row) {
                    return $row->is_active 
                        ? '<span class="badge bg-success">' . __('gym::lang.active') . '</span>'
                        : '<span class="badge bg-danger">' . __('gym::lang.inactive') . '</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '<button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-href="' . 
                        action([self::class, 'edit'], $row->id) . '" data-container=".court_modal">' . 
                        '<i class="fa fa-edit"></i> ' . __('messages.edit') . '</button> ';
                    $html .= '<button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete_court" data-href="' . 
                        action([self::class, 'destroy'], $row->id) . '">' . 
                        '<i class="fa fa-trash"></i> ' . __('messages.delete') . '</button>';
                    return $html;
                })
                ->rawColumns(['is_active', 'action'])
                ->make(true);
        }

        $classes = GymClass::forDropdown($business_id);

        return view('gym::court.index', compact('classes'));
    }

    /**
     * Show the form for creating a new court
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        
        $classes = GymClass::where('business_id', $business_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id');

        return view('gym::court.create', compact('classes'));
    }

    /**
     * Store a newly created court
     */
    public function store(Request $request)
    {
        try {
            $business_id = request()->session()->get('user.business_id');

            $input = $request->validate([
                'name' => 'required|string|max:255',
                'gym_class_id' => 'required|exists:gym_classes,id',
                'code' => 'nullable|string|max:50',
                'description' => 'nullable|string',
                'price_per_hour' => 'nullable|numeric|min:0',
                'capacity' => 'nullable|integer|min:1',
                'is_active' => 'nullable|boolean',
                'sort_order' => 'nullable|integer',
            ]);

            $input['business_id'] = $business_id;
            $input['price_per_hour'] = $this->commonUtil->num_uf($input['price_per_hour'] ?? 0);
            $input['is_active'] = isset($input['is_active']) ? 1 : 0;

            GymCourt::create($input);

            // Update class to indicate it has courts
            GymClass::where('id', $input['gym_class_id'])->update(['has_courts' => true]);

            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.added_success'),
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
     * Show the form for editing a court
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');

        $court = GymCourt::where('business_id', $business_id)->findOrFail($id);
        
        $classes = GymClass::where('business_id', $business_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name', 'id');

        return view('gym::court.edit', compact('court', 'classes'));
    }

    /**
     * Update the specified court
     */
    public function update(Request $request, $id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');

            $court = GymCourt::where('business_id', $business_id)->findOrFail($id);

            $input = $request->validate([
                'name' => 'required|string|max:255',
                'gym_class_id' => 'required|exists:gym_classes,id',
                'code' => 'nullable|string|max:50',
                'description' => 'nullable|string',
                'price_per_hour' => 'nullable|numeric|min:0',
                'capacity' => 'nullable|integer|min:1',
                'is_active' => 'nullable|boolean',
                'sort_order' => 'nullable|integer',
            ]);

            $input['price_per_hour'] = $this->commonUtil->num_uf($input['price_per_hour'] ?? 0);
            $input['is_active'] = isset($input['is_active']) ? 1 : 0;

            $court->update($input);

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
     * Remove the specified court
     */
    public function destroy($id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');

            $court = GymCourt::where('business_id', $business_id)->findOrFail($id);
            $class_id = $court->gym_class_id;
            
            $court->delete();

            // Check if class still has courts
            $remaining = GymCourt::where('gym_class_id', $class_id)->count();
            if ($remaining == 0) {
                GymClass::where('id', $class_id)->update(['has_courts' => false]);
            }

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
}
