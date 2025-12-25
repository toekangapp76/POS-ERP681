<?php

namespace Modules\Gym\Http\Controllers;

use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Gym\Entities\GymCategory;
use Yajra\DataTables\Facades\DataTables;

class GymCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $categories = GymCategory::where('business_id', $business_id);
            
            return DataTables::of($categories)
                ->editColumn('created_at', '{{@format_datetime($created_at)}}')
                ->editColumn('is_active', function ($row) {
                    if ($row->is_active) {
                        return '<span class="label label-success">' . __('gym::lang.active') . '</span>';
                    }
                    return '<span class="label label-danger">' . __('gym::lang.inactive') . '</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '<a type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" 
                        data-href="' . action([\Modules\Gym\Http\Controllers\GymCategoryController::class, 'edit'], ['gym_category' => $row->id]) . '" 
                        data-container=".view_modal">' . __('messages.edit') . '</a>';
                    
                    $html .= ' <a href="' . action([\Modules\Gym\Http\Controllers\GymCategoryController::class, 'destroy'], ['gym_category' => $row->id]) . '"
                        class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete_category_confirmation">' . __('messages.delete') . '</a>';

                    return $html;
                })
                ->rawColumns(['created_at', 'action', 'is_active'])
                ->make(true);
        }
        
        return view('gym::categories.index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('gym::categories.create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        try {
            $input = $request->except(['_token']);
            $input['is_active'] = $request->has('is_active') ? 1 : 0;
            $input['created_by'] = auth()->user()->id;
            $input['business_id'] = request()->session()->get('user.business_id');
            
            GymCategory::create($input);

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Gym\Http\Controllers\GymCategoryController::class, 'index'])
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
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $category = GymCategory::where('business_id', $business_id)->findOrFail($id);
        
        return view('gym::categories.edit', compact('category'));
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
            $business_id = request()->session()->get('user.business_id');
            $category = GymCategory::where('business_id', $business_id)->findOrFail($id);
            
            $input = $request->except(['_token', '_method']);
            $input['is_active'] = $request->has('is_active') ? 1 : 0;
            
            $category->update($input);

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Gym\Http\Controllers\GymCategoryController::class, 'index'])
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
            $business_id = request()->session()->get('user.business_id');
            $category = GymCategory::where('business_id', $business_id)->findOrFail($id);
            
            // Check if category is used in any packages
            if ($category->packages()->count() > 0) {
                $output = [
                    'success' => 0,
                    'msg' => __('gym::lang.category_in_use'),
                ];
                return redirect()->back()->with('status', $output);
            }
            
            $category->delete();

            $output = [
                'success' => 1,
                'msg' => __('lang_v1.success'),
            ];

            return redirect()
                ->action([\Modules\Gym\Http\Controllers\GymCategoryController::class, 'index'])
                ->with('status', $output);
        } catch (\Exception $e) {
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = [
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];

            return back()->with('status', $output);
        }
    }
}
