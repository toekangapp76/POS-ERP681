<?php

namespace Modules\Accounting\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Entities\AccountingAccountType;
use Modules\Accounting\Utils\AccountingUtil;
use Modules\Gym\Entities\GymCategory;

class ReportController extends Controller
{
    protected $accountingUtil;

    protected $businessUtil;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct(
        AccountingUtil $accountingUtil,
        BusinessUtil $businessUtil,
        ModuleUtil $moduleUtil
    ) {
        $this->accountingUtil = $accountingUtil;
        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        if (
            !(auth()->user()->can('superadmin') ||
                $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            !(auth()->user()->can('accounting.view_reports'))
        ) {
            abort(403, 'Unauthorized action.');
        }

        $first_account = AccountingAccount::where('business_id', $business_id)
            ->where('status', 'active')
            ->first();
        $ledger_url = null;
        if (!empty($first_account)) {
            $ledger_url = route('accounting.ledger', $first_account);
        }
        $journal_entry_url = null;
        if (!empty($first_account)) {
            $journal_entry_url = route('journal-entry.index', $first_account);
        }

        return view('accounting::report.index')
            ->with(compact('ledger_url', 'journal_entry_url'));
    }

    /**
     * Trial Balance
     *
     * @return Response
     */
    public function trialBalance()
    {
        $business_id = request()->session()->get('user.business_id');

        if (
            !(auth()->user()->can('superadmin') ||
                $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            !(auth()->user()->can('accounting.view_reports'))
        ) {
            abort(403, 'Unauthorized action.');
        }

        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start_date = request()->start_date;
            $end_date = request()->end_date;
        } elseif (!empty(request()->month)) {
            $month = request()->month; // Format: YYYY-MM
            $start_date = $month . '-01';
            $end_date = date('Y-m-t', strtotime($start_date)); // Last day of month
        } else {
            // Default to current month
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-t');
        }

        // Calculate beginning balance date (end of previous day)
        $beginning_balance_date = date('Y-m-d', strtotime($start_date . ' -1 day'));

        // Get all active accounts
        $all_accounts = AccountingAccount::where('business_id', $business_id)
            ->select('id', 'name', 'gl_code', 'account_primary_type')
            ->orderBy('gl_code')
            ->get();

        $accounts = collect();
        $opening_balance_formula = "SUM(IF(AAT.type = 'debit', AAT.amount, 0)) - SUM(IF(AAT.type = 'credit', AAT.amount, 0)) as balance";

        $pl_accounts = AccountingAccount::where('business_id', $business_id)
            ->whereNotNull('gl_code')
            ->where('gl_code', '!=', '')
            ->whereRaw("CAST(SUBSTRING(gl_code, 1, 1) AS UNSIGNED) >= 4")
            ->select('id', 'account_primary_type')
            ->get();

        $business_start = Business::where('id', $business_id)->value('start_date');
        $selected_end = \Carbon\Carbon::parse($end_date);
        if (!empty($business_start)) {
            $business_start = \Carbon\Carbon::parse($business_start)->startOfDay();
            if ($business_start->gt($selected_end)) {
                $base_start_date = $selected_end->copy()->startOfMonth()->format('Y-m-d');
            } else {
                $base_start_date = $business_start->format('Y-m-d');
            }
        } else {
            $base_start_date = $selected_end->copy()->startOfYear()->format('Y-m-d');
        }

        $period_start = $start_date;
        if (\Carbon\Carbon::parse($period_start)->lt(\Carbon\Carbon::parse($base_start_date))) {
            $period_start = $base_start_date;
        }

        $opening_profit_end = \Carbon\Carbon::parse($period_start)->subDay()->format('Y-m-d');

        $calculate_net_profit = function ($from_date, $to_date) use ($pl_accounts) {
            if (empty($from_date) || empty($to_date)) {
                return 0;
            }

            if (\Carbon\Carbon::parse($from_date)->gt(\Carbon\Carbon::parse($to_date))) {
                return 0;
            }

            $income = 0;
            $expense = 0;
            foreach ($pl_accounts as $pl_account) {
                $pl_balance = DB::table('accounting_accounts_transactions')
                    ->where('accounting_account_id', $pl_account->id)
                    ->where(function ($q) {
                        $q->whereNull('sub_type')
                            ->orWhere('sub_type', '!=', 'opening_balance');
                    })
                    ->whereDate('operation_date', '>=', $from_date)
                    ->whereDate('operation_date', '<=', $to_date)
                    ->select(
                        DB::raw("COALESCE(SUM(IF(type = 'debit', amount, 0)), 0) as debit"),
                        DB::raw("COALESCE(SUM(IF(type = 'credit', amount, 0)), 0) as credit")
                    )
                    ->first();

                $debit = $pl_balance->debit ?? 0;
                $credit = $pl_balance->credit ?? 0;

                if ($pl_account->account_primary_type == 'income') {
                    $income += ($credit - $debit);
                } else {
                    $expense += ($debit - $credit);
                }
            }

            return $income - $expense;
        };

        $re_opening_balance = $calculate_net_profit($base_start_date, $opening_profit_end);
        $re_period_profit = $calculate_net_profit($period_start, $end_date);
        $re_ending_balance = $re_opening_balance + $re_period_profit;
        $re_debit_balance = $re_period_profit < 0 ? abs($re_period_profit) : 0;
        $re_credit_balance = $re_period_profit > 0 ? $re_period_profit : 0;

        // Determine if start_date is at or before the fiscal year start
        // If start_date <= fiscal year start, beginning balance should be 0
        $fiscal_year_start = $base_start_date;
        $is_start_of_year = \Carbon\Carbon::parse($start_date)->lte(\Carbon\Carbon::parse($fiscal_year_start));

        foreach ($all_accounts as $account) {
            // Calculate beginning balance (all transactions up to the day before start_date)
            // But only count transactions from fiscal year start onwards for P&L accounts
            // For balance sheet accounts (asset, liability, equity), include all historical transactions
            $beginning_balance = 0;

            if (!$is_start_of_year) {
                // Only calculate beginning balance if we're not at the start of fiscal year
                $gl_code_first = !empty($account->gl_code) ? substr($account->gl_code, 0, 1) : '0';
                $is_pl_account = is_numeric($gl_code_first) && (int) $gl_code_first >= 4;

                if ($is_pl_account) {
                    // For P&L accounts (gl_code >= 4), beginning balance is from fiscal year start to day before selected start_date
                    // This represents YTD balance before the selected period
                    $beginning = DB::table('accounting_accounts_transactions as AAT')
                        ->join('accounting_accounts as AA', 'AAT.accounting_account_id', '=', 'AA.id')
                        ->where('AAT.accounting_account_id', $account->id)
                        ->whereDate('AAT.operation_date', '>=', $fiscal_year_start)
                        ->where(function ($q) use ($beginning_balance_date, $start_date) {
                            $q->whereDate('AAT.operation_date', '<=', $beginning_balance_date)
                                ->orWhere(function ($q2) use ($start_date) {
                                    $q2->where('AAT.sub_type', 'opening_balance')
                                        ->whereDate('AAT.operation_date', '<=', $start_date);
                                });
                        })
                        ->select(DB::raw($opening_balance_formula))
                        ->first();
                    $beginning_balance = $beginning->balance ?? 0;
                } else {
                    // For balance sheet accounts, include ALL historical transactions
                    $beginning = DB::table('accounting_accounts_transactions as AAT')
                        ->join('accounting_accounts as AA', 'AAT.accounting_account_id', '=', 'AA.id')
                        ->where('AAT.accounting_account_id', $account->id)
                        ->where(function ($q) use ($beginning_balance_date, $start_date) {
                            $q->whereDate('AAT.operation_date', '<=', $beginning_balance_date)
                                ->orWhere(function ($q2) use ($start_date) {
                                    $q2->where('AAT.sub_type', 'opening_balance')
                                        ->whereDate('AAT.operation_date', '<=', $start_date);
                                });
                        })
                        ->select(DB::raw($opening_balance_formula))
                        ->first();
                    $beginning_balance = $beginning->balance ?? 0;
                }
            } else {
                // At start of fiscal year - P&L accounts have 0 beginning balance
                // Balance sheet accounts still have their full historical balance
                $gl_code_first = !empty($account->gl_code) ? substr($account->gl_code, 0, 1) : '0';
                $is_pl_account = is_numeric($gl_code_first) && (int) $gl_code_first >= 4;

                if (!$is_pl_account) {
                    // Balance sheet accounts keep their historical balance
                    $beginning = DB::table('accounting_accounts_transactions as AAT')
                        ->join('accounting_accounts as AA', 'AAT.accounting_account_id', '=', 'AA.id')
                        ->where('AAT.accounting_account_id', $account->id)
                        ->where(function ($q) use ($beginning_balance_date, $start_date) {
                            $q->whereDate('AAT.operation_date', '<=', $beginning_balance_date)
                                ->orWhere(function ($q2) use ($start_date) {
                                    $q2->where('AAT.sub_type', 'opening_balance')
                                        ->whereDate('AAT.operation_date', '<=', $start_date);
                                });
                        })
                        ->select(DB::raw($opening_balance_formula))
                        ->first();
                    $beginning_balance = $beginning->balance ?? 0;
                }
                // P&L accounts have 0 beginning balance at start of fiscal year
            }

            // Calculate period transactions (debit and credit in the date range)
            $period = DB::table('accounting_accounts_transactions as AAT')
                ->where('AAT.accounting_account_id', $account->id)
                ->where(function ($q) {
                    $q->whereNull('AAT.sub_type')
                        ->orWhere('AAT.sub_type', '!=', 'opening_balance');
                })
                ->whereDate('AAT.operation_date', '>=', $start_date)
                ->whereDate('AAT.operation_date', '<=', $end_date)
                ->select(
                    DB::raw("SUM(IF(type = 'debit', amount, 0)) as debit"),
                    DB::raw("SUM(IF(type = 'credit', amount, 0)) as credit")
                )
                ->first();

            $debit_balance = $period->debit ?? 0;
            $credit_balance = $period->credit ?? 0;
            $ending_balance = $beginning_balance + $debit_balance - $credit_balance;

            // Include ALL accounts (including those with zero balance)
            $accounts->push((object) [
                'id' => $account->id,
                'name' => $account->name,
                'gl_code' => $account->gl_code,
                'beginning_balance' => $beginning_balance,
                'debit_balance' => $debit_balance,
                'credit_balance' => $credit_balance,
                'ending_balance' => $ending_balance,
            ]);
        }

        // Recalculate R/E opening balance based on whether we're at start of fiscal year
        if ($is_start_of_year) {
            $re_opening_balance = 0;
        }

        $re_index = $accounts->search(function ($row) {
            return !empty($row->gl_code) && $row->gl_code === '3202-0000';
        });

        // R/E Current Year di-nol-kan sesuai permintaan
        $re_row = (object) [
            'id' => null,
            'name' => 'R/E Current Year (Net Profit/Loss)',
            'gl_code' => '3202-0000',
            'beginning_balance' => 0,
            'debit_balance' => 0,
            'credit_balance' => 0,
            'ending_balance' => 0,
        ];
        // $re_row = (object) [
        //     'id' => null,
        //     'name' => 'R/E Current Year (Net Profit/Loss)',
        //     'gl_code' => '3202-0000',
        //     'beginning_balance' => $re_opening_balance,
        //     'debit_balance' => $re_debit_balance,
        //     'credit_balance' => $re_credit_balance,
        //     'ending_balance' => $re_ending_balance,
        // ];

        if ($re_index !== false) {
            $accounts[$re_index] = $re_row;
        } else {
            $accounts->push($re_row);
        }

        $accounts = $accounts->sortBy(function ($row) {
            return $row->gl_code ?? '';
        })->values();

        return view('accounting::report.trial_balance')
            ->with(compact('accounts', 'start_date', 'end_date'));
    }

    /**
     * Balance Sheet
     *
     * @return Response
     */
    public function balanceSheet()
    {
        $business_id = request()->session()->get('user.business_id');

        if (
            !(auth()->user()->can('superadmin') ||
                $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            !(auth()->user()->can('accounting.view_reports'))
        ) {
            abort(403, 'Unauthorized action.');
        }

        if (!empty(request()->end_date)) {
            $selected_end = \Carbon\Carbon::parse(request()->end_date)->endOfMonth();
            $requested_start = request()->input('start_date');
            $start_candidate = !empty($requested_start)
                ? \Carbon\Carbon::parse($requested_start)->startOfDay()
                : $selected_end->copy()->startOfYear();

            $business_start = Business::where('id', $business_id)->value('start_date');
            if (!empty($business_start)) {
                $business_start = \Carbon\Carbon::parse($business_start)->startOfDay();
                if ($business_start->greaterThan($selected_end)) {
                    $start_candidate = $selected_end->copy()->startOfMonth();
                } elseif ($business_start->greaterThan($start_candidate)) {
                    $start_candidate = $business_start;
                }
            }

            if ($start_candidate->greaterThan($selected_end)) {
                $start_candidate = $selected_end->copy()->startOfMonth();
            }

            $start_date = $start_candidate->format('Y-m-d');
            $end_date = $selected_end->format('Y-m-d');
        } else {
            $fy = $this->businessUtil->getCurrentFinancialYear($business_id);
            $start_date = $fy['start'];
            $end_date = $fy['end'];
        }

        // Get the first transaction year for year filter
        $first_transaction_date = DB::table('accounting_accounts_transactions')
            ->join('accounting_accounts', 'accounting_accounts_transactions.accounting_account_id', '=', 'accounting_accounts.id')
            ->where('accounting_accounts.business_id', $business_id)
            ->min('accounting_accounts_transactions.operation_date');
        
        $first_transaction_year = $first_transaction_date 
            ? (int) \Carbon\Carbon::parse($first_transaction_date)->format('Y')
            : (int) \Carbon\Carbon::now()->format('Y');
        
        // Year filter options: from first transaction year to 2 years from now
        $current_year = (int) \Carbon\Carbon::now()->format('Y');
        $year_filter_end = $current_year + 2;
        
        $year_filter_options = [];
        for ($y = $first_transaction_year; $y <= $year_filter_end; $y++) {
            $year_filter_options[$y] = $y;
        }

        // Generate list of months in the date range
        $months = [];
        
        $start_for_months = \Carbon\Carbon::parse($start_date)->startOfMonth()->subMonth();
        $current = $start_for_months->copy();
        $end = \Carbon\Carbon::parse($end_date)->endOfMonth();

        while ($current <= $end) {
            $months[] = [
                'key' => $current->format('Y-m'),
                'label' => $current->translatedFormat('M Y'),
                'start' => $current->copy()->startOfMonth()->format('Y-m-d'),
                'end' => $current->copy()->endOfMonth()->format('Y-m-d'),
            ];
            $current->addMonth();
        }

        // Get base account info
        $base_accounts = AccountingAccount::leftJoin(
            'accounting_account_types as AATP',
            'AATP.id',
            '=',
            'accounting_accounts.account_sub_type_id'
        )
            ->where('accounting_accounts.business_id', $business_id)
            ->where('accounting_accounts.status', 'active')
            ->whereIn('accounting_accounts.account_primary_type', ['asset', 'liability', 'equity'])
            ->select(
                'accounting_accounts.id',
                'accounting_accounts.name',
                'accounting_accounts.gl_code',
                'accounting_accounts.account_primary_type',
                'AATP.name as sub_type'
            )
            ->orderBy('accounting_accounts.gl_code')
            ->get();

        // Kumulatif all
        
        $all_accounts = [];
        foreach ($base_accounts as $account) {
            $account_data = [
                'id' => $account->id,
                'name' => $account->name,
                'gl_code' => $account->gl_code,
                'account_primary_type' => $account->account_primary_type,
                'sub_type' => $account->sub_type,
                'monthly_balances' => [],
                'balance' => 0,
            ];

            $has_any_balance = false;

            foreach ($months as $month) {
                // Hitung kumulatid semua bulan
                $balance = DB::table('accounting_accounts_transactions')
                    ->where('accounting_account_id', $account->id)
                    ->whereDate('operation_date', '<=', $month['end']) // From beginning of time to end of month
                    ->select(
                        DB::raw("COALESCE(SUM(IF(type = 'debit', amount, 0)), 0) as debit"),
                        DB::raw("COALESCE(SUM(IF(type = 'credit', amount, 0)), 0) as credit")
                    )
                    ->first();

                $debit = $balance->debit ?? 0;
                $credit = $balance->credit ?? 0;

                // Assets: Debit - Credit (debit increases assets)
                // Liabilities & Equity: Credit - Debit (credit increases these accounts)
                if ($account->account_primary_type == 'asset') {
                    $monthly_balance = $debit - $credit;
                } else {
                    // liability and equity
                    $monthly_balance = $credit - $debit;
                }

                $account_data['monthly_balances'][$month['key']] = $monthly_balance;
                
                $account_data['balance'] = $monthly_balance;

                if ($monthly_balance != 0) {
                    $has_any_balance = true;
                }
            }

            // Only include accounts that have at least some balance
            if ($has_any_balance || $account_data['balance'] != 0) {
                $all_accounts[] = (object) $account_data;
            }
        }

        $all_accounts = collect($all_accounts);

        // Calculate Net Profit/Loss for R/E Current Year (from P&L accounts - gl_code >= 4)
        // R/E Current Year = Cumulative net profit from all time to end of each month
        $pl_accounts = AccountingAccount::where('business_id', $business_id)
            ->where('status', 'active')
            ->whereNotNull('gl_code')
            ->where('gl_code', '!=', '')
            ->whereRaw("CAST(SUBSTRING(gl_code, 1, 1) AS UNSIGNED) >= 4")
            ->select('id', 'account_primary_type')
            ->get();

        $net_profit_monthly = [];
        $total_net_profit = 0;

        foreach ($months as $month) {
            // Calculate CUMULATIVE net profit from ALL TIME up to end of this month
            $cumulative_income = 0;
            $cumulative_expense = 0;

            foreach ($pl_accounts as $pl_account) {
                $pl_balance = DB::table('accounting_accounts_transactions')
                    ->where('accounting_account_id', $pl_account->id)
                    ->where(function ($q) {
                        $q->whereNull('sub_type')
                            ->orWhere('sub_type', '!=', 'opening_balance');
                    })
                    ->whereDate('operation_date', '<=', $month['end']) // From beginning to end of month
                    ->select(
                        DB::raw("COALESCE(SUM(IF(type = 'debit', amount, 0)), 0) as debit"),
                        DB::raw("COALESCE(SUM(IF(type = 'credit', amount, 0)), 0) as credit")
                    )
                    ->first();

                $debit = $pl_balance->debit ?? 0;
                $credit = $pl_balance->credit ?? 0;

                if ($pl_account->account_primary_type == 'income') {
                    $cumulative_income += ($credit - $debit);
                } else {
                    $cumulative_expense += ($debit - $credit);
                }
            }

            // Net profit = cumulative income - cumulative expense
            $net_profit_monthly[$month['key']] = $cumulative_income - $cumulative_expense;
            $total_net_profit = $net_profit_monthly[$month['key']]; // Last value is total
        }

        // R/E Current Year account info (GL Code: 3202-0000)
        // monthly_balances now contains CUMULATIVE net profit up to each month
        $re_current_year = (object) [
            'id' => null,
            'name' => 'R/E Current Year (Net Profit/Loss)',
            'gl_code' => '3202-0000',
            'account_primary_type' => 'equity',
            'sub_type' => 'Retained Earnings',
            'monthly_balances' => $net_profit_monthly,
            'balance' => $total_net_profit,
            'is_calculated' => true, // Flag to identify this is calculated, not from DB
        ];

        return view('accounting::report.balance_sheet')
            ->with(compact('all_accounts', 'start_date', 'end_date', 'months', 're_current_year', 'net_profit_monthly', 'total_net_profit', 'year_filter_options'));
    }

    public function accountReceivableAgeingReport()
    {
        $business_id = request()->session()->get('user.business_id');

        if (
            !(auth()->user()->can('superadmin') ||
                $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            !(auth()->user()->can('accounting.view_reports'))
        ) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = request()->input('location_id', null);

        $report_details = $this->accountingUtil->getAgeingReport($business_id, 'sell', 'contact', $location_id);

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.account_receivable_ageing_report')
            ->with(compact('report_details', 'business_locations'));
    }

    public function accountPayableAgeingReport()
    {
        $business_id = request()->session()->get('user.business_id');

        if (
            !(auth()->user()->can('superadmin') ||
                $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            !(auth()->user()->can('accounting.view_reports'))
        ) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = request()->input('location_id', null);
        $report_details = $this->accountingUtil->getAgeingReport(
            $business_id,
            'purchase',
            'contact',
            $location_id
        );
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.account_payable_ageing_report')
            ->with(compact('report_details', 'business_locations'));
    }

    public function accountReceivableAgeingDetails()
    {
        $business_id = request()->session()->get('user.business_id');

        if (
            !(auth()->user()->can('superadmin') ||
                $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            !(auth()->user()->can('accounting.view_reports'))
        ) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = request()->input('location_id', null);

        $report_details = $this->accountingUtil->getAgeingReport(
            $business_id,
            'sell',
            'due_date',
            $location_id
        );

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.account_receivable_ageing_details')
            ->with(compact('business_locations', 'report_details'));
    }

    public function accountPayableAgeingDetails()
    {
        $business_id = request()->session()->get('user.business_id');

        if (
            !(auth()->user()->can('superadmin') ||
                $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            !(auth()->user()->can('accounting.view_reports'))
        ) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = request()->input('location_id', null);

        $report_details = $this->accountingUtil->getAgeingReport(
            $business_id,
            'purchase',
            'due_date',
            $location_id
        );

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('accounting::report.account_payable_ageing_details')
            ->with(compact('business_locations', 'report_details'));
    }

    /**
     * Profit and Loss Report
     * Shows accounts with GL Code starting from 4 and above (Income & Expenses)
     *
     * @return Response
     */
    public function profitLoss()
    {
        $business_id = request()->session()->get('user.business_id');

        if (
            !(auth()->user()->can('superadmin') ||
                $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            !(auth()->user()->can('accounting.view_reports'))
        ) {
            abort(403, 'Unauthorized action.');
        }

        // Detail Type filter
        $detail_type_id = request()->input('detail_type_id', null);
        $detail_types = AccountingAccountType::where('account_type', 'detail_type')
            ->where(function ($q) use ($business_id) {
                $q->whereNull('business_id')
                    ->orWhere('business_id', $business_id);
            })
            ->get()
            ->mapWithKeys(function ($item) {
                $name = !empty($item->business_id) ? $item->name : __('accounting::lang.' . $item->name);
                return [$item->id => $name];
            })
            ->toArray();

        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start_date = request()->start_date;
            $end_date = request()->end_date;
        } else {
            $fy = $this->businessUtil->getCurrentFinancialYear($business_id);
            $start_date = $fy['start'];
            $end_date = $fy['end'];
        }

        // Generate list of months in the date range
        $months = [];
        $current = \Carbon\Carbon::parse($start_date)->startOfMonth();
        $end = \Carbon\Carbon::parse($end_date)->endOfMonth();

        while ($current <= $end) {
            $months[] = [
                'key' => $current->format('Y-m'),
                'label' => $current->translatedFormat('M Y'),
                'start' => $current->copy()->startOfMonth()->format('Y-m-d'),
                'end' => $current->copy()->endOfMonth()->format('Y-m-d'),
            ];
            $current->addMonth();
        }

        // Initialize monthly totals
        $monthly_totals = [
            'income' => [],
            'expense' => [],
            'net_profit' => [],
        ];
        foreach ($months as $month) {
            $monthly_totals['income'][$month['key']] = 0;
            $monthly_totals['expense'][$month['key']] = 0;
            $monthly_totals['net_profit'][$month['key']] = 0;
        }

        // Get all active accounts with gl_code starting from 4 (Income, Expenses, etc)
        $accountQuery = AccountingAccount::where('business_id', $business_id)
            ->where('status', 'active')
            ->whereNotNull('gl_code')
            ->where('gl_code', '!=', '')
            ->whereRaw("CAST(SUBSTRING(gl_code, 1, 1) AS UNSIGNED) >= 4");

        // Apply detail type filter if selected
        if (!empty($detail_type_id)) {
            $accountQuery->where('detail_type_id', $detail_type_id);
        }

        $all_accounts = $accountQuery
            ->select('id', 'name', 'gl_code', 'account_primary_type', 'detail_type_id')
            ->orderBy('gl_code')
            ->get();

        $income_accounts = collect();
        $expense_accounts = collect();
        $total_income = 0;
        $total_expense = 0;

        foreach ($all_accounts as $account) {
            $monthly_balances = [];
            $total_balance = 0;
            $has_any_balance = false;

            foreach ($months as $month) {
                // For P&L Report: Calculate ONLY period transactions (EXCLUDE opening_balance)
                // Join with transactions table to filter by gym category
                $query = DB::table('accounting_accounts_transactions')
                    ->where('accounting_accounts_transactions.accounting_account_id', $account->id)
                    ->where(function ($q) {
                        $q->whereNull('accounting_accounts_transactions.sub_type')
                            ->orWhere('accounting_accounts_transactions.sub_type', '!=', 'opening_balance');
                    })
                    ->whereDate('accounting_accounts_transactions.operation_date', '>=', $month['start'])
                    ->whereDate('accounting_accounts_transactions.operation_date', '<=', $month['end']);

                // Apply gym category filter if specified
                if (!empty($gym_category_id)) {
                    $query->leftJoin('transactions', 'accounting_accounts_transactions.transaction_id', '=', 'transactions.id')
                        ->leftJoin('gym_packages', 'transactions.gym_package_id', '=', 'gym_packages.id')
                        ->where(function ($q) use ($gym_category_id) {
                            $q->where('gym_packages.gym_category_id', $gym_category_id)
                                ->orWhereNull('accounting_accounts_transactions.transaction_id'); // Include journal entries without transaction
                        });
                }

                $period = $query->select(
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'debit', accounting_accounts_transactions.amount, 0)) as debit"),
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'credit', accounting_accounts_transactions.amount, 0)) as credit")
                )->first();

                $debit_balance = $period->debit ?? 0;
                $credit_balance = $period->credit ?? 0;

                // For income: credit - debit = positive income
                // For expense: debit - credit = positive expense
                $balance = $account->account_primary_type == 'income'
                    ? ($credit_balance - $debit_balance)
                    : ($debit_balance - $credit_balance);

                $monthly_balances[$month['key']] = $balance;
                $total_balance += $balance;

                if ($balance != 0) {
                    $has_any_balance = true;
                }

                // Add to monthly totals
                if ($account->account_primary_type == 'income') {
                    $monthly_totals['income'][$month['key']] += $balance;
                } else {
                    $monthly_totals['expense'][$month['key']] += $balance;
                }
            }

            // Only include accounts that have activity DURING THE PERIOD
            if ($has_any_balance) {
                $account_data = (object) [
                    'id' => $account->id,
                    'name' => $account->name,
                    'gl_code' => $account->gl_code,
                    'account_primary_type' => $account->account_primary_type,
                    'monthly_balances' => $monthly_balances,
                    'balance' => $total_balance,
                ];

                if ($account->account_primary_type == 'income') {
                    $income_accounts->push($account_data);
                    $total_income += $total_balance;
                } else {
                    // expenses, cost_of_sale, other_expense
                    $expense_accounts->push($account_data);
                    $total_expense += $total_balance;
                }
            }
        }

        // Calculate net profit per month
        foreach ($months as $month) {
            $monthly_totals['net_profit'][$month['key']] =
                $monthly_totals['income'][$month['key']] - $monthly_totals['expense'][$month['key']];
        }

        $net_profit = $total_income - $total_expense;

        return view('accounting::report.profit_loss')
            ->with(compact('income_accounts', 'expense_accounts', 'total_income', 'total_expense', 'net_profit', 'start_date', 'end_date', 'months', 'monthly_totals', 'detail_types', 'detail_type_id'));
    }

    /**
     * Profit and Loss YTD (Year-to-Date) Report
     * Similar to Balance Sheet but for P&L accounts
     *
     * @return Response
     */
    public function pnlYtd()
    {
        $business_id = request()->session()->get('user.business_id');

        if (
            !(auth()->user()->can('superadmin') ||
                $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            !(auth()->user()->can('accounting.view_reports'))
        ) {
            abort(403, 'Unauthorized action.');
        }

        // Detail Type filter
        $detail_type_id = request()->input('detail_type_id', null);
        $detail_types = AccountingAccountType::where('account_type', 'detail_type')
            ->where(function ($q) use ($business_id) {
                $q->whereNull('business_id')
                    ->orWhere('business_id', $business_id);
            })
            ->get()
            ->mapWithKeys(function ($item) {
                $name = !empty($item->business_id) ? $item->name : __('accounting::lang.' . $item->name);
                return [$item->id => $name];
            })
            ->toArray();

        if (!empty(request()->end_date)) {
            $selected_end = \Carbon\Carbon::parse(request()->end_date)->endOfMonth();
            $business_start = Business::where('id', $business_id)->value('start_date');

            if (!empty($business_start)) {
                $business_start = \Carbon\Carbon::parse($business_start)->startOfDay();
                if ($business_start->greaterThan($selected_end)) {
                    $start_date = $selected_end->copy()->startOfMonth()->format('Y-m-d');
                } else {
                    $start_date = $business_start->format('Y-m-d');
                }
            } else {
                $start_date = $selected_end->copy()->startOfYear()->format('Y-m-d');
            }

            $end_date = $selected_end->format('Y-m-d');
        } else {
            $fy = $this->businessUtil->getCurrentFinancialYear($business_id);
            $start_date = $fy['start'];
            $end_date = $fy['end'];
        }

        // Generate list of months in the date range
        $months = [];
        $current = \Carbon\Carbon::parse($start_date)->startOfMonth();
        $end = \Carbon\Carbon::parse($end_date)->endOfMonth();

        while ($current <= $end) {
            $months[] = [
                'key' => $current->format('Y-m'),
                'label' => $current->translatedFormat('M Y'),
                'start' => $current->copy()->startOfMonth()->format('Y-m-d'),
                'end' => $current->copy()->endOfMonth()->format('Y-m-d'),
            ];
            $current->addMonth();
        }

        // Get all active P&L accounts (gl_code >= 4), optionally filtered by detail_type
        $accountQuery = AccountingAccount::where('business_id', $business_id)
            ->where('status', 'active')
            ->whereNotNull('gl_code')
            ->where('gl_code', '!=', '')
            ->whereRaw("CAST(SUBSTRING(gl_code, 1, 1) AS UNSIGNED) >= 4");

        // Apply detail type filter if selected
        if (!empty($detail_type_id)) {
            $accountQuery->where('detail_type_id', $detail_type_id);
        }

        $all_accounts = $accountQuery
            ->select('id', 'name', 'gl_code', 'account_primary_type', 'detail_type_id')
            ->orderBy('gl_code')
            ->get();

        $income_accounts = collect();
        $expense_accounts = collect();
        $total_income = 0;
        $total_expense = 0;

        // Initialize monthly totals
        $monthly_totals = [
            'income' => [],
            'expense' => [],
            'net_profit' => [],
        ];
        foreach ($months as $month) {
            $monthly_totals['income'][$month['key']] = 0;
            $monthly_totals['expense'][$month['key']] = 0;
            $monthly_totals['net_profit'][$month['key']] = 0;
        }

        foreach ($all_accounts as $account) {
            $monthly_balances = [];
            $cumulative_balance = 0; // Track cumulative YTD balance
            $has_any_balance = false;

            foreach ($months as $month) {
                // Calculate monthly balance (EXCLUDE opening_balance)
                $query = DB::table('accounting_accounts_transactions')
                    ->where('accounting_accounts_transactions.accounting_account_id', $account->id)
                    ->where(function ($q) {
                        $q->whereNull('accounting_accounts_transactions.sub_type')
                            ->orWhere('accounting_accounts_transactions.sub_type', '!=', 'opening_balance');
                    })
                    ->whereDate('accounting_accounts_transactions.operation_date', '>=', $month['start'])
                    ->whereDate('accounting_accounts_transactions.operation_date', '<=', $month['end']);

                // Apply gym category filter if specified
                if (!empty($gym_category_id)) {
                    $query->leftJoin('transactions', 'accounting_accounts_transactions.transaction_id', '=', 'transactions.id')
                        ->leftJoin('gym_packages', 'transactions.gym_package_id', '=', 'gym_packages.id')
                        ->where(function ($q) use ($gym_category_id) {
                            $q->where('gym_packages.gym_category_id', $gym_category_id)
                                ->orWhereNull('accounting_accounts_transactions.transaction_id');
                        });
                }

                $period = $query->select(
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'debit', accounting_accounts_transactions.amount, 0)) as debit"),
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'credit', accounting_accounts_transactions.amount, 0)) as credit")
                )->first();

                $debit_balance = $period->debit ?? 0;
                $credit_balance = $period->credit ?? 0;

                // For income: credit - debit = positive income
                // For expense: debit - credit = positive expense
                $month_balance = $account->account_primary_type == 'income'
                    ? ($credit_balance - $debit_balance)
                    : ($debit_balance - $credit_balance);

                // Add to cumulative YTD balance
                $cumulative_balance += $month_balance;

                // Store cumulative YTD balance for this month
                $monthly_balances[$month['key']] = $cumulative_balance;

                if ($month_balance != 0) {
                    $has_any_balance = true;
                }

                // Add to monthly totals (cumulative)
                if ($account->account_primary_type == 'income') {
                    $monthly_totals['income'][$month['key']] += $cumulative_balance;
                } else {
                    $monthly_totals['expense'][$month['key']] += $cumulative_balance;
                }
            }

            // Only include accounts that have activity during the period
            if ($has_any_balance) {
                $account_data = (object) [
                    'id' => $account->id,
                    'name' => $account->name,
                    'gl_code' => $account->gl_code,
                    'account_primary_type' => $account->account_primary_type,
                    'monthly_balances' => $monthly_balances,
                    'balance' => $cumulative_balance, // Final YTD balance
                ];

                if ($account->account_primary_type == 'income') {
                    $income_accounts->push($account_data);
                    $total_income += $cumulative_balance;
                } else {
                    $expense_accounts->push($account_data);
                    $total_expense += $cumulative_balance;
                }
            }
        }

        // Calculate net profit per month (cumulative)
        foreach ($months as $month) {
            $monthly_totals['net_profit'][$month['key']] =
                $monthly_totals['income'][$month['key']] - $monthly_totals['expense'][$month['key']];
        }

        $net_profit = $total_income - $total_expense;

        // Get the first transaction year for year filter range
        $first_transaction_year = DB::table('accounting_accounts_transactions')
            ->whereIn('accounting_account_id', $all_accounts->pluck('id'))
            ->min(DB::raw('YEAR(operation_date)'));
        
        // If no transactions, fallback to current year
        if (empty($first_transaction_year)) {
            $first_transaction_year = (int) date('Y');
        }

        return view('accounting::report.pnl_ytd')
            ->with(compact('income_accounts', 'expense_accounts', 'total_income', 'total_expense', 'net_profit', 'start_date', 'end_date', 'months', 'monthly_totals', 'first_transaction_year', 'detail_types', 'detail_type_id'));
    }


    public function pnlBisnis()
    {
        $business_id = request()->session()->get('user.business_id');

        if (
            !(auth()->user()->can('superadmin') ||
                $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            !(auth()->user()->can('accounting.view_reports'))
        ) {
            abort(403, 'Unauthorized action.');
        }

        if (! empty(request()->start_date) && ! empty(request()->end_date)) {
            $start_date = request()->start_date;
            $end_date = request()->end_date;
        } else {
            $fy = $this->businessUtil->getCurrentFinancialYear($business_id);
            $start_date = $fy['start'];
            $end_date = $fy['end'];
        }

        // Generate months for Last Month, Current Month, YTD calculations
        $months = [];
        $current = \Carbon\Carbon::parse($start_date)->startOfMonth();
        $end = \Carbon\Carbon::parse($end_date)->endOfMonth();

        while ($current <= $end) {
            $months[] = [
                'key' => $current->format('Y-m'),
                'label' => $current->translatedFormat('M Y'),
                'start' => $current->copy()->startOfMonth()->format('Y-m-d'),
                'end' => $current->copy()->endOfMonth()->format('Y-m-d'),
            ];
            $current->addMonth();
        }

        // Calculate period ranges for Last Month, Current Month, YTD
        $month_count = count($months);
        $current_month_period = $month_count > 0 ? $months[$month_count - 1] : null;
        $last_month_period = $month_count > 1 ? $months[$month_count - 2] : null;

        $ytd_period = [
            'start' => $start_date,
            'end' => $end_date,
        ];

        // Current Month period
        $cm_start = $current_month_period ? $current_month_period['start'] : null;
        $cm_end = $current_month_period ? $current_month_period['end'] : null;

        // Last Month period
        $lm_start = $last_month_period ? $last_month_period['start'] : null;
        $lm_end = $last_month_period ? $last_month_period['end'] : null;

        // Get all active P&L accounts (gl_code >= 4) with detail_type
        $all_accounts = AccountingAccount::where('business_id', $business_id)
            ->where('status', 'active')
            ->whereNotNull('gl_code')
            ->where('gl_code', '!=', '')
            ->whereRaw("CAST(SUBSTRING(gl_code, 1, 1) AS UNSIGNED) >= 4")
            ->with('detail_type')
            ->select('id', 'name', 'gl_code', 'account_primary_type', 'detail_type_id')
            ->orderBy('gl_code')
            ->get();

        $income_accounts = collect();
        $expense_accounts = collect();
        $total_income = 0;
        $total_expense = 0;

        // Initialize category totals
        $period_values = ['last_month', 'current_month', 'ytd'];
        $createPeriodStructure = function () use ($period_values) {
            return array_fill_keys($period_values, 0);
        };

        $category_totals = [
            'income' => [],
            'expense' => [],
        ];

        // 1. BUILD BUSINESS CATEGORIES (Merge Detail Types & Expense Category Groups)
        $business_categories = [];

        // A. From Accounting Detail Types (Existing Logic)
        $detail_types_from_db = AccountingAccountType::where('account_type', 'detail_type')
            ->where(function ($q) use ($business_id) {
                $q->whereNull('business_id')
                    ->orWhere('business_id', $business_id);
            })
            ->select('id', 'name', 'business_id')
            ->orderBy('name')
            ->get();

        foreach ($detail_types_from_db as $dt) {
            $cat_key = \Illuminate\Support\Str::slug($dt->name, '_');
            $display_name = empty($dt->business_id)
                ? __('accounting::lang.'.$dt->name)
                : $dt->name;

            if (isset($business_categories[$cat_key])) {
                $business_categories[$cat_key]['detail_type_ids'][] = $dt->id;
            } else {
                $business_categories[$cat_key] = [
                    'name' => $display_name,
                    'detail_type_ids' => [$dt->id],
                    'pnl_groups' => [], // Will be used for expense matching
                ];
            }
        }

        // B. From Expense Categories 'pnl_group' field (New Logic)
        $expense_groups = \App\ExpenseCategory::where('business_id', $business_id)
            ->whereNotNull('pnl_group')
            ->distinct('pnl_group')
            ->pluck('pnl_group');

        foreach ($expense_groups as $group_name) {
            $cat_key = \Illuminate\Support\Str::slug($group_name, '_');

            if (! isset($business_categories[$cat_key])) {
                // If category doesn't exist from detail types, create it
                $business_categories[$cat_key] = [
                    'name' => $group_name,
                    'detail_type_ids' => [],
                    'pnl_groups' => [$group_name],
                ];
            } else {
                // If exists, just add the pnl_group name to the list
                // This handles case where Expense Group "Gym" matches Account Detail Type "Gym"
                if (! in_array($group_name, $business_categories[$cat_key]['pnl_groups'])) {
                    $business_categories[$cat_key]['pnl_groups'][] = $group_name;
                }
            }
        }

        // Initialize totals for all categories
        foreach (array_keys($business_categories) as $cat_key) {
            $category_totals['income'][$cat_key] = $createPeriodStructure();
            $category_totals['expense'][$cat_key] = $createPeriodStructure();
        }
        $category_totals['income']['other'] = $createPeriodStructure();
        $category_totals['expense']['other'] = $createPeriodStructure();

        // 2. HELPER TO CALCULATE BALANCE WITH BREAKDOWN
        $calculateBalanceWithBreakdown = function ($account_id, $start, $end, $account_type) use ($business_categories) {
            if (! $start || ! $end) {
                return ['total' => 0, 'breakdown' => []];
            }

            $query = DB::table('accounting_accounts_transactions as aat')
                ->where('aat.accounting_account_id', $account_id)
                ->where(function ($q) {
                    $q->whereNull('aat.sub_type')
                        ->orWhere('aat.sub_type', '!=', 'opening_balance');
                })
                ->whereDate('aat.operation_date', '>=', $start)
                ->whereDate('aat.operation_date', '<=', $end);

            if ($account_type == 'expenses') {
                // For expenses: Join transactions to get expense category -> pnl_group
                $query->leftJoin('transactions as t', 'aat.transaction_id', '=', 't.id')
                    ->leftJoin('expense_categories as ec', 't.expense_category_id', '=', 'ec.id')
                    ->select(
                        DB::raw("SUM(IF(aat.type = 'debit', aat.amount, 0)) - SUM(IF(aat.type = 'credit', aat.amount, 0)) as balance"),
                        'ec.pnl_group'
                    )
                    ->groupBy('ec.pnl_group');
            } else {
                // For income: Simple total (distribution handled by account detail type)
                $query->select(
                    DB::raw("SUM(IF(aat.type = 'credit', aat.amount, 0)) - SUM(IF(aat.type = 'debit', aat.amount, 0)) as balance")
                );
            }

            $results = $query->get();
            $total = 0;
            $breakdown = [];

            if ($account_type == 'expenses') {
                foreach ($results as $row) {
                    $bal = $row->balance;
                    $total += $bal;

                    $pnl_group = $row->pnl_group;
                    $matched = false;

                    if ($pnl_group) {
                        $slug = \Illuminate\Support\Str::slug($pnl_group, '_');
                        // Use slug to find matching business category
                        if (isset($business_categories[$slug])) {
                            $breakdown[$slug] = ($breakdown[$slug] ?? 0) + $bal;
                            $matched = true;
                        }
                    }

                    if (! $matched) {
                        $breakdown['other'] = ($breakdown['other'] ?? 0) + $bal;
                    }
                }
            } else {
                $bal = $results->first()->balance ?? 0;
                $total = $bal;
                // No dynamic breakdown for income
            }

            return ['total' => $total, 'breakdown' => $breakdown];
        };

        // 3. MAIN LOOP
        foreach ($all_accounts as $account) {
            $is_income = $account->account_primary_type == 'income';

            // Calculate for all periods
            $lm_data = $calculateBalanceWithBreakdown($account->id, $lm_start, $lm_end, $account->account_primary_type);
            $cm_data = $calculateBalanceWithBreakdown($account->id, $cm_start, $cm_end, $account->account_primary_type);
            $ytd_data = $calculateBalanceWithBreakdown($account->id, $start_date, $end_date, $account->account_primary_type);

            $ytd_total = $ytd_data['total'];

            // Skip if no activity
            if ($ytd_total == 0 && $lm_data['total'] == 0 && $cm_data['total'] == 0) {
                continue;
            }

            // Init account specific balances
            $account_cat_balances = [];
            foreach (array_keys($business_categories) as $k) {
                $account_cat_balances[$k] = $createPeriodStructure();
            }
            $account_cat_balances['other'] = $createPeriodStructure();

            if ($is_income) {
                // INCOME LOGIC: Assign total to account's detail type category
                $dt_id = $account->detail_type_id;
                $target_cat = 'other';
                if ($dt_id) {
                    foreach ($business_categories as $k => $info) {
                        if (in_array($dt_id, $info['detail_type_ids'])) {
                            $target_cat = $k;
                            break;
                        }
                    }
                }

                // Add to account balances
                $account_cat_balances[$target_cat]['last_month'] += $lm_data['total'];
                $account_cat_balances[$target_cat]['current_month'] += $cm_data['total'];
                $account_cat_balances[$target_cat]['ytd'] += $ytd_data['total'];

                // Add to Global Totals
                $category_totals['income'][$target_cat]['last_month'] += $lm_data['total'];
                $category_totals['income'][$target_cat]['current_month'] += $cm_data['total'];
                $category_totals['income'][$target_cat]['ytd'] += $ytd_data['total'];
                $total_income += $ytd_data['total'];
            } else {
                // EXPENSE LOGIC: Distribute based on transaction breakdown
                $distribute = function ($data, $period) use (&$account_cat_balances, &$category_totals) {
                    foreach ($data['breakdown'] as $cat => $val) {
                        $target = isset($account_cat_balances[$cat]) ? $cat : 'other';
                        $account_cat_balances[$target][$period] += $val;
                        $category_totals['expense'][$target][$period] += $val;
                    }
                };

                $distribute($lm_data, 'last_month');
                $distribute($cm_data, 'current_month');
                $distribute($ytd_data, 'ytd');
                $total_expense += $ytd_total;
            }

            $account_data = (object) [
                'gl_code' => $account->gl_code,
                'name' => $account->name,
                'account_primary_type' => $account->account_primary_type,
                'category_balances' => $account_cat_balances,
                'balance' => $ytd_total,
                'detail_type' => $account->detail_type ? $account->detail_type->name : null,
                // category_key used for grouping in view, might be ambiguous for shared expenses but useful for sorting
                'category_key' => $is_income ? $target_cat : 'mixed',
            ];

            if ($is_income) {
                $income_accounts->push($account_data);
            } else {
                $expense_accounts->push($account_data);
            }
        }

        $net_profit = $total_income - $total_expense;

        // Calculate net profit per category
        $category_net_profit = [];
        foreach (array_keys($business_categories) as $cat_key) {
            $category_net_profit[$cat_key] = [
                'last_month' => $category_totals['income'][$cat_key]['last_month'] - $category_totals['expense'][$cat_key]['last_month'],
                'current_month' => $category_totals['income'][$cat_key]['current_month'] - $category_totals['expense'][$cat_key]['current_month'],
                'ytd' => $category_totals['income'][$cat_key]['ytd'] - $category_totals['expense'][$cat_key]['ytd'],
            ];
        }
        $category_net_profit['other'] = [
            'last_month' => $category_totals['income']['other']['last_month'] - $category_totals['expense']['other']['last_month'],
            'current_month' => $category_totals['income']['other']['current_month'] - $category_totals['expense']['other']['current_month'],
            'ytd' => $category_totals['income']['other']['ytd'] - $category_totals['expense']['other']['ytd'],
        ];

        return view('accounting::report.pnl_bisnis')
            ->with(compact(
                'income_accounts',
                'expense_accounts',
                'total_income',
                'total_expense',
                'net_profit',
                'start_date',
                'end_date',
                'months',
                'business_categories',
                'category_totals',
                'category_net_profit'
            ));
    }

    /**
     * Diagnostic page for P&L Business Categories
     * Shows the complete flow: Detail Types -> Accounts -> Transactions -> Report
     */
    public function diagnosa()
    {
        $business_id = request()->session()->get('user.business_id');

        if (
            !(auth()->user()->can('superadmin') ||
                $this->moduleUtil->hasThePermissionInSubscription($business_id, 'accounting_module')) ||
            !(auth()->user()->can('accounting.view_reports'))
        ) {
            abort(403, 'Unauthorized action.');
        }

        // Get date range
        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start_date = request()->start_date;
            $end_date = request()->end_date;
        } else {
            $fy = $this->businessUtil->getCurrentFinancialYear($business_id);
            $start_date = $fy['start'];
            $end_date = $fy['end'];
        }

        // STEP 1: Get all Detail Types with account count
        $detail_types = AccountingAccountType::where('account_type', 'detail_type')
            ->where(function ($q) use ($business_id) {
                $q->whereNull('business_id')
                    ->orWhere('business_id', $business_id);
            })
            ->select('id', 'name', 'business_id', 'parent_id')
            ->withCount(['accounts' => function ($q) use ($business_id) {
                $q->where('business_id', $business_id);
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($dt) {
                $dt->parent_name = $dt->parent ? $dt->parent->name : null;
                return $dt;
            });

        // STEP 2: Get accounts with detail type
        $accounts_with_detail_type = AccountingAccount::where('business_id', $business_id)
            ->where('status', 'active')
            ->whereNotNull('detail_type_id')
            ->whereRaw("CAST(SUBSTRING(gl_code, 1, 1) AS UNSIGNED) >= 4")
            ->with('detail_type')
            ->select('id', 'name', 'gl_code', 'account_primary_type', 'detail_type_id')
            ->orderBy('gl_code')
            ->get()
            ->map(function ($acc) use ($start_date, $end_date) {
                $acc->detail_type_name = $acc->detail_type ? $acc->detail_type->name : 'N/A';
                $acc->transactions_count = DB::table('accounting_accounts_transactions')
                    ->where('accounting_account_id', $acc->id)
                    ->where(function ($q) {
                        $q->whereNull('sub_type')
                            ->orWhere('sub_type', '!=', 'opening_balance');
                    })
                    ->whereDate('operation_date', '>=', $start_date)
                    ->whereDate('operation_date', '<=', $end_date)
                    ->count();
                return $acc;
            });

        // Get accounts WITHOUT detail type (these go to "Other")
        $accounts_without_detail_type = AccountingAccount::where('business_id', $business_id)
            ->where('status', 'active')
            ->whereNull('detail_type_id')
            ->whereRaw("CAST(SUBSTRING(gl_code, 1, 1) AS UNSIGNED) >= 4")
            ->select('id', 'name', 'gl_code', 'account_primary_type')
            ->orderBy('gl_code')
            ->get()
            ->map(function ($acc) use ($start_date, $end_date) {
                $acc->transactions_count = DB::table('accounting_accounts_transactions')
                    ->where('accounting_account_id', $acc->id)
                    ->where(function ($q) {
                        $q->whereNull('sub_type')
                            ->orWhere('sub_type', '!=', 'opening_balance');
                    })
                    ->whereDate('operation_date', '>=', $start_date)
                    ->whereDate('operation_date', '<=', $end_date)
                    ->count();
                return $acc;
            });

        // STEP 3: Calculate category summary
        $business_categories = [];
        foreach ($detail_types as $dt) {
            $cat_key = \Illuminate\Support\Str::slug($dt->name, '_');
            if (isset($business_categories[$cat_key])) {
                $business_categories[$cat_key]['detail_type_ids'][] = $dt->id;
            } else {
                $business_categories[$cat_key] = [
                    'name' => $dt->name,
                    'detail_type_ids' => [$dt->id],
                ];
            }
        }

        $category_summary = [];
        $total_income = 0;
        $total_expense = 0;

        foreach ($business_categories as $cat_key => $cat_info) {
            $dt_ids = $cat_info['detail_type_ids'];
            
            // Get income for this category
            $income = DB::table('accounting_accounts_transactions as aat')
                ->join('accounting_accounts as acc', 'aat.accounting_account_id', '=', 'acc.id')
                ->whereIn('acc.detail_type_id', $dt_ids)
                ->where('acc.business_id', $business_id)
                ->where('acc.account_primary_type', 'income')
                ->where(function ($q) {
                    $q->whereNull('aat.sub_type')
                        ->orWhere('aat.sub_type', '!=', 'opening_balance');
                })
                ->whereDate('aat.operation_date', '>=', $start_date)
                ->whereDate('aat.operation_date', '<=', $end_date)
                ->select(
                    DB::raw("COALESCE(SUM(IF(aat.type = 'credit', aat.amount, 0)), 0) as credit"),
                    DB::raw("COALESCE(SUM(IF(aat.type = 'debit', aat.amount, 0)), 0) as debit")
                )
                ->first();

            $income_val = ($income->credit ?? 0) - ($income->debit ?? 0);

            // Get expense for this category
            $expense = DB::table('accounting_accounts_transactions as aat')
                ->join('accounting_accounts as acc', 'aat.accounting_account_id', '=', 'acc.id')
                ->whereIn('acc.detail_type_id', $dt_ids)
                ->where('acc.business_id', $business_id)
                ->where('acc.account_primary_type', '!=', 'income')
                ->where(function ($q) {
                    $q->whereNull('aat.sub_type')
                        ->orWhere('aat.sub_type', '!=', 'opening_balance');
                })
                ->whereDate('aat.operation_date', '>=', $start_date)
                ->whereDate('aat.operation_date', '<=', $end_date)
                ->select(
                    DB::raw("COALESCE(SUM(IF(aat.type = 'debit', aat.amount, 0)), 0) as debit"),
                    DB::raw("COALESCE(SUM(IF(aat.type = 'credit', aat.amount, 0)), 0) as credit")
                )
                ->first();

            $expense_val = ($expense->debit ?? 0) - ($expense->credit ?? 0);

            $category_summary[$cat_key] = [
                'name' => $cat_info['name'],
                'income' => $income_val,
                'expense' => $expense_val,
                'net_profit' => $income_val - $expense_val,
            ];

            $total_income += $income_val;
            $total_expense += $expense_val;
        }

        // Add "Other" category
        $other_income = DB::table('accounting_accounts_transactions as aat')
            ->join('accounting_accounts as acc', 'aat.accounting_account_id', '=', 'acc.id')
            ->whereNull('acc.detail_type_id')
            ->where('acc.business_id', $business_id)
            ->where('acc.account_primary_type', 'income')
            ->whereRaw("CAST(SUBSTRING(acc.gl_code, 1, 1) AS UNSIGNED) >= 4")
            ->where(function ($q) {
                $q->whereNull('aat.sub_type')
                    ->orWhere('aat.sub_type', '!=', 'opening_balance');
            })
            ->whereDate('aat.operation_date', '>=', $start_date)
            ->whereDate('aat.operation_date', '<=', $end_date)
            ->select(
                DB::raw("COALESCE(SUM(IF(aat.type = 'credit', aat.amount, 0)), 0) as credit"),
                DB::raw("COALESCE(SUM(IF(aat.type = 'debit', aat.amount, 0)), 0) as debit")
            )
            ->first();

        $other_expense = DB::table('accounting_accounts_transactions as aat')
            ->join('accounting_accounts as acc', 'aat.accounting_account_id', '=', 'acc.id')
            ->whereNull('acc.detail_type_id')
            ->where('acc.business_id', $business_id)
            ->where('acc.account_primary_type', '!=', 'income')
            ->whereRaw("CAST(SUBSTRING(acc.gl_code, 1, 1) AS UNSIGNED) >= 4")
            ->where(function ($q) {
                $q->whereNull('aat.sub_type')
                    ->orWhere('aat.sub_type', '!=', 'opening_balance');
            })
            ->whereDate('aat.operation_date', '>=', $start_date)
            ->whereDate('aat.operation_date', '<=', $end_date)
            ->select(
                DB::raw("COALESCE(SUM(IF(aat.type = 'debit', aat.amount, 0)), 0) as debit"),
                DB::raw("COALESCE(SUM(IF(aat.type = 'credit', aat.amount, 0)), 0) as credit")
            )
            ->first();

        $other_income_val = ($other_income->credit ?? 0) - ($other_income->debit ?? 0);
        $other_expense_val = ($other_expense->debit ?? 0) - ($other_expense->credit ?? 0);

        $category_summary['other'] = [
            'name' => 'Other',
            'income' => $other_income_val,
            'expense' => $other_expense_val,
            'net_profit' => $other_income_val - $other_expense_val,
        ];

        $total_income += $other_income_val;
        $total_expense += $other_expense_val;
        $net_profit = $total_income - $total_expense;

        return view('accounting::report.diagnosa')
            ->with(compact(
                'detail_types',
                'accounts_with_detail_type',
                'accounts_without_detail_type',
                'category_summary',
                'total_income',
                'total_expense',
                'net_profit',
                'start_date',
                'end_date'
            ));
    }
}
