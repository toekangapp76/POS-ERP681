@extends('layouts.app')

@section('title', __('accounting::lang.balance_sheet'))

@section('content')

@include('accounting::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('accounting::lang.balance_sheet')</h1>
</section>

<section class="content">
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
        for ($year = $year_start; $year <= $year_end; $year++) {
            $year_options[$year] = $year;
        }

        $month_options = [];
        for ($month = 1; $month <= 12; $month++) {
            $value = str_pad($month, 2, '0', STR_PAD_LEFT);
            $month_options[$value] = \Carbon\Carbon::createFromDate(null, $month, 1)->translatedFormat('F');
        }
    @endphp
    <div class="row">
        <div class="col-md-3 col-md-offset-1">
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
                <label>&nbsp;</label>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="show_difference_columns" checked>
                        <strong>Tampilkan Kolom Selisih</strong>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-warning">
                <div class="box-header with-border text-center">
                    <h2 class="box-title">@lang('accounting::lang.balance_sheet')</h2>
                </div>

                <div class="box-body table-responsive">

                    <table class="table table-striped table-bordered table-hover" id="balance_sheet_table"
                        style="width:100%">
                        @php
                            $month_count = count($months);
                            $current_month = $month_count > 0 ? $months[$month_count - 1] : null;
                            $last_month = $month_count > 1 ? $months[$month_count - 2] : null;
                            $current_key = $current_month['key'] ?? null;
                            $last_key = $last_month['key'] ?? null;
                            $period_start = $month_count > 0 ? \Carbon\Carbon::parse($months[0]['start']) : null;
                            $current_range_label = ($current_month && $period_start)
                                ? 'as at ' . \Carbon\Carbon::parse($current_month['end'])->format('M')
                                : '-';
                            $last_range_label = ($last_month && $period_start)
                                ? ' as at ' . \Carbon\Carbon::parse($last_month['end'])->format('M')
                                : '-';
                        @endphp
                        <thead>
                            <tr>
                                <th class="text-center align-middle" style="vertical-align: middle;">
                                    @lang('accounting::lang.gl_code')
                                </th>
                                <th class="text-center align-middle" style="vertical-align: middle;">@lang('user.name')
                                </th>
                                <th class="text-center align-middle" style="vertical-align: middle;">
                                    @lang('accounting::lang.account_type')
                                </th>
                                <th class="text-center align-middle bg-info" style="vertical-align: middle;">
                                    Last Month
                                    <div class="text-muted" style="font-size: 11px;">{{ $last_range_label }}</div>
                                </th>
                                <th class="text-center align-middle bg-info" style="vertical-align: middle;">
                                    Current Month
                                    <div class="text-muted" style="font-size: 11px;">{{ $current_range_label }}</div>
                                </th>
                                <th class="text-center align-middle bg-warning diff-col"
                                    style="vertical-align: middle;">Varian</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $monthly_totals_assets = [];
                                $monthly_totals_liabilities = [];
                                $monthly_totals_equity = [];

                                // Initialize monthly totals
                                foreach ($months as $month) {
                                    $monthly_totals_assets[$month['key']] = 0;
                                    $monthly_totals_liabilities[$month['key']] = 0;
                                    $monthly_totals_equity[$month['key']] = 0;
                                }
                            @endphp
                            @foreach($all_accounts as $account)
                                @php
                                    if ($account->account_primary_type == 'asset') {
                                        foreach ($months as $month) {
                                            $monthly_totals_assets[$month['key']] += $account->monthly_balances[$month['key']] ?? 0;
                                        }
                                    } elseif ($account->account_primary_type == 'liability') {
                                        foreach ($months as $month) {
                                            $monthly_totals_liabilities[$month['key']] += $account->monthly_balances[$month['key']] ?? 0;
                                        }
                                    } elseif ($account->account_primary_type == 'equity') {
                                        foreach ($months as $month) {
                                            $monthly_totals_equity[$month['key']] += $account->monthly_balances[$month['key']] ?? 0;
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $account->gl_code ?? '-' }}</td>
                                    <td>{{ $account->name }}</td>
                                    <td>{{ __('accounting::lang.' . $account->account_primary_type) }}</td>
                                    @php
                                        $last_balance = 0;
                                        $current_balance = 0;
                                        $running_balance = 0;
                                        foreach ($months as $month) {
                                            $running_balance += $account->monthly_balances[$month['key']] ?? 0;
                                            if ($last_key && $month['key'] === $last_key) {
                                                $last_balance = $running_balance;
                                            }
                                            if ($current_key && $month['key'] === $current_key) {
                                                $current_balance = $running_balance;
                                            }
                                        }
                                        // Varian calculation based on COA:
                                        // COA 1XXX (Asset) = Debit - Credit (current - last is already positive when assets increase)
                                        // COA 2XXX (Liability) & 3XXX (Equity) = Credit - Debit (need to negate for proper variance)
                                        $gl_code_prefix = !empty($account->gl_code) ? substr($account->gl_code, 0, 1) : '1';
                                        if ($gl_code_prefix == '1') {
                                            // Asset: variance shows increase/decrease in assets
                                            $difference = $current_balance - $last_balance;
                                        } else {
                                            // Liability (2) and Equity (3): variance shows increase/decrease 
                                            // For balance sheet to balance, we negate so total variance = 0
                                            $difference = -($current_balance - $last_balance);
                                        }
                                    @endphp
                                    <td class="text-right month-col">
                                        <span data-orig-value="{{ $last_balance }}">@format_currency($last_balance)</span>
                                    </td>
                                    <td class="text-right month-col">
                                        <span
                                            data-orig-value="{{ $current_balance }}">@format_currency($current_balance)</span>
                                    </td>
                                    @php
                                        $diff_color = $difference > 0 ? 'text-success' : ($difference < 0 ? 'text-danger' : '');
                                    @endphp
                                    <td class="text-right diff-col {{ $diff_color }}">
                                        @if($difference > 0)
                                            <i class="fa fa-arrow-up"></i>
                                        @elseif($difference < 0)
                                            <i class="fa fa-arrow-down"></i>
                                        @endif
                                        @format_currency(abs($difference))
                                    </td>
                                </tr>
                            @endforeach

                            {{-- R/E Current Year (Net Profit/Loss from P&L) --}}
                            @if(isset($re_current_year))
                                @php
                                    // R/E Calculation logic moved to explicit addition in footer to prevent double counting
                                    // foreach ($months as $month) {
                                    //    $monthly_totals_equity[$month['key']] += $re_current_year->monthly_balances[$month['key']] ?? 0;
                                    // }

                                    $re_last_balance = 0;
                                    $re_current_balance = 0;
                                    $re_running_balance = 0;
                                    foreach ($months as $month) {
                                        $re_running_balance += $re_current_year->monthly_balances[$month['key']] ?? 0;
                                        if ($last_key && $month['key'] === $last_key) {
                                            $re_last_balance = $re_running_balance;
                                        }
                                        if ($current_key && $month['key'] === $current_key) {
                                            $re_current_balance = $re_running_balance;
                                        }
                                    }
                                    // R/E Current Year is equity (3XXX), so negate for balance
                                    $re_difference = -($re_current_balance - $re_last_balance);
                                    $re_diff_color = $re_difference > 0 ? 'text-success' : ($re_difference < 0 ? 'text-danger' : '');
                                @endphp
                                <tr class="bg-success-light" style="background-color: #d4edda; font-weight: bold;">
                                    <td>{{ $re_current_year->gl_code }}</td>
                                    <td>
                                        <i class="fa fa-calculator"></i> {{ $re_current_year->name }}
                                        <small class="text-muted">(Auto dari P&L)</small>
                                    </td>
                                    <td>{{ __('accounting::lang.equity') }}</td>
                                    <td class="text-right month-col">
                                        <span data-orig-value="{{ $re_last_balance }}"
                                            class="{{ $re_last_balance >= 0 ? 'text-success' : 'text-danger' }}">
                                            @format_currency($re_last_balance)
                                        </span>
                                    </td>
                                    <td class="text-right month-col">
                                        <span data-orig-value="{{ $re_current_balance }}"
                                            class="{{ $re_current_balance >= 0 ? 'text-success' : 'text-danger' }}">
                                            @format_currency($re_current_balance)
                                        </span>
                                    </td>
                                    <td class="text-right diff-col {{ $re_diff_color }}">
                                        @if($re_difference > 0)
                                            <i class="fa fa-arrow-up"></i>
                                        @elseif($re_difference < 0)
                                            <i class="fa fa-arrow-down"></i>
                                        @endif
                                        @format_currency(abs($re_difference))
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            {{-- Total Assets --}}
                            <tr class="bg-success">
                                <th colspan="3" class="text-right">@lang('accounting::lang.total_assets'):</th>
                                @php
                                    $last_asset_total = 0;
                                    $current_asset_total = 0;
                                    $running_asset_total = 0;
                                    foreach ($months as $month) {
                                        $running_asset_total += $monthly_totals_assets[$month['key']] ?? 0;
                                        if ($last_key && $month['key'] === $last_key) {
                                            $last_asset_total = $running_asset_total;
                                        }
                                        if ($current_key && $month['key'] === $current_key) {
                                            $current_asset_total = $running_asset_total;
                                        }
                                    }
                                    $asset_diff = $current_asset_total - $last_asset_total;
                                @endphp
                                <th class="text-right month-col">@format_currency($last_asset_total)</th>
                                <th class="text-right month-col">@format_currency($current_asset_total)</th>
                                <th class="text-right diff-col {{ $asset_diff > 0 ? 'text-success' : ($asset_diff < 0 ? 'text-danger' : '') }}"
                                    style="background-color: #d4edda;">
                                    @if($asset_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($asset_diff < 0) <i
                                    class="fa fa-arrow-down"></i> @endif
                                    @format_currency(abs($asset_diff))
                                </th>
                            </tr>

                            {{-- Total Liabilities --}}
                            <tr class="bg-warning">
                                <th colspan="3" class="text-right">@lang('accounting::lang.total_liabilities'):</th>
                                @php
                                    $last_liab_total = 0;
                                    $current_liab_total = 0;
                                    $running_liab_total = 0;
                                    foreach ($months as $month) {
                                        $running_liab_total += $monthly_totals_liabilities[$month['key']] ?? 0;
                                        if ($last_key && $month['key'] === $last_key) {
                                            $last_liab_total = $running_liab_total;
                                        }
                                        if ($current_key && $month['key'] === $current_key) {
                                            $current_liab_total = $running_liab_total;
                                        }
                                    }
                                    // Liability (COA 2XXX) - negate for balance sheet equation
                                    $liab_diff = -($current_liab_total - $last_liab_total);
                                @endphp
                                <th class="text-right month-col">@format_currency($last_liab_total)</th>
                                <th class="text-right month-col">@format_currency($current_liab_total)</th>
                                <th class="text-right diff-col {{ $liab_diff > 0 ? 'text-success' : ($liab_diff < 0 ? 'text-danger' : '') }}"
                                    style="background-color: #fff3cd;">
                                    @if($liab_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($liab_diff < 0) <i
                                    class="fa fa-arrow-down"></i> @endif
                                    @format_currency(abs($liab_diff))
                                </th>
                            </tr>

                            {{-- Total Equity --}}
                            <tr class="bg-info">
                                <th colspan="3" class="text-right">@lang('accounting::lang.total_equity'):</th>
                                @php
                                    $last_equity_total = 0;
                                    $current_equity_total = 0;
                                    $running_equity_total = 0;
                                    foreach ($months as $month) {
                                        $running_equity_total += $monthly_totals_equity[$month['key']] ?? 0;
                                        if ($last_key && $month['key'] === $last_key) {
                                            $last_equity_total = $running_equity_total + ($re_last_balance ?? 0);
                                        }
                                        if ($current_key && $month['key'] === $current_key) {
                                            $current_equity_total = $running_equity_total + ($re_current_balance ?? 0);
                                        }
                                    }
                                    // Equity (COA 3XXX) - negate for balance sheet equation
                                    $equity_diff = -($current_equity_total - $last_equity_total);
                                @endphp
                                <th class="text-right month-col">@format_currency($last_equity_total)</th>
                                <th class="text-right month-col">@format_currency($current_equity_total)</th>
                                <th class="text-right diff-col {{ $equity_diff > 0 ? 'text-success' : ($equity_diff < 0 ? 'text-danger' : '') }}"
                                    style="background-color: #d1ecf1;">
                                    @if($equity_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($equity_diff < 0) <i
                                    class="fa fa-arrow-down"></i> @endif
                                    @format_currency(abs($equity_diff))
                                </th>
                            </tr>

                            {{-- Total Liabilities + Equity --}}
                            <tr class="bg-primary">
                                <th colspan="3" class="text-right">@lang('accounting::lang.total_liab_owners'):</th>
                                @php
                                    $last_liab_eq = $last_liab_total + $last_equity_total;
                                    $current_liab_eq = $current_liab_total + $current_equity_total;
                                    // Liabilities + Equity is contra to Assets, negate for variance
                                    $liab_eq_diff = -($current_liab_eq - $last_liab_eq);
                                @endphp
                                <th class="text-right month-col">@format_currency($last_liab_eq)</th>
                                <th class="text-right month-col">@format_currency($current_liab_eq)</th>
                                <th class="text-right diff-col" style="background-color: #cce5ff;">
                                    @if($liab_eq_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($liab_eq_diff < 0) <i
                                    class="fa fa-arrow-down"></i> @endif
                                    @format_currency(abs($liab_eq_diff))
                                </th>
                            </tr>

                            {{-- Total Variance (should equal 0) --}}
                            <tr class="bg-gray" style="background-color: #f0f0f0; font-weight: bold;">
                                <th colspan="3" class="text-right">Total Varian (harus = 0):</th>
                                @php
                                    // Total variance = Asset diff + (negated Liab+Eq diff) 
                                    // Since we already negated liab/equity, just add them
                                    $total_variance = $asset_diff + $liab_diff + $equity_diff;
                                @endphp
                                <th class="text-center month-col">-</th>
                                <th class="text-center month-col">-</th>
                                <th class="text-right diff-col {{ abs($total_variance) < 0.01 ? 'text-success' : 'text-danger' }}"
                                    style="background-color: {{ abs($total_variance) < 0.01 ? '#d4edda' : '#f8d7da' }};">
                                    @if(abs($total_variance) < 0.01)
                                        <i class="fa fa-check-circle"></i> 0
                                    @else
                                        <i class="fa fa-exclamation-triangle"></i> @format_currency($total_variance)
                                    @endif
                                </th>
                            </tr>
                        </tfoot>
                    </table>

                </div>

            </div>
        </div>
    </div>


</section>

@stop

@section('javascript')

<script type="text/javascript">
    $(document).ready(function () {

        // Helper function to strip HTML tags
        function stripHtml(html) {
            var tmp = document.createElement("DIV");
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || "";
        }

        // Helper function to parse Indonesian formatted number (Rp 1.234.567,00 → 1234567.00)
        function parseIndonesianNumber(str) {
            if (!str) return 0;
            // Remove Rp, spaces, and HTML tags
            var cleaned = stripHtml(str);
            cleaned = cleaned.replace(/Rp\s*/gi, '').trim();
            cleaned = cleaned.replace(/[↑↓▲▼]/g, '').trim();

            // Check if it looks like a number
            if (!cleaned.match(/^-?[\d.,]+$/)) {
                return 0;
            }

            // Detect format by finding the last occurrence of . or ,
            var lastDot = cleaned.lastIndexOf('.');
            var lastComma = cleaned.lastIndexOf(',');

            // If both exist, the one that comes last is the decimal separator
            if (lastDot > lastComma) {
                // Format: 1,234.56 (comma = thousand, dot = decimal) - International
                cleaned = cleaned.replace(/,/g, ''); // Remove thousand separator
            } else if (lastComma > lastDot) {
                // Format: 1.234,56 (dot = thousand, comma = decimal) - Indonesian
                cleaned = cleaned.replace(/\./g, '').replace(',', '.'); // Remove thousand, convert decimal
            } else if (lastDot !== -1) {
                // Only dot exists
                if (cleaned.match(/\.\d{2}$/)) {
                    // Likely decimal: 123.45
                } else {
                    // Likely thousand separator: 1.234
                    cleaned = cleaned.replace(/\./g, '');
                }
            } else if (lastComma !== -1) {
                // Only comma exists - treat as decimal
                cleaned = cleaned.replace(',', '.');
            }

            var num = parseFloat(cleaned);
            return isNaN(num) ? 0 : num;
        }

        // Initialize DataTable with export buttons and sorting
        var table = $('#balance_sheet_table').DataTable({
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel-o"></i> Excel',
                    title: 'Balance Sheet - {{@format_date($start_date)}} to {{@format_date($end_date)}}',
                    exportOptions: {
                        columns: ':visible',
                        format: {
                            body: function (data, row, column, node) {
                                // For columns with currency (columns 3 onwards), extract numeric value
                                if (column >= 3) {
                                    return parseIndonesianNumber(data);
                                }
                                // Strip HTML from other columns
                                return stripHtml(data);
                            }
                        }
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fa fa-file-pdf-o"></i> PDF',
                    title: 'Balance Sheet - {{@format_date($start_date)}} to {{@format_date($end_date)}}',
                    orientation: 'landscape',
                    pageSize: 'A3',
                    exportOptions: {
                        columns: ':visible',
                        format: {
                            body: function (data, row, column, node) {
                                if (column >= 3) {
                                    var num = parseIndonesianNumber(data);
                                    // Format for PDF display with Indonesian locale
                                    return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                                return stripHtml(data);
                            }
                        }
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fa fa-print"></i> Print',
                    title: 'Balance Sheet - {{@format_date($start_date)}} to {{@format_date($end_date)}}',
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ],
            paging: false,
            searching: true,
            info: false,
            ordering: true,
            order: [[0, 'asc']], // Sort by GL Code by default
            scrollX: true,
            fixedColumns: {
                left: 2
            }
        });

        // Toggle difference columns visibility
        $('#show_difference_columns').on('change', function () {
            if ($(this).is(':checked')) {
                $('.diff-col').show();
            } else {
                $('.diff-col').hide();
            }
            // Redraw table to adjust column widths
            table.columns.adjust().draw();
        });

        $('#month_filter, #year_filter').on('change', function () {
            apply_filter();
        });

        function apply_filter() {
            var end = '';
            var month = $('#month_filter').val();
            var year = $('#year_filter').val();

            if (month && year) {
                var selected = moment(year + '-' + month, 'YYYY-MM', true);
                if (selected.isValid()) {
                    end = selected.endOf('month').format('YYYY-MM-DD');
                }
            }

            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('end_date', end);
            urlParams.delete('start_date');
            window.location.search = urlParams;
        }
    });

</script>

<style>
    #balance_sheet_table th,
    #balance_sheet_table td {
        white-space: nowrap;
    }

    #balance_sheet_table thead th {
        text-align: center;
        vertical-align: middle;
    }

    .text-success {
        color: #28a745 !important;
    }

    .text-danger {
        color: #dc3545 !important;
    }

    #balance_sheet_table tfoot tr th {
        font-weight: bold;
    }

    .bg-success th {
        color: #155724;
    }

    .bg-warning th {
        color: #856404;
    }

    .bg-info th {
        color: #0c5460;
    }

    .bg-primary th {
        color: #fff;
    }

    .diff-col {
        background-color: #fff8e1;
    }
</style>

@stop