<?php

namespace Modules\Gym\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Gym\Entities\GymClass;
use Yajra\DataTables\Facades\DataTables;
use App\Utils\Util;


class ClassController extends Controller
{

    protected $commonUtil;


    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $classes = GymClass::where('business_id', $business_id);
            return Datatables::of($classes)
                ->editColumn('created_at', '{{@format_datetime($created_at)}}')
                ->editColumn('class_type', function ($row) {
                    $types = [
                        'gym' => __('gym::lang.gym'),
                        'court' => __('gym::lang.court'),
                        'class' => __('gym::lang.class_type'),
                    ];
                    return $types[$row->class_type] ?? $row->class_type;
                })
                ->editColumn('start_time', function ($row) {
                    return $row->start_time ? $this->commonUtil->format_time($row->start_time) : '';
                })
                ->editColumn('end_time', function ($row) {
                    return $row->end_time ? $this->commonUtil->format_time($row->end_time) : '';
                })
                ->editColumn('is_active', function ($row) {
                    return $row->is_active 
                        ? '<span class="label label-success">' . __('gym::lang.active') . '</span>' 
                        : '<span class="label label-danger">' . __('gym::lang.inactive') . '</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '<a type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-primary btn-modal-class" href="' . action([\Modules\Gym\Http\Controllers\ClassController::class, 'edit'], ['class' => $row->id]) . '">'
                        . __('messages.edit') . '</a>';
                        $html .= ' <a href="' . route('delete_class', $row->id) . '"
                        class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-error delete_class_confirmation">' . __('messages.delete') . '</a>';    

                    return $html;
                })
                ->rawColumns(['created_at', 'action', 'is_active'])
                ->make(true);
        }
        return view('gym::class.index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('gym::class.create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        try {
            $input =  $request->except(['_token']);
            
            $input['business_id'] = request()->session()->get('user.business_id');

            $input['start_time'] = !empty($input['start_time']) ? $this->commonUtil->uf_time($input['start_time']) : null;
            $input['end_time'] = !empty($input['end_time']) ? $this->commonUtil->uf_time($input['end_time']) : null;
            
            // Handle checkbox fields
            $input['has_courts'] = isset($input['has_courts']) ? 1 : 0;
            $input['is_active'] = isset($input['is_active']) ? 1 : 0;
            
            // Handle numeric fields
            $input['price_per_hour'] = !empty($input['price_per_hour']) ? $this->commonUtil->num_uf($input['price_per_hour']) : 0;

            $class = GymClass::create($input);

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Gym\Http\Controllers\ClassController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
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
        $class = Gymclass::findOrFail($id);
        return view('gym::class.edit', compact('class'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request, $id)
    {
        try {

            $class = GymClass::findOrFail($id);
            $input =  $request->except(['_token']);
            $input['start_time'] = !empty($input['start_time']) ? $this->commonUtil->uf_time($input['start_time']) : null;
            $input['end_time'] = !empty($input['end_time']) ? $this->commonUtil->uf_time($input['end_time']) : null;
            
            // Handle checkbox fields
            $input['has_courts'] = isset($input['has_courts']) ? 1 : 0;
            $input['is_active'] = isset($input['is_active']) ? 1 : 0;
            
            // Handle numeric fields
            $input['price_per_hour'] = !empty($input['price_per_hour']) ? $this->commonUtil->num_uf($input['price_per_hour']) : 0;
            
            $class = $class->update($input);
            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];
            return redirect()
                ->action([\Modules\Gym\Http\Controllers\ClassController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());
            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        try {
            Gymclass::where('id', $id)->delete();
            $output = ['success' => 1, 'msg' => __('lang_v1.success')];
            return redirect()
                ->action([\Modules\Gym\Http\Controllers\ClassController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());
            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output)->withInput();
        }
    }
}
