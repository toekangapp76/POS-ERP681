@extends('layouts.app')

@section('title', __('accounting::lang.balance_sheet'))

@section('content')

@include('accounting::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang( 'accounting::lang.balance_sheet' )</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-3 col-md-offset-1">
            <div class="form-group">
                {!! Form::label('date_range_filter', __('report.date_range') . ':') !!}
                {!! Form::text('date_range_filter', null, 
                    ['placeholder' => __('lang_v1.select_a_date_range'), 
                    'class' => 'form-control', 'readonly', 'id' => 'date_range_filter']); !!}
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
                    <h2 class="box-title">@lang( 'accounting::lang.balance_sheet')</h2>
                    <p>{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</p>
                </div>
    
                <div class="box-body table-responsive">
                    
                    <table class="table table-striped table-bordered table-hover" id="balance_sheet_table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="text-center align-middle" style="vertical-align: middle;">@lang('accounting::lang.gl_code')</th>
                                <th rowspan="2" class="text-center align-middle" style="vertical-align: middle;">@lang('user.name')</th>
                                <th rowspan="2" class="text-center align-middle" style="vertical-align: middle;">@lang('accounting::lang.account_type')</th>
                                <th rowspan="2" class="text-center align-middle" style="vertical-align: middle;">@lang('accounting::lang.account_sub_type')</th>
                                @foreach($months as $index => $month)
                                    <th class="text-center bg-info month-col">{{ $month['label'] }}</th>
                                    @if($index > 0)
                                        <th class="text-center bg-warning diff-col">Selisih</th>
                                    @endif
                                @endforeach
                                <th rowspan="2" class="text-center bg-primary align-middle" style="vertical-align: middle;">Total</th>
                            </tr>
                            <tr>
                                @foreach($months as $index => $month)
                                    <th class="text-center text-muted month-col" style="font-size: 11px;">
                                        {{ \Carbon\Carbon::parse($month['start'])->format('d M') }} - {{ \Carbon\Carbon::parse($month['end'])->format('d M') }}
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
                            @php
                                $total_assets = 0;
                                $total_liabilities = 0;
                                $total_equity = 0;
                                $monthly_totals_assets = [];
                                $monthly_totals_liabilities = [];
                                $monthly_totals_equity = [];
                                
                                // Initialize monthly totals
                                foreach($months as $month) {
                                    $monthly_totals_assets[$month['key']] = 0;
                                    $monthly_totals_liabilities[$month['key']] = 0;
                                    $monthly_totals_equity[$month['key']] = 0;
                                }
                            @endphp
                            @foreach($all_accounts as $account)
                                @php
                                    if ($account->account_primary_type == 'asset') {
                                        $total_assets += $account->balance;
                                        foreach($months as $month) {
                                            $monthly_totals_assets[$month['key']] += $account->monthly_balances[$month['key']] ?? 0;
                                        }
                                    } elseif ($account->account_primary_type == 'liability') {
                                        $total_liabilities += $account->balance;
                                        foreach($months as $month) {
                                            $monthly_totals_liabilities[$month['key']] += $account->monthly_balances[$month['key']] ?? 0;
                                        }
                                    } elseif ($account->account_primary_type == 'equity') {
                                        $total_equity += $account->balance;
                                        foreach($months as $month) {
                                            $monthly_totals_equity[$month['key']] += $account->monthly_balances[$month['key']] ?? 0;
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $account->gl_code ?? '-' }}</td>
                                    <td>{{ $account->name }}</td>
                                    <td>{{ __('accounting::lang.' . $account->account_primary_type) }}</td>
                                    <td>{{ $account->sub_type ? __('accounting::lang.' . $account->sub_type) : '-' }}</td>
                                    @foreach($months as $index => $month)
                                        @php
                                            $current_balance = $account->monthly_balances[$month['key']] ?? 0;
                                            $prev_balance = $index > 0 ? ($account->monthly_balances[$months[$index - 1]['key']] ?? 0) : 0;
                                            $difference = $current_balance - $prev_balance;
                                        @endphp
                                        <td class="text-right month-col">
                                            <span data-orig-value="{{ $current_balance }}">@format_currency($current_balance)</span>
                                        </td>
                                        @if($index > 0)
                                            @php
                                                // For Assets & Equity: positive = good (green), negative = bad (red)
                                                // For Liabilities: positive = bad (red/more debt), negative = good (green/less debt)
                                                if ($account->account_primary_type == 'liability') {
                                                    $diff_color = $difference > 0 ? 'text-danger' : ($difference < 0 ? 'text-success' : '');
                                                } else {
                                                    $diff_color = $difference > 0 ? 'text-success' : ($difference < 0 ? 'text-danger' : '');
                                                }
                                            @endphp
                                            <td class="text-right diff-col {{ $diff_color }}">
                                                @if($difference > 0)
                                                    <i class="fa fa-arrow-up"></i>
                                                @elseif($difference < 0)
                                                    <i class="fa fa-arrow-down"></i>
                                                @endif
                                                @format_currency(abs($difference))
                                            </td>
                                        @endif
                                    @endforeach
                                    <td class="text-right"><strong><span data-orig-value="{{ $account->balance }}">@format_currency($account->balance)</span></strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            {{-- Total Assets --}}
                            <tr class="bg-success">
                                <th colspan="4" class="text-right">@lang('accounting::lang.total_assets'):</th>
                                @foreach($months as $index => $month)
                                    @php
                                        $current_asset_total = $monthly_totals_assets[$month['key']] ?? 0;
                                        $prev_asset_total = $index > 0 ? ($monthly_totals_assets[$months[$index - 1]['key']] ?? 0) : 0;
                                        $asset_diff = $current_asset_total - $prev_asset_total;
                                    @endphp
                                    <th class="text-right month-col">@format_currency($current_asset_total)</th>
                                    @if($index > 0)
                                        <th class="text-right diff-col {{ $asset_diff > 0 ? 'text-success' : ($asset_diff < 0 ? 'text-danger' : '') }}" style="background-color: #d4edda;">
                                            @if($asset_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($asset_diff < 0) <i class="fa fa-arrow-down"></i> @endif
                                            @format_currency(abs($asset_diff))
                                        </th>
                                    @endif
                                @endforeach
                                <th class="text-right total-assets">@format_currency($total_assets)</th>
                            </tr>
                            
                            {{-- Total Liabilities --}}
                            <tr class="bg-warning">
                                <th colspan="4" class="text-right">@lang('accounting::lang.total_liabilities'):</th>
                                @foreach($months as $index => $month)
                                    @php
                                        $current_liab_total = $monthly_totals_liabilities[$month['key']] ?? 0;
                                        $prev_liab_total = $index > 0 ? ($monthly_totals_liabilities[$months[$index - 1]['key']] ?? 0) : 0;
                                        $liab_diff = $current_liab_total - $prev_liab_total;
                                    @endphp
                                    <th class="text-right month-col">@format_currency($current_liab_total)</th>
                                    @if($index > 0)
                                        <th class="text-right diff-col {{ $liab_diff > 0 ? 'text-danger' : ($liab_diff < 0 ? 'text-success' : '') }}" style="background-color: #fff3cd;">
                                            @if($liab_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($liab_diff < 0) <i class="fa fa-arrow-down"></i> @endif
                                            @format_currency(abs($liab_diff))
                                        </th>
                                    @endif
                                @endforeach
                                <th class="text-right total-liabilities">@format_currency($total_liabilities)</th>
                            </tr>
                            
                            {{-- Total Equity --}}
                            <tr class="bg-info">
                                <th colspan="4" class="text-right">@lang('accounting::lang.total_equity'):</th>
                                @foreach($months as $index => $month)
                                    @php
                                        $current_equity_total = $monthly_totals_equity[$month['key']] ?? 0;
                                        $prev_equity_total = $index > 0 ? ($monthly_totals_equity[$months[$index - 1]['key']] ?? 0) : 0;
                                        $equity_diff = $current_equity_total - $prev_equity_total;
                                    @endphp
                                    <th class="text-right month-col">@format_currency($current_equity_total)</th>
                                    @if($index > 0)
                                        <th class="text-right diff-col {{ $equity_diff > 0 ? 'text-success' : ($equity_diff < 0 ? 'text-danger' : '') }}" style="background-color: #d1ecf1;">
                                            @if($equity_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($equity_diff < 0) <i class="fa fa-arrow-down"></i> @endif
                                            @format_currency(abs($equity_diff))
                                        </th>
                                    @endif
                                @endforeach
                                <th class="text-right total-equity">@format_currency($total_equity)</th>
                            </tr>
                            
                            {{-- Total Liabilities + Equity --}}
                            <tr class="bg-primary">
                                <th colspan="4" class="text-right">@lang('accounting::lang.total_liab_owners'):</th>
                                @foreach($months as $index => $month)
                                    @php
                                        $current_liab_eq = ($monthly_totals_liabilities[$month['key']] ?? 0) + ($monthly_totals_equity[$month['key']] ?? 0);
                                        $prev_liab_eq = $index > 0 ? (($monthly_totals_liabilities[$months[$index - 1]['key']] ?? 0) + ($monthly_totals_equity[$months[$index - 1]['key']] ?? 0)) : 0;
                                        $liab_eq_diff = $current_liab_eq - $prev_liab_eq;
                                    @endphp
                                    <th class="text-right month-col">@format_currency($current_liab_eq)</th>
                                    @if($index > 0)
                                        <th class="text-right diff-col" style="background-color: #cce5ff;">
                                            @if($liab_eq_diff > 0) <i class="fa fa-arrow-up"></i> @elseif($liab_eq_diff < 0) <i class="fa fa-arrow-down"></i> @endif
                                            @format_currency(abs($liab_eq_diff))
                                        </th>
                                    @endif
                                @endforeach
                                <th class="text-right total-liab-equity">@format_currency($total_liabilities + $total_equity)</th>
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
    $(document).ready(function(){

        // Calculate column count for DataTable
        var monthCount = {{ count($months) }};
        var differenceCount = monthCount > 0 ? monthCount - 1 : 0;
        var totalColumns = 4 + monthCount + differenceCount + 1; // base cols + months + differences + total

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
            // Indonesian format: dots for thousands, comma for decimal
            // Remove dots (thousand separator), replace comma with dot (decimal)
            cleaned = cleaned.replace(/\./g, '').replace(',', '.');
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
                            body: function(data, row, column, node) {
                                // For columns with currency (columns 4 onwards), extract numeric value
                                if (column >= 4) {
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
                            body: function(data, row, column, node) {
                                if (column >= 4) {
                                    var num = parseIndonesianNumber(data);
                                    // Format for PDF display with Indonesian locale
                                    return num.toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
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
        $('#show_difference_columns').on('change', function() {
            if ($(this).is(':checked')) {
                $('.diff-col').show();
            } else {
                $('.diff-col').hide();
            }
            // Redraw table to adjust column widths
            table.columns.adjust().draw();
        });

        dateRangeSettings.startDate = moment('{{$start_date}}');
        dateRangeSettings.endDate = moment('{{$end_date}}');

        $('#date_range_filter').daterangepicker(
            dateRangeSettings,
            function (start, end) {
                $('#date_range_filter').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                apply_filter();
            }
        );
        $('#date_range_filter').on('cancel.daterangepicker', function(ev, picker) {
            $('#date_range_filter').val('');
            apply_filter();
        });

        function apply_filter(){
            var start = '';
            var end = '';

            if ($('#date_range_filter').val()) {
                start = $('input#date_range_filter')
                    .data('daterangepicker')
                    .startDate.format('YYYY-MM-DD');
                end = $('input#date_range_filter')
                    .data('daterangepicker')
                    .endDate.format('YYYY-MM-DD');
            }

            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('start_date', start);
            urlParams.set('end_date', end);
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