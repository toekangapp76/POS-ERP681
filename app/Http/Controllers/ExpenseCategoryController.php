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
                        ->with('defaultExpenseAccount.detail_type')
                        ->select(['name', 'code', 'id', 'parent_id', 'default_expense_account_id']);

            return Datatables::of($expense_category)
                ->addColumn(
                    'action',
                    '<button data-href="{{action(\'App\Http\Controllers\ExpenseCategoryController@edit\', [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".expense_category_modal"><i class="glyphicon glyphicon-edit"></i>  @lang("messages.edit")</button>
                        &nbsp;
                        <button data-href="{{action(\'App\Http\Controllers\ExpenseCategoryController@destroy\', [$id])}}" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_expense_category"><i class="glyphicon glyphicon-trash"></i> @lang("messages.delete")</button>'
                )
                ->addColumn('default_account', function ($row) {
                    if (!empty($row->defaultExpenseAccount)) {
                        $account = $row->defaultExpenseAccount;
                        $detail_type = $account->detail_type ? ' <span class="label label-info">' . $account->detail_type->name . '</span>' : '';
                        return $account->gl_code . ' - ' . $account->name . $detail_type;
                    }
                    return '<span class="text-muted">-</span>';
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
                ->removeColumn('default_expense_account_id')
                ->removeColumn('defaultExpenseAccount')
                ->removeColumn('default_expense_account')
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

        $expense_accounts = [];
        if (class_exists('\Modules\Accounting\Entities\AccountingAccount')) {
            $expense_accounts = \Modules\Accounting\Entities\AccountingAccount::where('business_id', $business_id)
                ->where('status', 'active')
                ->whereIn('account_primary_type', ['expenses', 'cost_of_sale', 'other_expense'])
                ->with('detail_type')
                ->orderBy('gl_code')
                ->get()
                ->mapWithKeys(function ($acc) {
                    $detail_type_label = $acc->detail_type ? ' [' . $acc->detail_type->name . ']' : '';
                    return [$acc->id => $acc->gl_code . ' - ' . $acc->name . $detail_type_label];
                });
        }

        return view('expense_category.create')->with(compact('categories', 'expense_accounts'));
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

            $expense_accounts = [];
            if (class_exists('\Modules\Accounting\Entities\AccountingAccount')) {
                $expense_accounts = \Modules\Accounting\Entities\AccountingAccount::where('business_id', $business_id)
                    ->where('status', 'active')
                    ->whereIn('account_primary_type', ['expenses', 'cost_of_sale', 'other_expense'])
                    ->with('detail_type')
                    ->orderBy('gl_code')
                    ->get()
                    ->mapWithKeys(function ($acc) {
                        $detail_type_label = $acc->detail_type ? ' [' . $acc->detail_type->name . ']' : '';
                        return [$acc->id => $acc->gl_code . ' - ' . $acc->name . $detail_type_label];
                    });
            }

            return view('expense_category.edit')
                    ->with(compact('expense_category', 'categories', 'expense_accounts'));
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
     * Display diagnostic information for expense category mapping.
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
            ->with('defaultExpenseAccount.detail_type')
            ->get();

        $detail_types = [];
        if (class_exists('\Modules\Accounting\Entities\AccountingAccountType')) {
            $detail_types = \Modules\Accounting\Entities\AccountingAccountType::where('account_type', 'detail_type')
                ->orderBy('name')
                ->pluck('name', 'id');
        }

        $diagnostics = [];
        foreach ($categories as $cat) {
            $status = 'warning';
            $message = 'Default Expense Account belum di-set';
            $account_info = null;
            $detail_type_info = null;

            if ($cat->default_expense_account_id && $cat->defaultExpenseAccount) {
                $account = $cat->defaultExpenseAccount;
                $account_info = [
                    'gl_code' => $account->gl_code,
                    'name' => $account->name,
                ];
                
                if ($account->detail_type) {
                    $detail_type_info = $account->detail_type->name;
                    $status = 'success';
                    $message = 'Konfigurasi sudah benar. Expense akan muncul di kategori "' . $account->detail_type->name . '" di P&L Bisnis.';
                } else {
                    $status = 'danger';
                    $message = 'Akun "' . $account->name . '" tidak memiliki Detail Type. Expense tidak akan ter-grouping dengan benar di P&L Bisnis.';
                }
            }

            $diagnostics[] = [
                'category' => $cat,
                'status' => $status,
                'message' => $message,
                'account_info' => $account_info,
                'detail_type' => $detail_type_info,
            ];
        }

        return view('expense_category.diagnose')->with(compact('diagnostics', 'detail_types'));
    }
}
