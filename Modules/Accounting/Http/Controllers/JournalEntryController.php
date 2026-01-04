<?php

namespace Modules\Accounting\Http\Controllers;

use App\Utils\ModuleUtil;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Entities\AccountingAccountsTransaction;
use Modules\Accounting\Entities\AccountingAccTransMapping;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Utils\AccountingUtil;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\IOFactory;

class JournalEntryController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $util;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(Util $util, ModuleUtil $moduleUtil, AccountingUtil $accountingUtil)
    {
        $this->util = $util;
        $this->moduleUtil = $moduleUtil;
        $this->accountingUtil = $accountingUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_journal'))) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            // Query 1: Manual Journal Entries (with acc_trans_mapping_id)
            $manualJournals = DB::table('accounting_accounts_transactions')
                ->join('accounting_acc_trans_mappings as map', 'accounting_accounts_transactions.acc_trans_mapping_id', '=', 'map.id')
                ->join('accounting_accounts as acc', 'accounting_accounts_transactions.accounting_account_id', '=', 'acc.id')
                ->where('map.business_id', $business_id)
                ->whereIn('map.type', ['journal_entry', 'transfer'])
                ->select([
                    'accounting_accounts_transactions.id',
                    'map.id as mapping_id',
                    'map.ref_no',
                    'accounting_accounts_transactions.operation_date',
                    'map.note as description',
                    'acc.gl_code',
                    'acc.name as account_name',
                    'accounting_accounts_transactions.amount',
                    'accounting_accounts_transactions.type',
                    'accounting_accounts_transactions.sub_type',
                    DB::raw("'manual' as source"),
                ]);

            // Query 2: Mapped Transactions (sell, purchase, expense, payment, gym_subscription)
            // These don't have acc_trans_mapping_id but have transaction_id or transaction_payment_id
            $mappedTransactions = DB::table('accounting_accounts_transactions')
                ->join('accounting_accounts as acc', 'accounting_accounts_transactions.accounting_account_id', '=', 'acc.id')
                ->leftJoin('transactions as t', 'accounting_accounts_transactions.transaction_id', '=', 't.id')
                ->leftJoin('transaction_payments as tp', 'accounting_accounts_transactions.transaction_payment_id', '=', 'tp.id')
                ->where(function($q) use ($business_id) {
                    $q->where('t.business_id', $business_id)
                      ->orWhere('tp.business_id', $business_id);
                })
                ->whereNull('accounting_accounts_transactions.acc_trans_mapping_id')
                ->whereIn('accounting_accounts_transactions.sub_type', ['sell', 'purchase', 'expense', 'sell_payment', 'purchase_payment', 'gym_subscription'])
                ->select([
                    'accounting_accounts_transactions.id',
                    DB::raw('NULL as mapping_id'),
                    DB::raw("COALESCE(t.invoice_no, t.ref_no, tp.payment_ref_no, CONCAT('TXN-', accounting_accounts_transactions.id)) as ref_no"),
                    'accounting_accounts_transactions.operation_date',
                    DB::raw("CONCAT(UPPER(REPLACE(accounting_accounts_transactions.sub_type, '_', ' ')), ' - ', accounting_accounts_transactions.map_type) as description"),
                    'acc.gl_code',
                    'acc.name as account_name',
                    'accounting_accounts_transactions.amount',
                    'accounting_accounts_transactions.type',
                    'accounting_accounts_transactions.sub_type',
                    DB::raw("'mapped' as source"),
                ]);

            // Apply date filter to both queries
            if (!empty(request()->start_date) && !empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $manualJournals->whereDate('accounting_accounts_transactions.operation_date', '>=', $start)
                      ->whereDate('accounting_accounts_transactions.operation_date', '<=', $end);
                $mappedTransactions->whereDate('accounting_accounts_transactions.operation_date', '>=', $start)
                      ->whereDate('accounting_accounts_transactions.operation_date', '<=', $end);
            }

            // Combine both queries using UNION
            $unionQuery = $manualJournals->union($mappedTransactions);
            
            // Wrap the union in a subquery for DataTables compatibility
            $query = DB::table(DB::raw("({$unionQuery->toSql()}) as combined"))
                ->mergeBindings($unionQuery);

            // Use Datatables::of() with the query - this will handle everything
            $datatable = Datatables::of($query);
            
            // Edit columns
            $datatable->editColumn('operation_date', function ($row) {
                return \Carbon\Carbon::parse($row->operation_date)->format('d M Y H:i');
            });
            
            // Add source badge column
            $datatable->editColumn('sub_type', function ($row) {
                $badges = [
                    'journal_entry' => '<span class="badge bg-primary">Manual</span>',
                    'transfer' => '<span class="badge bg-info">Transfer</span>',
                    'sell' => '<span class="badge bg-green">Sale</span>',
                    'purchase' => '<span class="badge bg-orange">Purchase</span>',
                    'expense' => '<span class="badge bg-red">Expense</span>',
                    'sell_payment' => '<span class="badge bg-teal">Sale Payment</span>',
                    'purchase_payment' => '<span class="badge bg-yellow">Purchase Payment</span>',
                    'gym_subscription' => '<span class="badge bg-purple">Gym</span>',
                ];
                return $badges[$row->sub_type] ?? '<span class="badge bg-gray">'.$row->sub_type.'</span>';
            });
            
            $datatable->addColumn('debit', function ($row) {
                if ($row->type == 'debit') {
                    return '<span class="display_currency tw-block" data-currency_symbol="false" data-orig-value="'.$row->amount.'">'.$row->amount.'</span>';
                }
                return '';
            });
            
            $datatable->addColumn('credit', function ($row) {
                if ($row->type == 'credit') {
                    return '<span class="display_currency tw-block" data-currency_symbol="false" data-orig-value="'.$row->amount.'">'.$row->amount.'</span>';
                }
                return '';
            });
            
            $datatable->addColumn('balance', function ($row) {
                return '<span class="display_currency tw-block" data-currency_symbol="false" data-orig-value="'.$row->amount.'">'.$row->amount.'</span>';
            });
            
            $datatable->addColumn('action', function ($row) {
                // For manual journal entries and transfers (have mapping_id)
                if (!empty($row->mapping_id)) {
                    $html = '<div class="btn-group">
                            <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                data-toggle="dropdown" aria-expanded="false">'.
                                __('messages.actions').
                                '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                </span>
                            </button>
                            <ul class="dropdown-menu" role="menu">';
                    if (auth()->user()->can('accounting.edit_journal')) {
                        $html .= '<li>
                            <a href="'.action([\Modules\Accounting\Http\Controllers\JournalEntryController::class, 'edit'], [$row->mapping_id]).'">
                                <i class="fas fa-edit"></i>'.__('messages.edit').'
                            </a>
                        </li>';
                    }
                    if (auth()->user()->can('accounting.delete_journal')) {
                        $html .= '<li>
                                <a href="#" data-href="'.action([\Modules\Accounting\Http\Controllers\JournalEntryController::class, 'destroy'], [$row->mapping_id]).'" class="delete_journal_button">
                                    <i class="fas fa-trash" aria-hidden="true"></i>'.__('messages.delete').'
                                </a>
                                </li>';
                    }
                    $html .= '</ul></div>';
                    return $html;
                }
                
                // For mapped transactions (no mapping_id) - show link to transactions page
                return '<a href="'.url('/accounting/transactions').'" class="btn btn-xs btn-default" title="'.__('accounting::lang.view_in_transactions').'">
                    <i class="fas fa-external-link-alt"></i> '.__('accounting::lang.transactions').'
                </a>';
            });
            
            // Global search filter for UNION query
            $datatable->filter(function ($query) {
                if (request()->has('search') && !empty(request()->search['value'])) {
                    $keyword = request()->search['value'];
                    $query->where(function ($q) use ($keyword) {
                        $q->where('ref_no', 'like', "%{$keyword}%")
                          ->orWhere('gl_code', 'like', "%{$keyword}%")
                          ->orWhere('account_name', 'like', "%{$keyword}%")
                          ->orWhere('description', 'like', "%{$keyword}%")
                          ->orWhere('sub_type', 'like', "%{$keyword}%");
                    });
                }
            });
            
            // Order by operation_date desc by default
            $datatable->orderColumn('operation_date', 'operation_date $1');
            $datatable->orderColumn('ref_no', 'ref_no $1');
            $datatable->orderColumn('gl_code', 'gl_code $1');
            $datatable->orderColumn('account_name', 'account_name $1');
            $datatable->orderColumn('description', 'description $1');
            
            $datatable->rawColumns(['sub_type', 'debit', 'credit', 'balance', 'action']);
            
            return $datatable->make(true);
        }

        return view('accounting::journal_entry.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.add_journal'))) {
            abort(403, 'Unauthorized action.');
        }

        return view('accounting::journal_entry.create');
    }

    /**
     * Import journal entries via Excel
     *
     * Expected columns: GL Date, GL Number, Account Number, Account Name, Description, Debit, Credit, Balance
     */
    public function import(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.add_journal'))) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'journal_import' => 'required|file|mimes:xls,xlsx,csv',
        ]);

        try {
            DB::beginTransaction();

            $user_id = $request->session()->get('user.id');
            $file = $request->file('journal_import');

            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();
            if ($highestRow < 2) {
                throw new \Exception(__('messages.no_data_found'));
            }

            $ref_count = $this->util->setAndGetReferenceCount('journal_entry');
            $accounting_settings = $this->accountingUtil->getAccountingSettings($business_id);

            $mappings = [];

            for ($row = 2; $row <= $highestRow; $row++) {
                $gl_date_raw = $sheet->getCellByColumnAndRow(1, $row)->getCalculatedValue();
                $gl_number = trim((string) $sheet->getCellByColumnAndRow(2, $row)->getCalculatedValue());
                $account_number = trim((string) $sheet->getCellByColumnAndRow(3, $row)->getCalculatedValue());
                $account_name = trim((string) $sheet->getCellByColumnAndRow(4, $row)->getCalculatedValue());
                $description = trim((string) $sheet->getCellByColumnAndRow(5, $row)->getCalculatedValue());
                $debit_raw = $sheet->getCellByColumnAndRow(6, $row)->getCalculatedValue();
                $credit_raw = $sheet->getCellByColumnAndRow(7, $row)->getCalculatedValue();

                if (empty($gl_date_raw) && empty($gl_number) && empty($account_number) && empty($account_name) && empty($debit_raw) && empty($credit_raw)) {
                    continue;
                }

                $debit = $this->util->num_uf($debit_raw ?: 0);
                $credit = $this->util->num_uf($credit_raw ?: 0);

                // parse date (Excel numeric or string)
                if (is_numeric($gl_date_raw)) {
                    $gl_date = Date::excelToDateTimeObject($gl_date_raw)->format('Y-m-d H:i:s');
                } else {
                    $gl_date = $this->util->uf_date($gl_date_raw, true);
                }

                // resolve account: prefer GL code (Account Number), fallback to name
                $accountQuery = AccountingAccount::where('business_id', $business_id);
                if (!empty($account_number)) {
                    $accountQuery->where('gl_code', $account_number);
                } elseif (!empty($account_name)) {
                    $accountQuery->where('name', $account_name);
                }
                $account = $accountQuery->first();

                if (empty($account)) {
                    throw new \Exception(__('accounting::lang.account').' '.$account_name.' '.__('lang_v1.not_found'));
                }

                $mapping_key = !empty($gl_number) ? $gl_number : 'AUTO-'.$row;

                if (!isset($mappings[$mapping_key])) {
                    $ref_no = $gl_number;
                    if (empty($ref_no)) {
                        $prefix = ! empty($accounting_settings['journal_entry_prefix']) ? $accounting_settings['journal_entry_prefix'] : '';
                        $ref_no = $this->util->generateReferenceNumber('journal_entry', $ref_count++, $business_id, $prefix);
                    }

                    $mapping = new AccountingAccTransMapping();
                    $mapping->business_id = $business_id;
                    $mapping->ref_no = $ref_no;
                    $mapping->note = $description;
                    $mapping->type = 'journal_entry';
                    $mapping->created_by = $user_id;
                    $mapping->operation_date = $gl_date;
                    $mapping->save();

                    $mappings[$mapping_key] = $mapping->id;
                }

                $transaction_row = [
                    'accounting_account_id' => $account->id,
                    'amount' => $debit > 0 ? $debit : $credit,
                    'type' => $debit > 0 ? 'debit' : 'credit',
                    'created_by' => $user_id,
                    'operation_date' => $gl_date,
                    'sub_type' => 'journal_entry',
                    'acc_trans_mapping_id' => $mappings[$mapping_key],
                    'note' => $description,
                ];

                $accounts_transactions = new AccountingAccountsTransaction();
                $accounts_transactions->fill($transaction_row);
                $accounts_transactions->save();
            }

            DB::commit();

            $output = ['success' => 1, 'msg' => __('accounting::lang.journal_import_success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . 'Line:' . $e->getLine() . 'Message:' . $e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];
        }

        return redirect()->back()->with(['status' => $output]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.add_journal'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $user_id = request()->session()->get('user.id');

            $account_ids = $request->get('account_id');
            $credits = $request->get('credit');
            $debits = $request->get('debit');
            $journal_date = $request->get('journal_date');

            $accounting_settings = $this->accountingUtil->getAccountingSettings($business_id);

            $ref_no = $request->get('ref_no');
            $ref_count = $this->util->setAndGetReferenceCount('journal_entry');
            if (empty($ref_no)) {
                $prefix = ! empty($accounting_settings['journal_entry_prefix']) ?
                $accounting_settings['journal_entry_prefix'] : '';

                //Generate reference number
                $ref_no = $this->util->generateReferenceNumber('journal_entry', $ref_count, $business_id, $prefix);
            }

            $acc_trans_mapping = new AccountingAccTransMapping();
            $acc_trans_mapping->business_id = $business_id;
            $acc_trans_mapping->ref_no = $ref_no;
            $acc_trans_mapping->note = $request->get('note');
            $acc_trans_mapping->type = 'journal_entry';
            $acc_trans_mapping->created_by = $user_id;
            $acc_trans_mapping->operation_date = $this->util->uf_date($journal_date, true);
            $acc_trans_mapping->save();

            //save details in account trnsactions table
            foreach ($account_ids as $index => $account_id) {
                if (! empty($account_id)) {
                    $transaction_row = [];
                    $transaction_row['accounting_account_id'] = $account_id;

                    if (! empty($credits[$index])) {
                        $transaction_row['amount'] = $credits[$index];
                        $transaction_row['type'] = 'credit';
                    }

                    if (! empty($debits[$index])) {
                        $transaction_row['amount'] = $debits[$index];
                        $transaction_row['type'] = 'debit';
                    }

                    $transaction_row['created_by'] = $user_id;
                    $transaction_row['operation_date'] = $this->util->uf_date($journal_date, true);
                    $transaction_row['sub_type'] = 'journal_entry';
                    $transaction_row['acc_trans_mapping_id'] = $acc_trans_mapping->id;
                    $transaction_row['note'] = $request->get('note');

                    $accounts_transactions = new AccountingAccountsTransaction();
                    $accounts_transactions->fill($transaction_row);
                    $accounts_transactions->save();
                }
            }

            DB::commit();

            $output = ['success' => 1,
                'msg' => __('lang_v1.added_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->route('journal-entry.index')->with('status', $output);
    }

    /**
     * Show the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.view_journal'))) {
            abort(403, 'Unauthorized action.');
        }

        return view('accounting::journal_entry.show');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.edit_journal'))) {
            abort(403, 'Unauthorized action.');
        }

        $journal = AccountingAccTransMapping::where('business_id', $business_id)
                    ->where('type', 'journal_entry')
                    ->where('id', $id)
                    ->firstOrFail();
        $accounts_transactions = AccountingAccountsTransaction::with('account')
                                    ->where('acc_trans_mapping_id', $id)
                                    ->get()->toArray();

        return view('accounting::journal_entry.edit')
            ->with(compact('journal', 'accounts_transactions'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.edit_journal'))) {
            abort(403, 'Unauthorized action.');
        }

        try {
            DB::beginTransaction();

            $user_id = request()->session()->get('user.id');

            $account_ids = $request->get('account_id');
            $accounts_transactions_id = $request->get('accounts_transactions_id');
            $credits = $request->get('credit');
            $debits = $request->get('debit');
            $journal_date = $request->get('journal_date');

            $acc_trans_mapping = AccountingAccTransMapping::where('business_id', $business_id)
                        ->where('type', 'journal_entry')
                        ->where('id', $id)
                        ->firstOrFail();
            $acc_trans_mapping->note = $request->get('note');
            $acc_trans_mapping->operation_date = $this->util->uf_date($journal_date, true);
            $acc_trans_mapping->update();

            //save details in account trnsactions table
            foreach ($account_ids as $index => $account_id) {
                if (! empty($account_id)) {
                    $transaction_row = [];
                    $transaction_row['accounting_account_id'] = $account_id;

                    if (! empty($credits[$index])) {
                        $transaction_row['amount'] = $credits[$index];
                        $transaction_row['type'] = 'credit';
                    }

                    if (! empty($debits[$index])) {
                        $transaction_row['amount'] = $debits[$index];
                        $transaction_row['type'] = 'debit';
                    }

                    $transaction_row['created_by'] = $user_id;
                    $transaction_row['operation_date'] = $this->util->uf_date($journal_date, true);
                    $transaction_row['sub_type'] = 'journal_entry';
                    $transaction_row['acc_trans_mapping_id'] = $acc_trans_mapping->id;

                    if (! empty($accounts_transactions_id[$index])) {
                        $accounts_transactions = AccountingAccountsTransaction::find($accounts_transactions_id[$index]);
                        $accounts_transactions->fill($transaction_row);
                        $accounts_transactions->update();
                    } else {
                        $accounts_transactions = new AccountingAccountsTransaction();
                        $accounts_transactions->fill($transaction_row);
                        $accounts_transactions->save();
                    }
                } elseif (! empty($accounts_transactions_id[$index])) {
                    AccountingAccountsTransaction::delete($accounts_transactions_id[$index]);
                }
            }

            $output = ['success' => 1,
                'msg' => __('lang_v1.updated_success'),
            ];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            print_r($e->getMessage());
            exit;
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return redirect()->route('journal-entry.index')->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (! (auth()->user()->can('superadmin') ||
            $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            ! (auth()->user()->can('accounting.delete_journal'))) {
            abort(403, 'Unauthorized action.');
        }

        $user_id = request()->session()->get('user.id');

        $acc_trans_mapping = AccountingAccTransMapping::where('id', $id)
                        ->where('business_id', $business_id)->firstOrFail();

        if (! empty($acc_trans_mapping)) {
            $acc_trans_mapping->delete();
            AccountingAccountsTransaction::where('acc_trans_mapping_id', $id)->delete();
        }

        return ['success' => 1,
            'msg' => __('lang_v1.deleted_success'),
        ];
    }
}


