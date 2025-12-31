@extends('layouts.app')

@section('title', __('accounting::lang.pnl_ytd'))

@section('content')

@include('accounting::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1>@lang( 'accounting::lang.pnl_ytd' )</h1>
</section>

<section class="content no-print" style="min-height:auto !important;">
    @php
        $selected_end = \Carbon\Carbon::parse($end_date);
        $selected_month = $selected_end->format('m');
        $selected_year = $selected_end->format('Y');

        $year_start = (int) \Carbon\Carbon::parse($start_date)->format('Y');
        $year_end = (int) $selected_year;
        if ($year_start > $year_end) {
            $tmp = $year_start;
            $year_start = $year_end;
            $year_end = $tmp;
        }

        $year_options = [];
        for ($year = $year_start; $year <= $year_end + 2; $year++) {
            $year_options[$year] = $year;
        }

        $month_options = [];
        for ($month = 1; $month <= 12; $month++) {
            $value = str_pad($month, 2, '0', STR_PAD_LEFT);
            $month_options[$value] = \Carbon\Carbon::createFromDate(null, $month, 1)->translatedFormat('F');
        }
    @endphp
    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                <div class="row">
                    <div class="col-xs-6">
                        {!! Form::label('month_filter', __('lang_v1.month') . ':') !!}
                        {!! Form::select('month_filter', $month_options, $selected_month, ['class' => 'form-control', 'id' => 'month_filter']) !!}
                    </div>
                    <div class="col-xs-6">
                        {!! Form::label('year_filter', __('lang_v1.year') . ':') !!}
                        {!! Form::select('year_filter', $year_options, $selected_year, ['class' => 'form-control', 'id' => 'year_filter']) !!}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('gym_category_id', __('gym::lang.gym_category') . ':') !!}
                {!! Form::select('gym_category_id', $gym_categories, $gym_category_id, 
                    ['class' => 'form-control select2', 'style' => 'width:100%', 'id' => 'gym_category_filter', 'placeholder' => __('messages.all')]); !!}
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label>&nbsp;</label>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="show_difference_columns" checked> 
                        <strong>Tampilkan Kolom Selisih</strong>
                    </label>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label>&nbsp;</label>
                <button class="btn btn-success btn-block" id="export_all_excel">
                    <i class="fa fa-file-excel-o"></i> Export Excel
                </button>
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label>&nbsp;</label>
                <button class="btn btn-primary btn-block" onclick="window.print();">
                    <i class="fa fa-print"></i> @lang('messages.print')
                </button>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="print_section">
        <h2>{{ session()->get('business.name') }} - @lang('accounting::lang.pnl_ytd')</h2>
        <p>{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</p>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-success">
                <div class="box-header with-border text-center">
                    <h2 class="box-title">@lang( 'accounting::lang.pnl_ytd')</h2>
                    <p>{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</p>
                    <p class="text-muted"><small>* Nilai menunjukkan saldo kumulatif Year-to-Date (YTD)</small></p>
                </div>
    
                <div class="box-body table-responsive">
                    @php
                        $month_count = count($months);
                        $current_month = $month_count > 0 ? $months[$month_count - 1] : null;
                        $last_month = $month_count > 1 ? $months[$month_count - 2] : null;
                        $current_key = $current_month['key'] ?? null;
                        $last_key = $last_month['key'] ?? null;
                        $period_start = $month_count > 0 ? \Carbon\Carbon::parse($months[0]['start']) : null;
                        
                        // Label untuk kolom
                        // Last Month = hanya bulan sebelumnya saja
                        $last_range_label = $last_month
                            ? \Carbon\Carbon::parse($last_month['start'])->format('M Y')
                            : '-';
                        // Current Month = hanya bulan filter saja
                        $current_month_label = $current_month
                            ? \Carbon\Carbon::parse($current_month['start'])->format('M Y')
                            : '-';
                        // YTD = akumulasi dari awal tahun sampai bulan filter
                        $current_range_label = ($current_month && $period_start)
                            ? 'YTD s.d. ' . \Carbon\Carbon::parse($current_month['end'])->format('M Y')
                            : '-';
                    @endphp

                    {{-- Income Section --}}
                    <h4 class="text-success"><strong><i class="fa fa-arrow-up"></i> @lang('accounting::lang.income') (YTD)</strong></h4>
                    <table class="table table-striped table-bordered" id="income_report_table" style="width:100%">
                        <thead>
                            <tr class="success">
                                <th class="text-center align-middle" style="vertical-align: middle;">@lang('accounting::lang.gl_code')</th>
                                <th class="text-center align-middle" style="vertical-align: middle;">@lang('user.name')</th>
                                <th class="text-center align-middle bg-info" style="vertical-align: middle;">
                                    Last Month
                                    <div class="text-muted" style="font-size: 11px;">{{ $last_range_label }}</div>
                                </th>
                                <th class="text-center align-middle bg-info" style="vertical-align: middle;">
                                    Current Month
                                    <div class="text-muted" style="font-size: 11px;">{{ $current_month_label }}</div>
                                </th>
                                <th class="text-center align-middle bg-info" style="vertical-align: middle;">
                                    YTD
                                    <div class="text-muted" style="font-size: 11px;">{{ $current_range_label }}</div>
                                </th>
                                <th class="text-center align-middle bg-warning diff-col" style="vertical-align: middle;">Varian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($income_accounts as $account)
                                @php
                                    // monthly_balances berisi nilai kumulatif YTD per bulan
                                    $last_balance = 0;
                                    $current_month_balance = 0;
                                    $current_balance = 0;
                                    
                                    // YTD = nilai akumulasi sampai bulan filter
                                    if ($current_key) {
                                        $current_balance = $account->monthly_balances[$current_key] ?? 0;
                                        
                                        // Current Month = hanya nilai bulan filter saja
                                        // Rumus: YTD bulan ini - YTD bulan lalu = nilai bulan ini
                                        $current_ytd = $current_balance;
                                        $prev_current_ytd = 0;
                                        foreach ($months as $idx => $month) {
                                            if ($month['key'] === $current_key && $idx > 0) {
                                                $prev_current_ytd = $account->monthly_balances[$months[$idx - 1]['key']] ?? 0;
                                                break;
                                            }
                                        }
                                        $current_month_balance = $current_ytd - $prev_current_ytd;
                                    }
                                    
                                    // Last Month = hanya nilai bulan sebelumnya saja
                                    // Rumus: YTD bulan lalu - YTD 2 bulan lalu = nilai bulan lalu
                                    if ($last_key) {
                                        $last_ytd = $account->monthly_balances[$last_key] ?? 0;
                                        $prev_last_ytd = 0;
                                        foreach ($months as $idx => $month) {
                                            if ($month['key'] === $last_key && $idx > 0) {
                                                $prev_last_ytd = $account->monthly_balances[$months[$idx - 1]['key']] ?? 0;
                                                break;
                                            }
                                        }
                                        $last_balance = $last_ytd - $prev_last_ytd;
                                    }
                                    
                                    // Koreksi: varian = current month - last month
                                    $difference = $current_month_balance - $last_balance;
                                    $diff_color = $difference > 0 ? 'text-success' : ($difference < 0 ? 'text-danger' : '');
                                @endphp
                                <tr>
                                    <td>{{ $account->gl_code }}</td>
                                    <td>{{ $account->name }}</td>
                                    <td class="text-right month-col">
                                        <span data-orig-value="{{ $last_balance }}">@format_currency($last_balance)</span>
                                    </td>
                                    <td class="text-right month-col">
                                        <span data-orig-value="{{ $current_month_balance }}">@format_currency($current_month_balance)</span>
                                    </td>
                                    <td class="text-right month-col">
                                        <span data-orig-value="{{ $current_balance }}">@format_currency($current_balance)</span>
                                    </td>
                                    <td class="text-right diff-col {{ $diff_color }}">
                                        @if($difference > 0)
                                            <i class="fa fa-arrow-up"></i>
                                        @elseif($difference < 0)
                                            <i class="fa fa-arrow-down"></i>
                                        @endif
                                        @format_currency(abs($difference))
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">@lang('lang_v1.no_data')</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="success">
                                <th colspan="2" class="text-right"><strong>@lang('accounting::lang.total_income')</strong></th>
                                @php
                                    $last_income_total = 0;
                                    $current_month_income_total = 0;
                                    $current_income_total = 0;
                                    
                                    // YTD = total kumulatif dari awal tahun
                                    if ($current_key) {
                                        $current_income_total = $monthly_totals['income'][$current_key] ?? 0;
                                        
                                        // Current Month = hanya bulan filter saja
                                        $current_ytd = $current_income_total;
                                        $prev_current_ytd = 0;
                                        foreach ($months as $idx => $month) {
                                            if ($month['key'] === $current_key && $idx > 0) {
                                                $prev_current_ytd = $monthly_totals['income'][$months[$idx - 1]['key']] ?? 0;
                                                break;
                                            }
                                        }
                                        $current_month_income_total = $current_ytd - $prev_current_ytd;
                                    }
                                    
                                    // Last Month = hanya bulan sebelumnya saja
                                    if ($last_key) {
                                        $last_ytd = $monthly_totals['income'][$last_key] ?? 0;
                                        $prev_last_ytd = 0;
                                        foreach ($months as $idx => $month) {
                                            if ($month['key'] === $last_key && $idx > 0) {
                                                $prev_last_ytd = $monthly_totals['income'][$months[$idx - 1]['key']] ?? 0;
                                                break;
                                            }
                                        }
                                        $last_income_total = $last_ytd - $prev_last_ytd;
                                    }
                                    
                                    $income_diff = $current_income_total - $last_income_total;
                                @endphp
                                <th class="text-right month-col">@format_currency($last_income_total)</th>
                                <th class="text-right month-col">@format_currency($current_month_income_total)</th>
                                <th class="text-right month-col">@format_currency($current_income_total)</th>
                                <th class="text-right diff-col {{ $income_diff > 0 ? 'text-success' : ($income_diff < 0 ? 'text-danger' : '') }}" style="background-color: #d4edda;">
                                    @if($income_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($income_diff < 0) <i class="fa fa-arrow-down"></i> @endif
                                    @format_currency(abs($income_diff))
                                </th>
                            </tr>
                        </tfoot>
                    </table>

                    <br/>

                    {{-- Expense Section --}}
                    <h4 class="text-danger"><strong><i class="fa fa-arrow-down"></i> @lang('accounting::lang.expenses') (YTD)</strong></h4>
                    <table class="table table-striped table-bordered" id="expense_report_table" style="width:100%">
                        <thead>
                            <tr class="gray">
                                <th class="text-center align-middle" style="vertical-align: middle;">@lang('accounting::lang.gl_code')</th>
                                <th class="text-center align-middle" style="vertical-align: middle;">@lang('user.name')</th>
                                <th class="text-center align-middle bg-info" style="vertical-align: middle;">
                                    Last Month
                                    <div class="text-muted" style="font-size: 11px;">{{ $last_range_label }}</div>
                                </th>
                                <th class="text-center align-middle bg-info" style="vertical-align: middle;">
                                    Current Month
                                    <div class="text-muted" style="font-size: 11px;">{{ $current_month_label }}</div>
                                </th>
                                <th class="text-center align-middle bg-info" style="vertical-align: middle;">
                                    YTD
                                    <div class="text-muted" style="font-size: 11px;">{{ $current_range_label }}</div>
                                </th>
                                <th class="text-center align-middle bg-warning diff-col" style="vertical-align: middle;">Varian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expense_accounts as $account)
                                @php
                                    // monthly_balances berisi nilai kumulatif YTD per bulan
                                    $last_balance = 0;
                                    $current_month_balance = 0;
                                    $current_balance = 0;
                                    
                                    // YTD = nilai akumulasi sampai bulan filter
                                    if ($current_key) {
                                        $current_balance = $account->monthly_balances[$current_key] ?? 0;
                                        
                                        // Current Month = hanya nilai bulan filter saja
                                        // Rumus: YTD bulan ini - YTD bulan lalu = nilai bulan ini
                                        $current_ytd = $current_balance;
                                        $prev_current_ytd = 0;
                                        foreach ($months as $idx => $month) {
                                            if ($month['key'] === $current_key && $idx > 0) {
                                                $prev_current_ytd = $account->monthly_balances[$months[$idx - 1]['key']] ?? 0;
                                                break;
                                            }
                                        }
                                        $current_month_balance = $current_ytd - $prev_current_ytd;
                                    }
                                    
                                    // Last Month = hanya nilai bulan sebelumnya saja
                                    // Rumus: YTD bulan lalu - YTD 2 bulan lalu = nilai bulan lalu
                                    if ($last_key) {
                                        $last_ytd = $account->monthly_balances[$last_key] ?? 0;
                                        $prev_last_ytd = 0;
                                        foreach ($months as $idx => $month) {
                                            if ($month['key'] === $last_key && $idx > 0) {
                                                $prev_last_ytd = $account->monthly_balances[$months[$idx - 1]['key']] ?? 0;
                                                break;
                                            }
                                        }
                                        $last_balance = $last_ytd - $prev_last_ytd;
                                    }
                                    
                                    // Koreksi: varian = current month - last month
                                    $difference = $current_month_balance - $last_balance;
                                    // For expenses, more is bad (red), less is good (green)
                                    $diff_color = $difference > 0 ? 'text-danger' : ($difference < 0 ? 'text-success' : '');
                                @endphp
                                <tr>
                                    <td>{{ $account->gl_code }}</td>
                                    <td>{{ $account->name }}</td>
                                    <td class="text-right month-col">
                                        <span data-orig-value="{{ $last_balance }}">@format_currency($last_balance)</span>
                                    </td>
                                    <td class="text-right month-col">
                                        <span data-orig-value="{{ $current_month_balance }}">@format_currency($current_month_balance)</span>
                                    </td>
                                    <td class="text-right month-col">
                                        <span data-orig-value="{{ $current_balance }}">@format_currency($current_balance)</span>
                                    </td>
                                    <td class="text-right diff-col {{ $diff_color }}">
                                        @if($difference > 0)
                                            <i class="fa fa-arrow-up"></i>
                                        @elseif($difference < 0)
                                            <i class="fa fa-arrow-down"></i>
                                        @endif
                                        @format_currency(abs($difference))
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">@lang('lang_v1.no_data')</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="gray">
                                <th colspan="2" class="text-right"><strong>@lang('accounting::lang.total_expenses')</strong></th>
                                @php
                                    $last_expense_total = 0;
                                    $current_month_expense_total = 0;
                                    $current_expense_total = 0;
                                    
                                    // YTD = total kumulatif dari awal tahun
                                    if ($current_key) {
                                        $current_expense_total = $monthly_totals['expense'][$current_key] ?? 0;
                                        
                                        // Current Month = hanya bulan filter saja
                                        $current_ytd = $current_expense_total;
                                        $prev_current_ytd = 0;
                                        foreach ($months as $idx => $month) {
                                            if ($month['key'] === $current_key && $idx > 0) {
                                                $prev_current_ytd = $monthly_totals['expense'][$months[$idx - 1]['key']] ?? 0;
                                                break;
                                            }
                                        }
                                        $current_month_expense_total = $current_ytd - $prev_current_ytd;
                                    }
                                    
                                    // Last Month = hanya bulan sebelumnya saja
                                    if ($last_key) {
                                        $last_ytd = $monthly_totals['expense'][$last_key] ?? 0;
                                        $prev_last_ytd = 0;
                                        foreach ($months as $idx => $month) {
                                            if ($month['key'] === $last_key && $idx > 0) {
                                                $prev_last_ytd = $monthly_totals['expense'][$months[$idx - 1]['key']] ?? 0;
                                                break;
                                            }
                                        }
                                        $last_expense_total = $last_ytd - $prev_last_ytd;
                                    }
                                    
                                    $expense_diff = $current_expense_total - $last_expense_total;
                                @endphp
                                <th class="text-right month-col">@format_currency($last_expense_total)</th>
                                <th class="text-right month-col">@format_currency($current_month_expense_total)</th>
                                <th class="text-right month-col">@format_currency($current_expense_total)</th>
                                <th class="text-right diff-col {{ $expense_diff > 0 ? 'text-danger' : ($expense_diff < 0 ? 'text-success' : '') }}" style="background-color: #fff3cd;">
                                    @if($expense_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($expense_diff < 0) <i class="fa fa-arrow-down"></i> @endif
                                    @format_currency(abs($expense_diff))
                                </th>
                            </tr>
                        </tfoot>
                    </table>

                    <br/>

                    {{-- Net Profit/Loss Summary --}}
                    <h4><strong><i class="fa fa-calculator"></i> @lang('accounting::lang.net_profit') / @lang('accounting::lang.net_loss') (YTD)</strong></h4>
                    <table class="table table-bordered" id="net_profit_table" style="width:100%">
                        <thead>
                            <tr class="{{ $net_profit >= 0 ? 'bg-green' : 'bg-red' }}">
                                <th class="text-center align-middle" style="vertical-align: middle;">
                                    <strong>
                                        @if($net_profit >= 0)
                                            <i class="fa fa-check-circle"></i> @lang('accounting::lang.net_profit')
                                        @else
                                            <i class="fa fa-times-circle"></i> @lang('accounting::lang.net_loss')
                                        @endif
                                    </strong>
                                </th>
                                <th class="text-center align-middle bg-info" style="vertical-align: middle;">
                                    Last Month
                                    <div class="text-muted" style="font-size: 11px;">{{ $last_range_label }}</div>
                                </th>
                                <th class="text-center align-middle bg-info" style="vertical-align: middle;">
                                    Current Month
                                    <div class="text-muted" style="font-size: 11px;">{{ $current_month_label }}</div>
                                </th>
                                <th class="text-center align-middle bg-info" style="vertical-align: middle;">
                                    YTD
                                    <div class="text-muted" style="font-size: 11px;">{{ $current_range_label }}</div>
                                </th>
                                <th class="text-center align-middle bg-warning diff-col" style="vertical-align: middle;">Varian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $last_net = $last_income_total - $last_expense_total;
                                $current_month_net = $current_month_income_total - $current_month_expense_total;
                                $current_net = $current_income_total - $current_expense_total;
                                // Koreksi: varian = current month - last month
                                $net_diff = $current_month_net - $last_net;
                                $net_diff_color = $net_diff > 0 ? 'text-success' : ($net_diff < 0 ? 'text-danger' : '');
                            @endphp
                            <tr class="{{ $net_profit >= 0 ? 'bg-cyan' : 'bg-blue' }}">
                                <td style="font-size: 16px;"><strong>Nilai</strong></td>
                                <td class="text-right month-col" style="font-size: 14px;">
                                    <strong class="{{ $last_net >= 0 ? '' : 'text-danger' }}">
                                        <span data-orig-value="{{ $last_net }}">@format_currency($last_net)</span>
                                    </strong>
                                </td>
                                <td class="text-right month-col" style="font-size: 14px;">
                                    <strong class="{{ $current_month_net >= 0 ? '' : 'text-danger' }}">
                                        <span data-orig-value="{{ $current_month_net }}">@format_currency($current_month_net)</span>
                                    </strong>
                                </td>
                                <td class="text-right month-col" style="font-size: 14px;">
                                    <strong class="{{ $current_net >= 0 ? '' : 'text-danger' }}">
                                        <span data-orig-value="{{ $current_net }}">@format_currency($current_net)</span>
                                    </strong>
                                </td>
                                <td class="text-right diff-col {{ $net_diff_color }}" style="background-color: {{ $net_diff >= 0 ? '#d4edda' : '#f8d7da' }};">
                                    @if($net_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($net_diff < 0) <i class="fa fa-arrow-down"></i> @endif
                                    @format_currency(abs($net_diff))
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    {{-- Summary --}}
                    <div class="well well-sm">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <h5>@lang('accounting::lang.total_income') (YTD)</h5>
                                <h3 class="text-success">@format_currency($total_income)</h3>
                            </div>
                            <div class="col-md-4 text-center">
                                <h5>@lang('accounting::lang.total_expenses') (YTD)</h5>
                                <h3 class="text-primary">@format_currency($total_expense)</h3>
                            </div>
                            <div class="col-md-4 text-center">
                                <h5>{{ $net_profit >= 0 ? __('accounting::lang.net_profit') : __('accounting::lang.net_loss') }} (YTD)</h5>
                                <h3 class="{{ $net_profit >= 0 ? 'text-success' : 'text-danger' }}">@format_currency(abs($net_profit))</h3>
                            </div>
                        </div>
                    </div>
                </div>
    
            </div>
        </div>
    </div>
</section>

@stop

@section('javascript')

<script type="text/javascript">
    $(document).ready(function(){

        // Helper function to strip HTML tags
        function stripHtml(html) {
            var tmp = document.createElement("DIV");
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || "";
        }

        // Helper function to parse Indonesian formatted number
        function parseIndonesianNumber(str) {
            if (!str) return 0;
            var cleaned = stripHtml(str);
            cleaned = cleaned.replace(/Rp\s*/gi, '').trim();
            cleaned = cleaned.replace(/[↑↓▲▼]/g, '').trim();
            
            if (!cleaned.match(/^-?[\d.,]+$/)) {
                return 0;
            }
            
            var lastDot = cleaned.lastIndexOf('.');
            var lastComma = cleaned.lastIndexOf(',');
            
            if (lastDot > lastComma) {
                cleaned = cleaned.replace(/,/g, '');
            } else if (lastComma > lastDot) {
                cleaned = cleaned.replace(/\./g, '').replace(',', '.');
            } else if (lastDot !== -1) {
                if (cleaned.match(/\.\d{2}$/)) {
                    // Do nothing
                } else {
                    cleaned = cleaned.replace(/\./g, '');
                }
            } else if (lastComma !== -1) {
                cleaned = cleaned.replace(',', '.');
            }
            
            var num = parseFloat(cleaned);
            return isNaN(num) ? 0 : num;
        }

        // Initialize DataTables with export buttons
        var incomeTable = $('#income_report_table').DataTable({
            dom: 'rtip',
            paging: false,
            searching: false,
            info: false,
            ordering: true,
            order: [[0, 'asc']]
        });

        var expenseTable = $('#expense_report_table').DataTable({
            dom: 'rtip',
            paging: false,
            searching: false,
            info: false,
            ordering: true,
            order: [[0, 'asc']]
        });

        // Export All Tables to Excel
        $('#export_all_excel').on('click', function() {
            var colCount = 6; // GL Code, Name, Last Month, Current Month, YTD, Varian
            
            var incomeTable = document.getElementById('income_report_table').cloneNode(true);
            var expenseTable = document.getElementById('expense_report_table').cloneNode(true);
            var netProfitTable = document.getElementById('net_profit_table').cloneNode(true);
            
            [incomeTable, expenseTable, netProfitTable].forEach(function(table) {
                $(table).find('td, th').each(function() {
                    var text = $(this).text().trim();
                    if (text.match(/Rp\s*-?[\d.,]+/) || text.match(/^-?[\d.,]+$/)) {
                        var num = parseIndonesianNumber(text);
                        $(this).text(num);
                    } else {
                        $(this).text(text);
                    }
                });
            });
            
            var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            html += '<head><meta charset="utf-8"></head><body>';
            html += '<table>';
            html += '<tr><th colspan="' + colCount + '" style="text-align:center; font-size:18px; font-weight:bold;">P&L YTD Report</th></tr>';
            html += '<tr><th colspan="' + colCount + '" style="text-align:center;">{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</th></tr>';
            html += '<tr><td colspan="' + colCount + '">&nbsp;</td></tr>';
            html += '<tr><th colspan="' + colCount + '" style="background-color:#d4edda; font-weight:bold;">INCOME</th></tr>';
            html += '</table>';
            html += incomeTable.outerHTML;
            html += '<table><tr><td colspan="' + colCount + '">&nbsp;</td></tr>';
            html += '<tr><th colspan="' + colCount + '" style="background-color:#f8d7da; font-weight:bold;">EXPENSE</th></tr></table>';
            html += expenseTable.outerHTML;
            html += '<table><tr><td colspan="' + colCount + '">&nbsp;</td></tr>';
            html += '<tr><th colspan="' + colCount + '" style="background-color:#cce5ff; font-weight:bold;">NET PROFIT / NET LOSS</th></tr></table>';
            html += netProfitTable.outerHTML;
            html += '</body></html>';
            
            var blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            var url = URL.createObjectURL(blob);
            var downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            downloadLink.href = url;
            downloadLink.download = 'PNL_YTD_Report_{{$start_date}}_to_{{$end_date}}.xls';
            downloadLink.click();
            document.body.removeChild(downloadLink);
            URL.revokeObjectURL(url);
        });

        // Toggle difference columns visibility
        $('#show_difference_columns').on('change', function() {
            if ($(this).is(':checked')) {
                $('.diff-col').show();
            } else {
                $('.diff-col').hide();
            }
            incomeTable.columns.adjust().draw();
            expenseTable.columns.adjust().draw();
        });

        // Month/Year filter change handler
        $('#month_filter, #year_filter').on('change', function() {
            apply_filter();
        });

        // Gym Category filter change handler
        $('#gym_category_filter').on('change', function() {
            apply_filter();
        });

        function apply_filter(){
            var month = $('#month_filter').val();
            var year = $('#year_filter').val();
            var end_date = year + '-' + month + '-' + new Date(year, month, 0).getDate();
            var gym_category_id = $('#gym_category_filter').val();

            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('end_date', end_date);
            if (gym_category_id) {
                urlParams.set('gym_category_id', gym_category_id);
            } else {
                urlParams.delete('gym_category_id');
            }
            window.location.search = urlParams;
        }
    });
</script>

<style>
    .table th, .table td {
        white-space: nowrap;
    }

    .table thead th {
        text-align: center;
        vertical-align: middle;
    }
    
    .text-success {
        color: #28a745 !important;
    }
    
    .text-danger {
        color: #dc3545 !important;
    }
    
    .diff-col {
        background-color: #fff8e1;
    }

    .bg-green {
        background-color: #00a65a !important;
        color: #fff;
    }

    .bg-red {
        background-color: #dd4b39 !important;
        color: #fff;
    }

    .bg-blue {
        background-color: #3099c4 !important;
        color: #fff;
    }

    .bg-cyan {
        background-color: #00c6ff !important;
        color: #fff;
    }

    .table>thead>tr>td.gray, .table>thead>tr.gray>th {
        background-color: #cecece;
    }

    .bg-green th, .bg-green td,
    .bg-red th, .bg-red td {
        color: #fff;
    }

    @media print {
        .no-print {
            display: none !important;
        }
        .print_section {
            display: block !important;
        }
    }

    .print_section {
        display: none;
        text-align: center;
        margin-bottom: 20px;
    }
</style>

@stop
