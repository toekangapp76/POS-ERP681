<?php

namespace App\Http\Controllers;

use App\ExpenseCategory;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class ExpenseCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $expense_category = ExpenseCategory::where('business_id', $business_id)
                        ->select(['name', 'code', 'id', 'parent_id', 'pnl_group']);

            return Datatables::of($expense_category)
                ->addColumn(
                    'action',
                    '<button data-href="{{action(\'App\Http\Controllers\ExpenseCategoryController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".expense_category_modal"><i class="glyphicon glyphicon-edit"></i>  @lang("messages.edit")</button>
                        &nbsp;
                        <button data-href="{{action(\'App\Http\Controllers\ExpenseCategoryController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_expense_category"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>'
                )
                ->addColumn('pnl_group_display', function ($row) {
                    if (!empty($row->pnl_group)) {
                        return '<span class="label label-info">' . $row->pnl_group . '</span>';
                    }
                    return '<span class="label label-warning">Belum di-set</span>';
                })
                ->editColumn('name', function ($row) {
                    if (! empty($row->parent_id)) {
                        return '--'.$row->name;
                    } else {
                        return $row->name;
                    }
                })
                ->removeColumn('id')
                ->removeColumn('parent_id')
                ->removeColumn('pnl_group')
                ->rawColumns([2, 3])
                ->make(false);
        }

        return view('expense_category.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $categories = ExpenseCategory::where('business_id', $business_id)
                        ->whereNull('parent_id')
                        ->pluck('name', 'id');

        return view('expense_category.create')->with(compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only(['name', 'code']);
            $input['business_id'] = $request->session()->get('user.business_id');

            // Add default expense account if provided
            if (!empty($request->input('default_expense_account_id'))) {
                $input['default_expense_account_id'] = $request->input('default_expense_account_id');
            }

            if (! empty($request->input('add_as_sub_cat')) && $request->input('add_as_sub_cat') == 1 && ! empty($request->input('parent_id'))) {
                $input['parent_id'] = $request->input('parent_id');
            }

            ExpenseCategory::create($input);
            $output = ['success' => true,
                'msg' => __('expense.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\ExpenseCategory  $expenseCategory
     * @return \Illuminate\Http\Response
     */
    public function show(ExpenseCategory $expenseCategory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $expense_category = ExpenseCategory::where('business_id', $business_id)->find($id);

            $categories = ExpenseCategory::where('business_id', $business_id)
                        ->whereNull('parent_id')
                        ->pluck('name', 'id');

            return view('expense_category.edit')
                    ->with(compact('expense_category', 'categories'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'code']);
                $business_id = $request->session()->get('user.business_id');

                $expense_category = ExpenseCategory::where('business_id', $business_id)->findOrFail($id);
                $expense_category->name = $input['name'];
                $expense_category->code = $input['code'];

                // Update default expense account
                $expense_category->default_expense_account_id = !empty($request->input('default_expense_account_id')) 
                    ? $request->input('default_expense_account_id') 
                    : null;

                if (! empty($request->input('add_as_sub_cat')) && $request->input('add_as_sub_cat') == 1 && ! empty($request->input('parent_id'))) {
                    $expense_category->parent_id = $request->input('parent_id');
                } else {
                    $expense_category->parent_id = null;
                }

                $expense_category->save();

                $output = ['success' => true,
                    'msg' => __('expense.updated_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $expense_category = ExpenseCategory::where('business_id', $business_id)->findOrFail($id);
                $expense_category->delete();

                //delete sub categories also
                ExpenseCategory::where('business_id', $business_id)->where('parent_id', $id)->delete();

                $output = ['success' => true,
                    'msg' => __('expense.deleted_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function getSubCategories(Request $request)
    {
        if (! empty($request->input('cat_id'))) {
            $category_id = $request->input('cat_id');
            $business_id = $request->session()->get('user.business_id');
            $sub_categories = ExpenseCategory::where('business_id', $business_id)
                        ->where('parent_id', $category_id)
                        ->select(['name', 'id'])
                        ->get();
        }

        $html = '<option value="">'.__('lang_v1.none').'</option>';
        if (! empty($sub_categories)) {
            foreach ($sub_categories as $sub_category) {
                $html .= '<option value="'.$sub_category->id.'">'.$sub_category->name.'</option>';
            }
        }
        echo $html;
        exit;
    }

    /**
     * Display diagnostic information for expense category mapping to P&L Bisnis.
     *
     * @return \Illuminate\Http\Response
     */
    public function diagnose()
    {
        if (! (auth()->user()->can('expense.add') || auth()->user()->can('expense.edit'))) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $categories = ExpenseCategory::where('business_id', $business_id)
            ->whereNull('parent_id')
            ->with('defaultExpenseAccount')
            ->orderBy('code')
            ->get();

        // Get unique pnl_groups for reference
        $existing_pnl_groups = ExpenseCategory::where('business_id', $business_id)
            ->whereNotNull('pnl_group')
            ->distinct()
            ->pluck('pnl_group')
            ->toArray();

        $diagnostics = [];
        foreach ($categories as $cat) {
            $status = 'danger';
            $message = 'P&L Group belum di-set. Expense akan masuk ke kategori "Other" di P&L Bisnis.';
            $pnl_group = null;

            if (! empty($cat->pnl_group)) {
                $pnl_group = $cat->pnl_group;
                $status = 'success';
                $message = 'Expense akan muncul di kategori "' . $pnl_group . '" di P&L Bisnis.';
            }

            // Account info (optional, for reference)
            $account_info = null;
            if ($cat->default_expense_account_id && $cat->defaultExpenseAccount) {
                $account = $cat->defaultExpenseAccount;
                $account_info = [
                    'gl_code' => $account->gl_code,
                    'name' => $account->name,
                ];
            }

            $diagnostics[] = [
                'category' => $cat,
                'status' => $status,
                'message' => $message,
                'pnl_group' => $pnl_group,
                'account_info' => $account_info,
            ];
        }

        // Summary statistics
        $summary = [
            'total_categories' => count($categories),
            'configured' => collect($diagnostics)->where('status', 'success')->count(),
            'not_configured' => collect($diagnostics)->where('status', 'danger')->count(),
            'pnl_groups' => $existing_pnl_groups,
        ];

        return view('expense_category.diagnose')->with(compact('diagnostics', 'summary'));
    }
}
