<?php

namespace Modules\Accounting\Http\Controllers;

use App\BusinessLocation;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Accounting\Entities\AccountingAccount;
use Modules\Accounting\Utils\AccountingUtil;

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
            ->with(compact('ledger_url','journal_entry_url'));
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

        // Handle month filter instead of date range
        if (!empty(request()->month)) {
            $month = request()->month; // Format: YYYY-MM
            $start_date = $month . '-01';
            $end_date = date('Y-m-t', strtotime($start_date)); // Last day of month
        } else {
            // Default to current month
            $start_date = date('Y-m-01');
            $end_date = date('Y-m-t');
            $month = date('Y-m');
        }

        // Calculate beginning balance date (end of previous month)
        $beginning_balance_date = date('Y-m-d', strtotime($start_date . ' -1 day'));

        // Get all active accounts
        $all_accounts = AccountingAccount::where('business_id', $business_id)
            ->where('status', 'active')
            ->select('id', 'name', 'gl_code')
            ->orderBy('gl_code')
            ->get();

        $accounts = collect();

        foreach ($all_accounts as $account) {
            // Calculate beginning balance (all transactions up to the day before start_date)
            $beginning = DB::table('accounting_accounts_transactions')
                ->where('accounting_account_id', $account->id)
                ->whereDate('operation_date', '<=', $beginning_balance_date)
                ->select(
                    DB::raw("SUM(IF(type = 'debit', amount, 0)) - SUM(IF(type = 'credit', amount, 0)) as balance")
                )
                ->first();

            // Calculate period transactions (debit and credit in the date range)
            $period = DB::table('accounting_accounts_transactions')
                ->where('accounting_account_id', $account->id)
                ->whereDate('operation_date', '>=', $start_date)
                ->whereDate('operation_date', '<=', $end_date)
                ->select(
                    DB::raw("SUM(IF(type = 'debit', amount, 0)) as debit"),
                    DB::raw("SUM(IF(type = 'credit', amount, 0)) as credit")
                )
                ->first();

            $beginning_balance = $beginning->balance ?? 0;
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

        return view('accounting::report.trial_balance')
            ->with(compact('accounts', 'start_date', 'end_date', 'month'));
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

        // Get location filter
        $location_id = request()->input('location_id', null);
        $business_locations = BusinessLocation::forDropdown($business_id, true);

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

        foreach ($all_accounts as $account) {
            $monthly_balances = [];
            $total_balance = 0;
            $has_any_balance = false;

            foreach ($months as $month) {
                // For P&L Report: Calculate ONLY period transactions (EXCLUDE opening_balance)
                // Join with transactions table to filter by location
                $query = DB::table('accounting_accounts_transactions')
                    ->where('accounting_accounts_transactions.accounting_account_id', $account->id)
                    ->where(function ($q) {
                        $q->whereNull('accounting_accounts_transactions.sub_type')
                            ->orWhere('accounting_accounts_transactions.sub_type', '!=', 'opening_balance');
                    })
                    ->whereDate('accounting_accounts_transactions.operation_date', '>=', $month['start'])
                    ->whereDate('accounting_accounts_transactions.operation_date', '<=', $month['end']);

                // Apply location filter if specified
                if (!empty($location_id)) {
                    $query->leftJoin('transactions', 'accounting_accounts_transactions.transaction_id', '=', 'transactions.id')
                        ->where(function ($q) use ($location_id) {
                            $q->where('transactions.location_id', $location_id)
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
            ->with(compact('income_accounts', 'expense_accounts', 'total_income', 'total_expense', 'net_profit', 'start_date', 'end_date', 'months', 'monthly_totals', 'business_locations', 'location_id'));
    }
}
