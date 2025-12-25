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
                    {{-- Export Buttons --}}
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-success" id="export_income_excel">
                                <i class="fa fa-file-excel-o"></i> Export Income (Excel)
                            </button>
                            <button type="button" class="btn btn-danger" id="export_expense_excel">
                                <i class="fa fa-file-excel-o"></i> Export Expense (Excel)
                            </button>
                            <button type="button" class="btn btn-primary" id="export_all_excel">
                                <i class="fa fa-file-excel-o"></i> Export All (Excel)
                            </button>
                        </div>
                    </div>

                    @php
                        $month_count = count($months);
                        $current_month = $month_count > 0 ? $months[$month_count - 1] : null;
                        $last_month = $month_count > 1 ? $months[$month_count - 2] : null;
                        $current_key = $current_month['key'] ?? null;
                        $last_key = $last_month['key'] ?? null;
                    @endphp

                    {{-- Income Section --}}
                    <h4 class="text-success"><strong><i class="fa fa-arrow-up"></i> @lang('accounting::lang.income') (YTD)</strong></h4>
                    <table class="table table-striped table-bordered" id="income_report_table">
                        <thead>
                            <tr class="success">
                                <th rowspan="2" style="width:120px; vertical-align: middle;">@lang('accounting::lang.gl_code')</th>
                                <th rowspan="2" style="vertical-align: middle;">@lang('user.name')</th>
                                @foreach($months as $index => $month)
                                    <th class="text-center month-col">{{ $month['label'] }}</th>
                                    @if($index > 0)
                                        <th class="text-center diff-col bg-warning">Δ MoM</th>
                                    @endif
                                @endforeach
                                <th class="text-right bg-primary" rowspan="2" style="width:150px; vertical-align: middle;">YTD Total</th>
                            </tr>
                            <tr class="success">
                                @foreach($months as $index => $month)
                                    <th class="text-center text-muted month-col" style="font-size: 10px;">
                                        s.d. {{ \Carbon\Carbon::parse($month['end'])->format('d M') }}
                                    </th>
                                    @if($index > 0)
                                        <th class="text-center text-muted diff-col" style="font-size: 10px;">
                                            vs {{ $months[$index - 1]['label'] }}
                                        </th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($income_accounts as $account)
                                <tr>
                                    <td>{{ $account->gl_code }}</td>
                                    <td>{{ $account->name }}</td>
                                    @foreach($months as $index => $month)
                                        @php
                                            $current_balance = $account->monthly_balances[$month['key']] ?? 0;
                                            $prev_balance = $index > 0 ? ($account->monthly_balances[$months[$index - 1]['key']] ?? 0) : 0;
                                            $difference = $current_balance - $prev_balance;
                                            $diff_color = $difference > 0 ? 'text-success' : ($difference < 0 ? 'text-danger' : '');
                                        @endphp
                                        <td class="text-right month-col">@format_currency($current_balance)</td>
                                        @if($index > 0)
                                            <td class="text-right diff-col {{ $diff_color }}">
                                                @if($difference > 0) <i class="fa fa-arrow-up"></i> @elseif($difference < 0) <i class="fa fa-arrow-down"></i> @endif
                                                @format_currency(abs($difference))
                                            </td>
                                        @endif
                                    @endforeach
                                    <td class="text-right"><strong>@format_currency($account->balance)</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 2 + count($months) + (count($months) > 0 ? count($months) - 1 : 0) + 1 }}" class="text-center text-muted">@lang('lang_v1.no_data')</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="success">
                                <th colspan="2" class="text-right"><strong>@lang('accounting::lang.total_income') (YTD)</strong></th>
                                @foreach($months as $index => $month)
                                    @php
                                        $current_income = $monthly_totals['income'][$month['key']] ?? 0;
                                        $prev_income = $index > 0 ? ($monthly_totals['income'][$months[$index - 1]['key']] ?? 0) : 0;
                                        $income_diff = $current_income - $prev_income;
                                        $income_diff_color = $income_diff > 0 ? 'text-success' : ($income_diff < 0 ? 'text-danger' : '');
                                    @endphp
                                    <th class="text-right month-col">@format_currency($current_income)</th>
                                    @if($index > 0)
                                        <th class="text-right diff-col {{ $income_diff_color }}" style="background-color: #00c6ff;">
                                            @if($income_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($income_diff < 0) <i class="fa fa-arrow-down"></i> @endif
                                            @format_currency(abs($income_diff))
                                        </th>
                                    @endif
                                @endforeach
                                <th class="text-right"><strong>@format_currency($total_income)</strong></th>
                            </tr>
                        </tfoot>
                    </table>

                    <br/>

                    {{-- Expense Section --}}
                    <h4 class="text-danger"><strong><i class="fa fa-arrow-down"></i> @lang('accounting::lang.expenses') (YTD)</strong></h4>
                    <table class="table table-striped table-bordered" id="expense_report_table">
                        <thead>
                            <tr class="gray">
                                <th rowspan="2" style="width:120px; vertical-align: middle;">@lang('accounting::lang.gl_code')</th>
                                <th rowspan="2" style="vertical-align: middle;">@lang('user.name')</th>
                                @foreach($months as $index => $month)
                                    <th class="text-center month-col">{{ $month['label'] }}</th>
                                    @if($index > 0)
                                        <th class="text-center diff-col bg-warning">Δ MoM</th>
                                    @endif
                                @endforeach
                                <th class="text-right bg-primary" rowspan="2" style="width:150px; vertical-align: middle;">YTD Total</th>
                            </tr>
                            <tr class="gray">
                                @foreach($months as $index => $month)
                                    <th class="text-center text-muted month-col" style="font-size: 10px;">
                                        s.d. {{ \Carbon\Carbon::parse($month['end'])->format('d M') }}
                                    </th>
                                    @if($index > 0)
                                        <th class="text-center text-muted diff-col" style="font-size: 10px;">
                                            vs {{ $months[$index - 1]['label'] }}
                                        </th>
                                    @endif
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expense_accounts as $account)
                                <tr>
                                    <td>{{ $account->gl_code }}</td>
                                    <td>{{ $account->name }}</td>
                                    @foreach($months as $index => $month)
                                        @php
                                            $current_balance = $account->monthly_balances[$month['key']] ?? 0;
                                            $prev_balance = $index > 0 ? ($account->monthly_balances[$months[$index - 1]['key']] ?? 0) : 0;
                                            $difference = $current_balance - $prev_balance;
                                            $diff_color = $difference > 0 ? 'text-danger' : ($difference < 0 ? 'text-success' : '');
                                        @endphp
                                        <td class="text-right month-col">@format_currency($current_balance)</td>
                                        @if($index > 0)
                                            <td class="text-right diff-col {{ $diff_color }}">
                                                @if($difference > 0) <i class="fa fa-arrow-up"></i> @elseif($difference < 0) <i class="fa fa-arrow-down"></i> @endif
                                                @format_currency(abs($difference))
                                            </td>
                                        @endif
                                    @endforeach
                                    <td class="text-right"><strong>@format_currency($account->balance)</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 2 + count($months) + (count($months) > 0 ? count($months) - 1 : 0) + 1 }}" class="text-center text-muted">@lang('lang_v1.no_data')</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="gray">
                                <th colspan="2" class="text-right"><strong>@lang('accounting::lang.total_expenses') (YTD)</strong></th>
                                @foreach($months as $index => $month)
                                    @php
                                        $current_expense = $monthly_totals['expense'][$month['key']] ?? 0;
                                        $prev_expense = $index > 0 ? ($monthly_totals['expense'][$months[$index - 1]['key']] ?? 0) : 0;
                                        $expense_diff = $current_expense - $prev_expense;
                                        $expense_diff_color = $expense_diff > 0 ? 'text-danger' : ($expense_diff < 0 ? 'text-success' : '');
                                    @endphp
                                    <th class="text-right month-col">@format_currency($current_expense)</th>
                                    @if($index > 0)
                                        <th class="text-right diff-col {{ $expense_diff_color }}" style="background-color: #39cccc;">
                                            @if($expense_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($expense_diff < 0) <i class="fa fa-arrow-down"></i> @endif
                                            @format_currency(abs($expense_diff))
                                        </th>
                                    @endif
                                @endforeach
                                <th class="text-right"><strong>@format_currency($total_expense)</strong></th>
                            </tr>
                        </tfoot>
                    </table>

                    <br/>

                    {{-- Net Profit/Loss Section --}}
                    <h4><strong><i class="fa fa-calculator"></i> @lang('accounting::lang.net_profit') / @lang('accounting::lang.net_loss') (YTD)</strong></h4>
                    <table class="table table-bordered" id="net_profit_table">
                        <thead>
                            <tr class="{{ $net_profit >= 0 ? 'bg-green' : 'bg-red' }}">
                                <th style="font-size: 16px; vertical-align: middle;">
                                    <strong>
                                        @if($net_profit >= 0)
                                            <i class="fa fa-check-circle"></i> @lang('accounting::lang.net_profit') (YTD)
                                        @else
                                            <i class="fa fa-times-circle"></i> @lang('accounting::lang.net_loss') (YTD)
                                        @endif
                                    </strong>
                                </th>
                                @foreach($months as $index => $month)
                                    <th class="text-center month-col">{{ $month['label'] }}</th>
                                    @if($index > 0)
                                        <th class="text-center diff-col bg-teal">Δ MoM</th>
                                    @endif
                                @endforeach
                                <th class="text-center bg-primary">YTD Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="{{ $net_profit >= 0 ? 'bg-cyan' : 'bg-blue' }}">
                                <td style="font-size: 16px;"><strong>Nilai</strong></td>
                                @foreach($months as $index => $month)
                                    @php
                                        $current_net = $monthly_totals['net_profit'][$month['key']] ?? 0;
                                        $prev_net = $index > 0 ? ($monthly_totals['net_profit'][$months[$index - 1]['key']] ?? 0) : 0;
                                        $net_diff = $current_net - $prev_net;
                                        $net_diff_color = $net_diff > 0 ? 'text-success' : ($net_diff < 0 ? 'text-danger' : '');
                                    @endphp
                                    <td class="text-right month-col" style="font-size: 14px;">
                                        <strong class="{{ $current_net >= 0 ? '' : 'text-danger' }}">
                                            @format_currency($current_net)
                                        </strong>
                                    </td>
                                    @if($index > 0)
                                        <td class="text-right diff-col {{ $net_diff_color }}" style="background-color: {{ $net_diff >= 0 ? '#00c6ff' : '#39cccc' }};">
                                            @if($net_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($net_diff < 0) <i class="fa fa-arrow-down"></i> @endif
                                            @format_currency(abs($net_diff))
                                        </td>
                                    @endif
                                @endforeach
                                <td class="text-right" style="font-size: 18px;">
                                    <strong>@format_currency(abs($net_profit))</strong>
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

        // Toggle difference columns visibility
        $('#show_difference_columns').on('change', function() {
            if ($(this).is(':checked')) {
                $('.diff-col').show();
            } else {
                $('.diff-col').hide();
            }
        });

        // Helper function to parse Indonesian formatted number
        function parseIndonesianNumber(str) {
            if (!str) return '';
            var cleaned = str.replace(/Rp\s*/gi, '').trim();
            cleaned = cleaned.replace(/[↑↓▲▼]/g, '').trim();
            if (!cleaned.match(/^-?[\d.,]+$/)) {
                return str;
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

        // Function to export table to Excel
        function exportTableToExcel(tableId, filename) {
            var table = document.getElementById(tableId);
            var clone = table.cloneNode(true);
            
            $(clone).find('td, th').each(function() {
                var text = $(this).text().trim();
                if (text.match(/Rp\s*-?[\d.,]+/) || text.match(/^-?[\d.,]+$/)) {
                    var num = parseIndonesianNumber(text);
                    $(this).text(num);
                } else {
                    $(this).text(text);
                }
            });
            
            var html = clone.outerHTML;
            var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            var downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            downloadLink.href = url;
            downloadLink.download = filename + '.xls';
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }

        // Export buttons
        $('#export_income_excel').on('click', function() {
            exportTableToExcel('income_report_table', 'Income_YTD_Report_{{$start_date}}_to_{{$end_date}}');
        });

        $('#export_expense_excel').on('click', function() {
            exportTableToExcel('expense_report_table', 'Expense_YTD_Report_{{$start_date}}_to_{{$end_date}}');
        });

        $('#export_all_excel').on('click', function() {
            var monthCount = {{ count($months) }};
            var totalCols = 2 + monthCount + (monthCount > 1 ? monthCount - 1 : 0) + 1;
            
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
            
            var html = '<table>';
            html += '<tr><th colspan="' + totalCols + '" style="text-align:center; font-size:18px;">Profit & Loss YTD Report</th></tr>';
            html += '<tr><th colspan="' + totalCols + '" style="text-align:center;">{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</th></tr>';
            html += '<tr><td colspan="' + totalCols + '">&nbsp;</td></tr>';
            html += incomeTable.outerHTML;
            html += '<tr><td colspan="' + totalCols + '">&nbsp;</td></tr>';
            html += expenseTable.outerHTML;
            html += '<tr><td colspan="' + totalCols + '">&nbsp;</td></tr>';
            html += netProfitTable.outerHTML;
            html += '</table>';
            
            var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            var downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            downloadLink.href = url;
            downloadLink.download = 'Profit_Loss_YTD_Report_{{$start_date}}_to_{{$end_date}}.xls';
            downloadLink.click();
            document.body.removeChild(downloadLink);
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
