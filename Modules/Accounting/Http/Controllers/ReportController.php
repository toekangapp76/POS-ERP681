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

        // Get monthly balances for each account
        $all_accounts = [];
        foreach ($base_accounts as $account) {
            $account_data = [
                'id' => $account->id,
                'name' => $account->name,
                'gl_code' => $account->gl_code,
                'account_primary_type' => $account->account_primary_type,
                'sub_type' => $account->sub_type,
                'monthly_balances' => [],
                'balance' => 0, // Total balance for the entire period
            ];

            $has_any_balance = false;

            foreach ($months as $month) {
                $balance = DB::table('accounting_accounts_transactions')
                    ->where('accounting_account_id', $account->id)
                    ->whereDate('operation_date', '>=', $month['start'])
                    ->whereDate('operation_date', '<=', $month['end'])
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
                $account_data['balance'] += $monthly_balance;

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
            $month_income = 0;
            $month_expense = 0;

            foreach ($pl_accounts as $pl_account) {
                $pl_balance = DB::table('accounting_accounts_transactions')
                    ->where('accounting_account_id', $pl_account->id)
                    ->where(function ($q) {
                        $q->whereNull('sub_type')
                            ->orWhere('sub_type', '!=', 'opening_balance');
                    })
                    ->whereDate('operation_date', '>=', $month['start'])
                    ->whereDate('operation_date', '<=', $month['end'])
                    ->select(
                        DB::raw("COALESCE(SUM(IF(type = 'debit', amount, 0)), 0) as debit"),
                        DB::raw("COALESCE(SUM(IF(type = 'credit', amount, 0)), 0) as credit")
                    )
                    ->first();

                $debit = $pl_balance->debit ?? 0;
                $credit = $pl_balance->credit ?? 0;

                if ($pl_account->account_primary_type == 'income') {
                    $month_income += ($credit - $debit);
                } else {
                    $month_expense += ($debit - $credit);
                }
            }

            $net_profit_monthly[$month['key']] = $month_income - $month_expense;
            $total_net_profit += $net_profit_monthly[$month['key']];
        }

        // R/E Current Year account info (GL Code: 3202-0000)
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
            ->with(compact('all_accounts', 'start_date', 'end_date', 'months', 're_current_year', 'net_profit_monthly', 'total_net_profit'));
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

        // Get gym categories
        $gym_categories = [];
        try {
            $gym_categories = GymCategory::where('business_id', $business_id)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            // Gym module not installed
        }

        if (!empty(request()->start_date) && !empty(request()->end_date)) {
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

        // YTD period is the entire date range
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

        // Get all active P&L accounts (gl_code >= 4)
        $all_accounts = AccountingAccount::where('business_id', $business_id)
            ->where('status', 'active')
            ->whereNotNull('gl_code')
            ->where('gl_code', '!=', '')
            ->whereRaw("CAST(SUBSTRING(gl_code, 1, 1) AS UNSIGNED) >= 4")
            ->select('id', 'name', 'gl_code', 'account_primary_type')
            ->orderBy('gl_code')
            ->get();

        $income_accounts = collect();
        $expense_accounts = collect();
        $total_income = 0;
        $total_expense = 0;

        // Initialize category totals with period breakdown (last_month, current_month, ytd)
        $period_keys = ['last_month', 'current_month', 'ytd'];
        $category_totals = [
            'income' => [],
            'expense' => [],
        ];

        // Helper function to create period structure
        $createPeriodStructure = function () use ($period_keys) {
            return array_fill_keys($period_keys, 0);
        };

        foreach ($gym_categories as $category) {
            $category_totals['income'][$category->id] = $createPeriodStructure();
            $category_totals['expense'][$category->id] = $createPeriodStructure();
        }
        // Initialize Pro Shop, Sudest Café, and "Other" (no category)
        $category_totals['income']['pro_shop'] = $createPeriodStructure();
        $category_totals['expense']['pro_shop'] = $createPeriodStructure();
        $category_totals['income']['sudest_cafe'] = $createPeriodStructure();
        $category_totals['expense']['sudest_cafe'] = $createPeriodStructure();
        $category_totals['income']['other'] = $createPeriodStructure();
        $category_totals['expense']['other'] = $createPeriodStructure();

        // Helper function to calculate balance for a specific date range
        $calculateBalance = function ($query_base, $start, $end, $account_type) {
            if (!$start || !$end)
                return 0;

            $query = clone $query_base;
            $period = $query
                ->whereDate('accounting_accounts_transactions.operation_date', '>=', $start)
                ->whereDate('accounting_accounts_transactions.operation_date', '<=', $end)
                ->select(
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'debit', accounting_accounts_transactions.amount, 0)) as debit"),
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'credit', accounting_accounts_transactions.amount, 0)) as credit")
                )->first();

            $debit = $period->debit ?? 0;
            $credit = $period->credit ?? 0;

            if ($account_type == 'income') {
                return $credit - $debit;
            } else {
                return $debit - $credit;
            }
        };

        foreach ($all_accounts as $account) {
            $category_balances = [];
            $total_balance = 0;
            $has_any_balance = false;

            // Calculate balance for each gym category with period breakdown
            foreach ($gym_categories as $category) {
                // Base query for this category
                $getBaseQuery = function () use ($account, $category) {
                    return DB::table('accounting_accounts_transactions')
                        ->leftJoin('transactions', 'accounting_accounts_transactions.transaction_id', '=', 'transactions.id')
                        ->leftJoin('gym_packages', 'transactions.gym_package_id', '=', 'gym_packages.id')
                        ->where('accounting_accounts_transactions.accounting_account_id', $account->id)
                        ->where(function ($q) {
                            $q->whereNull('accounting_accounts_transactions.sub_type')
                                ->orWhere('accounting_accounts_transactions.sub_type', '!=', 'opening_balance');
                        })
                        ->where('gym_packages.gym_category_id', $category->id);
                };

                // Calculate for each period
                $lm_balance = 0;
                $cm_balance = 0;
                $ytd_balance = 0;

                // Last Month
                if ($lm_start && $lm_end) {
                    $lm_query = $getBaseQuery()
                        ->whereDate('accounting_accounts_transactions.operation_date', '>=', $lm_start)
                        ->whereDate('accounting_accounts_transactions.operation_date', '<=', $lm_end);
                    $lm_period = $lm_query->select(
                        DB::raw("SUM(IF(accounting_accounts_transactions.type = 'debit', accounting_accounts_transactions.amount, 0)) as debit"),
                        DB::raw("SUM(IF(accounting_accounts_transactions.type = 'credit', accounting_accounts_transactions.amount, 0)) as credit")
                    )->first();
                    $lm_debit = $lm_period->debit ?? 0;
                    $lm_credit = $lm_period->credit ?? 0;
                    $lm_balance = ($account->account_primary_type == 'income') ? ($lm_credit - $lm_debit) : ($lm_debit - $lm_credit);
                }

                // Current Month
                if ($cm_start && $cm_end) {
                    $cm_query = $getBaseQuery()
                        ->whereDate('accounting_accounts_transactions.operation_date', '>=', $cm_start)
                        ->whereDate('accounting_accounts_transactions.operation_date', '<=', $cm_end);
                    $cm_period = $cm_query->select(
                        DB::raw("SUM(IF(accounting_accounts_transactions.type = 'debit', accounting_accounts_transactions.amount, 0)) as debit"),
                        DB::raw("SUM(IF(accounting_accounts_transactions.type = 'credit', accounting_accounts_transactions.amount, 0)) as credit")
                    )->first();
                    $cm_debit = $cm_period->debit ?? 0;
                    $cm_credit = $cm_period->credit ?? 0;
                    $cm_balance = ($account->account_primary_type == 'income') ? ($cm_credit - $cm_debit) : ($cm_debit - $cm_credit);
                }

                // YTD (full period)
                $ytd_query = $getBaseQuery()
                    ->whereDate('accounting_accounts_transactions.operation_date', '>=', $start_date)
                    ->whereDate('accounting_accounts_transactions.operation_date', '<=', $end_date);
                $ytd_period = $ytd_query->select(
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'debit', accounting_accounts_transactions.amount, 0)) as debit"),
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'credit', accounting_accounts_transactions.amount, 0)) as credit")
                )->first();
                $ytd_debit = $ytd_period->debit ?? 0;
                $ytd_credit = $ytd_period->credit ?? 0;
                $ytd_balance = ($account->account_primary_type == 'income') ? ($ytd_credit - $ytd_debit) : ($ytd_debit - $ytd_credit);

                // Store with period breakdown
                $category_balances[$category->id] = [
                    'last_month' => $lm_balance,
                    'current_month' => $cm_balance,
                    'ytd' => $ytd_balance,
                ];
                $total_balance += $ytd_balance;

                if ($ytd_balance != 0 || $lm_balance != 0 || $cm_balance != 0) {
                    $has_any_balance = true;
                }
            }

            // DB::enableQueryLog();

            // Pro Shop: Semua transaksi type = 'sell' (POS) with period breakdown
            $getProShopBaseQuery = function () use ($account) {
                return DB::table('accounting_accounts_transactions')
                    ->join('transactions', 'accounting_accounts_transactions.transaction_id', '=', 'transactions.id')
                    ->where('accounting_accounts_transactions.accounting_account_id', $account->id)
                    ->where(function ($q) {
                        $q->whereNull('accounting_accounts_transactions.sub_type')
                            ->orWhere('accounting_accounts_transactions.sub_type', '!=', 'opening_balance');
                    })
                    ->where('transactions.type', 'sell')
                    ->whereNull('transactions.gym_package_id');
            };

            // Pro Shop - Last Month
            $ps_lm_balance = 0;
            if ($lm_start && $lm_end) {
                $ps_lm_query = $getProShopBaseQuery()
                    ->whereDate('accounting_accounts_transactions.operation_date', '>=', $lm_start)
                    ->whereDate('accounting_accounts_transactions.operation_date', '<=', $lm_end);
                $ps_lm_period = $ps_lm_query->select(
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'debit', accounting_accounts_transactions.amount, 0)) as debit"),
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'credit', accounting_accounts_transactions.amount, 0)) as credit")
                )->first();
                $ps_lm_balance = ($account->account_primary_type == 'income')
                    ? (($ps_lm_period->credit ?? 0) - ($ps_lm_period->debit ?? 0))
                    : (($ps_lm_period->debit ?? 0) - ($ps_lm_period->credit ?? 0));
            }

            // Pro Shop - Current Month
            $ps_cm_balance = 0;
            if ($cm_start && $cm_end) {
                $ps_cm_query = $getProShopBaseQuery()
                    ->whereDate('accounting_accounts_transactions.operation_date', '>=', $cm_start)
                    ->whereDate('accounting_accounts_transactions.operation_date', '<=', $cm_end);
                $ps_cm_period = $ps_cm_query->select(
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'debit', accounting_accounts_transactions.amount, 0)) as debit"),
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'credit', accounting_accounts_transactions.amount, 0)) as credit")
                )->first();
                $ps_cm_balance = ($account->account_primary_type == 'income')
                    ? (($ps_cm_period->credit ?? 0) - ($ps_cm_period->debit ?? 0))
                    : (($ps_cm_period->debit ?? 0) - ($ps_cm_period->credit ?? 0));
            }

            // Pro Shop - YTD
            $ps_ytd_query = $getProShopBaseQuery()
                ->whereDate('accounting_accounts_transactions.operation_date', '>=', $start_date)
                ->whereDate('accounting_accounts_transactions.operation_date', '<=', $end_date);
            $ps_ytd_period = $ps_ytd_query->select(
                DB::raw("SUM(IF(accounting_accounts_transactions.type = 'debit', accounting_accounts_transactions.amount, 0)) as debit"),
                DB::raw("SUM(IF(accounting_accounts_transactions.type = 'credit', accounting_accounts_transactions.amount, 0)) as credit")
            )->first();
            $ps_ytd_balance = ($account->account_primary_type == 'income')
                ? (($ps_ytd_period->credit ?? 0) - ($ps_ytd_period->debit ?? 0))
                : (($ps_ytd_period->debit ?? 0) - ($ps_ytd_period->credit ?? 0));

            $category_balances['pro_shop'] = [
                'last_month' => $ps_lm_balance,
                'current_month' => $ps_cm_balance,
                'ytd' => $ps_ytd_balance,
            ];
            $total_balance += $ps_ytd_balance;
            if ($ps_ytd_balance != 0 || $ps_lm_balance != 0 || $ps_cm_balance != 0) {
                $has_any_balance = true;
            }

            // Sudest Café: placeholder with period structure
            $category_balances['sudest_cafe'] = [
                'last_month' => 0,
                'current_month' => 0,
                'ytd' => 0,
            ];

            // Other: Journal entries and transactions that don't belong to gym categories or Pro Shop
            $getOtherBaseQuery = function () use ($account) {
                return DB::table('accounting_accounts_transactions')
                    ->leftJoin('transactions', 'accounting_accounts_transactions.transaction_id', '=', 'transactions.id')
                    ->leftJoin('gym_packages', 'transactions.gym_package_id', '=', 'gym_packages.id')
                    ->where('accounting_accounts_transactions.accounting_account_id', $account->id)
                    ->where(function ($q) {
                        $q->whereNull('accounting_accounts_transactions.sub_type')
                            ->orWhere('accounting_accounts_transactions.sub_type', '!=', 'opening_balance');
                    })
                    ->whereNull('gym_packages.gym_category_id')
                    ->where(function ($q) {
                        $q->whereNull('transactions.id')
                            ->orWhere('transactions.type', '!=', 'sell')
                            ->orWhereNotNull('transactions.gym_package_id');
                    });
            };

            // Other - Last Month
            $other_lm_balance = 0;
            if ($lm_start && $lm_end) {
                $other_lm_query = $getOtherBaseQuery()
                    ->whereDate('accounting_accounts_transactions.operation_date', '>=', $lm_start)
                    ->whereDate('accounting_accounts_transactions.operation_date', '<=', $lm_end);
                $other_lm_period = $other_lm_query->select(
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'debit', accounting_accounts_transactions.amount, 0)) as debit"),
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'credit', accounting_accounts_transactions.amount, 0)) as credit")
                )->first();
                $other_lm_balance = ($account->account_primary_type == 'income')
                    ? (($other_lm_period->credit ?? 0) - ($other_lm_period->debit ?? 0))
                    : (($other_lm_period->debit ?? 0) - ($other_lm_period->credit ?? 0));
            }

            // Other - Current Month
            $other_cm_balance = 0;
            if ($cm_start && $cm_end) {
                $other_cm_query = $getOtherBaseQuery()
                    ->whereDate('accounting_accounts_transactions.operation_date', '>=', $cm_start)
                    ->whereDate('accounting_accounts_transactions.operation_date', '<=', $cm_end);
                $other_cm_period = $other_cm_query->select(
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'debit', accounting_accounts_transactions.amount, 0)) as debit"),
                    DB::raw("SUM(IF(accounting_accounts_transactions.type = 'credit', accounting_accounts_transactions.amount, 0)) as credit")
                )->first();
                $other_cm_balance = ($account->account_primary_type == 'income')
                    ? (($other_cm_period->credit ?? 0) - ($other_cm_period->debit ?? 0))
                    : (($other_cm_period->debit ?? 0) - ($other_cm_period->credit ?? 0));
            }

            // Other - YTD
            $other_ytd_query = $getOtherBaseQuery()
                ->whereDate('accounting_accounts_transactions.operation_date', '>=', $start_date)
                ->whereDate('accounting_accounts_transactions.operation_date', '<=', $end_date);
            $other_ytd_period = $other_ytd_query->select(
                DB::raw("SUM(IF(accounting_accounts_transactions.type = 'debit', accounting_accounts_transactions.amount, 0)) as debit"),
                DB::raw("SUM(IF(accounting_accounts_transactions.type = 'credit', accounting_accounts_transactions.amount, 0)) as credit")
            )->first();
            $other_ytd_balance = ($account->account_primary_type == 'income')
                ? (($other_ytd_period->credit ?? 0) - ($other_ytd_period->debit ?? 0))
                : (($other_ytd_period->debit ?? 0) - ($other_ytd_period->credit ?? 0));

            $category_balances['other'] = [
                'last_month' => $other_lm_balance,
                'current_month' => $other_cm_balance,
                'ytd' => $other_ytd_balance,
            ];
            $total_balance += $other_ytd_balance;
            if ($other_ytd_balance != 0 || $other_lm_balance != 0 || $other_cm_balance != 0) {
                $has_any_balance = true;
            }

            // Only include accounts with balance
            if ($has_any_balance) {
                $account_data = (object) [
                    'gl_code' => $account->gl_code,
                    'name' => $account->name,
                    'account_primary_type' => $account->account_primary_type,
                    'category_balances' => $category_balances,
                    'balance' => $total_balance,
                ];

                if ($account->account_primary_type == 'income') {
                    $income_accounts->push($account_data);
                    $total_income += $total_balance;
                    // Update category totals with period breakdown
                    foreach ($gym_categories as $category) {
                        foreach ($period_keys as $pk) {
                            $category_totals['income'][$category->id][$pk] += $category_balances[$category->id][$pk];
                        }
                    }
                    foreach ($period_keys as $pk) {
                        $category_totals['income']['pro_shop'][$pk] += $category_balances['pro_shop'][$pk];
                        $category_totals['income']['sudest_cafe'][$pk] += $category_balances['sudest_cafe'][$pk];
                        $category_totals['income']['other'][$pk] += $category_balances['other'][$pk];
                    }
                } else {
                    $expense_accounts->push($account_data);
                    $total_expense += $total_balance;
                    // Update category totals with period breakdown
                    foreach ($gym_categories as $category) {
                        foreach ($period_keys as $pk) {
                            $category_totals['expense'][$category->id][$pk] += $category_balances[$category->id][$pk];
                        }
                    }
                    foreach ($period_keys as $pk) {
                        $category_totals['expense']['pro_shop'][$pk] += $category_balances['pro_shop'][$pk];
                        $category_totals['expense']['sudest_cafe'][$pk] += $category_balances['sudest_cafe'][$pk];
                        $category_totals['expense']['other'][$pk] += $category_balances['other'][$pk];
                    }
                }
            }
        }

        $net_profit = $total_income - $total_expense;

        // Calculate net profit per category with period breakdown
        $category_net_profit = [];
        foreach ($gym_categories as $category) {
            $category_net_profit[$category->id] = [
                'last_month' => $category_totals['income'][$category->id]['last_month'] - $category_totals['expense'][$category->id]['last_month'],
                'current_month' => $category_totals['income'][$category->id]['current_month'] - $category_totals['expense'][$category->id]['current_month'],
                'ytd' => $category_totals['income'][$category->id]['ytd'] - $category_totals['expense'][$category->id]['ytd'],
            ];
        }
        $category_net_profit['pro_shop'] = [
            'last_month' => $category_totals['income']['pro_shop']['last_month'] - $category_totals['expense']['pro_shop']['last_month'],
            'current_month' => $category_totals['income']['pro_shop']['current_month'] - $category_totals['expense']['pro_shop']['current_month'],
            'ytd' => $category_totals['income']['pro_shop']['ytd'] - $category_totals['expense']['pro_shop']['ytd'],
        ];
        $category_net_profit['sudest_cafe'] = [
            'last_month' => $category_totals['income']['sudest_cafe']['last_month'] - $category_totals['expense']['sudest_cafe']['last_month'],
            'current_month' => $category_totals['income']['sudest_cafe']['current_month'] - $category_totals['expense']['sudest_cafe']['current_month'],
            'ytd' => $category_totals['income']['sudest_cafe']['ytd'] - $category_totals['expense']['sudest_cafe']['ytd'],
        ];
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
                'gym_categories',
                'category_totals',
                'category_net_profit'
            ));
    }
}
