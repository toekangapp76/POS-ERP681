@extends('layouts.app')

@section('title', __('accounting::lang.pnl_bisnis'))

@section('content')

@include('accounting::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1>@lang( 'accounting::lang.pnl_bisnis' )</h1>
</section>

<section class="content no-print" style="min-height:auto !important;">
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                {!! Form::label('date_range_filter', __('report.date_range') . ':') !!}
                {!! Form::text('date_range_filter', null, 
                    ['placeholder' => __('lang_v1.select_a_date_range'), 
                    'class' => 'form-control', 'readonly', 'id' => 'date_range_filter']); !!}
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
        <div class="col-md-2">
            <div class="form-group">
                <label>&nbsp;</label>
                <a class="btn btn-primary btn-block" href="{{ route('accounting.diagnosa') }}">
                    {{-- <i class="fa fa-print"></i>  --}}
                    Diagnosa
                </a>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="print_section">
        <h2>{{ session()->get('business.name') }} - @lang('accounting::lang.pnl_bisnis')</h2>
        <p>{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</p>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-success">
                <div class="box-header with-border text-center">
                    <h2 class="box-title">@lang( 'accounting::lang.pnl_bisnis')</h2>
                    <p>{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</p>
                    <p class="text-muted"><small>@lang('accounting::lang.pnl_bisnis_description')</small></p>
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

                    {{-- Calculate period labels --}}
                    @php
                        $month_count = count($months);
                        $current_month = $month_count > 0 ? $months[$month_count - 1] : null;
                        $last_month = $month_count > 1 ? $months[$month_count - 2] : null;
                        $current_key = $current_month['key'] ?? null;
                        $last_key = $last_month['key'] ?? null;

                        // Last Month label
                        $last_month_label = $last_month
                            ? \Carbon\Carbon::parse($last_month['start'])->format('M Y')
                            : '-';
                        // Current Month label
                        $current_month_label = $current_month
                            ? \Carbon\Carbon::parse($current_month['start'])->format('M Y')
                            : '-';
                        // YTD label
                        $ytd_label = ($current_month && $month_count > 0)
                            ? 'YTD s.d. ' . \Carbon\Carbon::parse($current_month['end'])->format('M Y')
                            : '-';
                    @endphp

                    {{-- Income Section --}}
                    <h4 class="text-success"><strong><i class="fa fa-arrow-up"></i> @lang('accounting::lang.income')</strong></h4>
                    <div class="table-responsive">
                    <table class="table table-striped table-bordered table-condensed" id="income_report_table" style="width:100%; font-size: 11px;">
                        <thead>
                            {{-- Main Category Headers --}}
                            <tr class="success">
                                <th class="text-center" style="width:80px; vertical-align: middle;" rowspan="2">@lang('accounting::lang.gl_code')</th>
                                <th class="text-center" style="vertical-align: middle; min-width:150px;" rowspan="2">@lang('user.name')</th>
                                @foreach($business_categories as $cat_key => $cat_info)
                                    <th class="text-center category-col" colspan="3" style="vertical-align: middle;">{{ $cat_info['name'] }}</th>
                                @endforeach
                                <th class="text-center category-col" colspan="3" style="vertical-align: middle; background-color: #f0f0f0;">Other</th>
                                <th class="text-center bg-primary text-black" style="width:100px; vertical-align: middle;" rowspan="2">Total</th>
                            </tr>
                            {{-- Sub-headers for periods --}}
                            <tr class="active" style="font-size: 10px;">
                                @foreach($business_categories as $cat_key => $cat_info)
                                    <th class="text-center" style="padding: 3px;">LM</th>
                                    <th class="text-center" style="padding: 3px;">CM</th>
                                    <th class="text-center" style="padding: 3px;">YTD</th>
                                @endforeach
                                <th class="text-center" style="padding: 3px; background-color: #f0f0f0;">LM</th>
                                <th class="text-center" style="padding: 3px; background-color: #f0f0f0;">CM</th>
                                <th class="text-center" style="padding: 3px; background-color: #f0f0f0;">YTD</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($income_accounts as $account)
                                <tr>
                                    <td>{{ $account->gl_code }}</td>
                                    <td>{{ $account->name }}</td>
                                    @foreach($business_categories as $cat_key => $cat_info)
                                        @php
                                            $cat_data = $account->category_balances[$cat_key] ?? ['last_month' => 0, 'current_month' => 0, 'ytd' => 0];
                                        @endphp
                                        <td class="text-right category-col">@format_currency($cat_data['last_month'])</td>
                                        <td class="text-right category-col">@format_currency($cat_data['current_month'])</td>
                                        <td class="text-right category-col"><strong>@format_currency($cat_data['ytd'])</strong></td>
                                    @endforeach
                                    @php
                                        $other_data = $account->category_balances['other'] ?? ['last_month' => 0, 'current_month' => 0, 'ytd' => 0];
                                    @endphp
                                    <td class="text-right category-col" style="background-color: #f0f0f0;">@format_currency($other_data['last_month'])</td>
                                    <td class="text-right category-col" style="background-color: #f0f0f0;">@format_currency($other_data['current_month'])</td>
                                    <td class="text-right category-col" style="background-color: #f0f0f0;"><strong>@format_currency($other_data['ytd'])</strong></td>
                                    <td class="text-right"><strong>@format_currency($account->balance)</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 3 + (count($business_categories) * 3) + 6 + 1 }}" class="text-center text-muted">@lang('lang_v1.no_data')</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="success">
                                <th colspan="2" class="text-right"><strong>@lang('accounting::lang.total_income')</strong></th>
                                @foreach($business_categories as $cat_key => $cat_info)
                                    @php $cat_tot = $category_totals['income'][$cat_key] ?? ['last_month' => 0, 'current_month' => 0, 'ytd' => 0]; @endphp
                                    <th class="text-right category-col">@format_currency($cat_tot['last_month'])</th>
                                    <th class="text-right category-col">@format_currency($cat_tot['current_month'])</th>
                                    <th class="text-right category-col"><strong>@format_currency($cat_tot['ytd'])</strong></th>
                                @endforeach
                                @php 
                                    $other_tot = $category_totals['income']['other'] ?? ['last_month' => 0, 'current_month' => 0, 'ytd' => 0]; 
                                @endphp
                                <th class="text-right category-col" style="background-color: #f0f0f0; color:black">@format_currency($other_tot['last_month'])</th>
                                <th class="text-right category-col" style="background-color: #f0f0f0; color:black">@format_currency($other_tot['current_month'])</th>
                                <th class="text-right category-col" style="background-color: #f0f0f0; color:black"><strong>@format_currency($other_tot['ytd'])</strong></th>
                                <th class="text-right"><strong>@format_currency($total_income)</strong></th>
                            </tr>
                        </tfoot>
                    </table>
                    </div>

                    <br/>

                    {{-- Expense Section --}}
                    <h4 class="text-danger"><strong><i class="fa fa-arrow-down"></i> @lang('accounting::lang.expenses')</strong></h4>
                    <div class="table-responsive">
                    <table class="table table-striped table-bordered table-condensed" id="expense_report_table" style="width:100%; font-size: 11px;">
                        <thead>
                            {{-- Main Category Headers --}}
                            <tr class="danger">
                                <th class="text-center" style="width:80px; vertical-align: middle;" rowspan="2">@lang('accounting::lang.gl_code')</th>
                                <th class="text-center" style="vertical-align: middle; min-width:150px;" rowspan="2">@lang('user.name')</th>
                                @foreach($business_categories as $cat_key => $cat_info)
                                    <th class="text-center category-col" colspan="3" style="vertical-align: middle;">{{ $cat_info['name'] }}</th>
                                @endforeach
                                <th class="text-center category-col" colspan="3" style="vertical-align: middle; background-color: #f0f0f0;">Other</th>
                                <th class="text-center bg-primary text-black" style="width:100px; vertical-align: middle;" rowspan="2">Total</th>
                            </tr>
                            {{-- Sub-headers for periods --}}
                            <tr class="active" style="font-size: 10px;">
                                @foreach($business_categories as $cat_key => $cat_info)
                                    <th class="text-center" style="padding: 3px;">LM</th>
                                    <th class="text-center" style="padding: 3px;">CM</th>
                                    <th class="text-center" style="padding: 3px;">YTD</th>
                                @endforeach
                                <th class="text-center" style="padding: 3px; background-color: #f0f0f0;">LM</th>
                                <th class="text-center" style="padding: 3px; background-color: #f0f0f0;">CM</th>
                                <th class="text-center" style="padding: 3px; background-color: #f0f0f0;">YTD</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expense_accounts as $account)
                                <tr>
                                    <td>{{ $account->gl_code }}</td>
                                    <td>{{ $account->name }}</td>
                                    @foreach($business_categories as $cat_key => $cat_info)
                                        @php
                                            $cat_data = $account->category_balances[$cat_key] ?? ['last_month' => 0, 'current_month' => 0, 'ytd' => 0];
                                        @endphp
                                        <td class="text-right category-col">@format_currency($cat_data['last_month'])</td>
                                        <td class="text-right category-col">@format_currency($cat_data['current_month'])</td>
                                        <td class="text-right category-col"><strong>@format_currency($cat_data['ytd'])</strong></td>
                                    @endforeach
                                    @php
                                        $other_data = $account->category_balances['other'] ?? ['last_month' => 0, 'current_month' => 0, 'ytd' => 0];
                                    @endphp
                                    <td class="text-right category-col" style="background-color: #f0f0f0;">@format_currency($other_data['last_month'])</td>
                                    <td class="text-right category-col" style="background-color: #f0f0f0;">@format_currency($other_data['current_month'])</td>
                                    <td class="text-right category-col" style="background-color: #f0f0f0;"><strong>@format_currency($other_data['ytd'])</strong></td>
                                    <td class="text-right"><strong>@format_currency($account->balance)</strong></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 2 + (count($business_categories) * 3) + 3 + 1 }}" class="text-center text-muted">@lang('lang_v1.no_data')</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="danger">
                                <th colspan="2" class="text-right"><strong>@lang('accounting::lang.total_expenses')</strong></th>
                                @foreach($business_categories as $cat_key => $cat_info)
                                    @php $cat_tot = $category_totals['expense'][$cat_key] ?? ['last_month' => 0, 'current_month' => 0, 'ytd' => 0]; @endphp
                                    <th class="text-right category-col">@format_currency($cat_tot['last_month'])</th>
                                    <th class="text-right category-col">@format_currency($cat_tot['current_month'])</th>
                                    <th class="text-right category-col"><strong>@format_currency($cat_tot['ytd'])</strong></th>
                                @endforeach
                                @php 
                                    $other_tot = $category_totals['expense']['other'] ?? ['last_month' => 0, 'current_month' => 0, 'ytd' => 0]; 
                                @endphp
                                <th class="text-right category-col" style="background-color: #f0f0f0;">@format_currency($other_tot['last_month'])</th>
                                <th class="text-right category-col" style="background-color: #f0f0f0;">@format_currency($other_tot['current_month'])</th>
                                <th class="text-right category-col" style="background-color: #f0f0f0;"><strong>@format_currency($other_tot['ytd'])</strong></th>
                                <th class="text-right"><strong>@format_currency($total_expense)</strong></th>
                            </tr>
                        </tfoot>
                    </table>
                    </div>

                    <br/>

                    {{-- Net Profit/Loss Section --}}
                    <h4><strong><i class="fa fa-calculator"></i> @lang('accounting::lang.net_profit') / @lang('accounting::lang.net_loss')</strong></h4>
                    <div class="table-responsive">
                    <table class="table table-bordered table-condensed" id="net_profit_table" style="width:100%; font-size: 11px;">
                        <thead>
                            <tr class="{{ $net_profit >= 0 ? 'bg-green' : 'bg-red' }}">
                                <th colspan="2" style="font-size: 14px; vertical-align: middle;" rowspan="2">
                                    <strong>
                                        @if($net_profit >= 0)
                                            <i class="fa fa-check-circle"></i> @lang('accounting::lang.net_profit')
                                        @else
                                            <i class="fa fa-times-circle"></i> @lang('accounting::lang.net_loss')
                                        @endif
                                    </strong>
                                </th>
                                @foreach($business_categories as $cat_key => $cat_info)
                                    <th class="text-center category-col" colspan="3" style="vertical-align: middle;">{{ $cat_info['name'] }}</th>
                                @endforeach
                                <th class="text-center category-col" colspan="3" style="vertical-align: middle; background-color: #f0f0f0; color:black">Other</th>
                                <th class="text-center bg-primary" style="width:100px; vertical-align: middle;" rowspan="2">Total</th>
                            </tr>
                            <tr class="active" style="font-size: 10px;">
                                @foreach($business_categories as $cat_key => $cat_info)
                                    <th class="text-center" style="padding: 3px;">LM</th>
                                    <th class="text-center" style="padding: 3px;">CM</th>
                                    <th class="text-center" style="padding: 3px;">YTD</th>
                                @endforeach
                                <th class="text-center" style="padding: 3px; background-color: #f0f0f0;">LM</th>
                                <th class="text-center" style="padding: 3px; background-color: #f0f0f0;">CM</th>
                                <th class="text-center" style="padding: 3px; background-color: #f0f0f0;">YTD</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="{{ $net_profit >= 0 ? 'bg-cyan' : 'bg-blue' }}">
                                <td colspan="2" style="font-size: 14px;"><strong>Nilai</strong></td>
                                @foreach($business_categories as $cat_key => $cat_info)
                                    @php
                                        $cat_net = $category_net_profit[$cat_key] ?? ['last_month' => 0, 'current_month' => 0, 'ytd' => 0];
                                    @endphp
                                    <td class="text-right category-col {{ $cat_net['last_month'] < 0 ? 'text-danger' : '' }}">@format_currency($cat_net['last_month'])</td>
                                    <td class="text-right category-col {{ $cat_net['current_month'] < 0 ? 'text-danger' : '' }}">@format_currency($cat_net['current_month'])</td>
                                    <td class="text-right category-col {{ $cat_net['ytd'] < 0 ? 'text-danger' : '' }}"><strong>@format_currency($cat_net['ytd'])</strong></td>
                                @endforeach
                                @php
                                    $other_net = $category_net_profit['other'] ?? ['last_month' => 0, 'current_month' => 0, 'ytd' => 0];
                                @endphp
                                <td class="text-right category-col {{ $other_net['last_month'] < 0 ? 'text-danger' : '' }}" style="background-color: #f0f0f0;">@format_currency($other_net['last_month'])</td>
                                <td class="text-right category-col {{ $other_net['current_month'] < 0 ? 'text-danger' : '' }}" style="background-color: #f0f0f0;">@format_currency($other_net['current_month'])</td>
                                <td class="text-right category-col {{ $other_net['ytd'] < 0 ? 'text-danger' : '' }}" style="background-color: #f0f0f0;"><strong>@format_currency($other_net['ytd'])</strong></td>
                                <td class="text-right" style="font-size: 14px;">
                                    <strong class="{{ $net_profit < 0 ? 'text-danger' : '' }}">@format_currency($net_profit)</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    </div>

                    {{-- Monthly Comparison Summary --}}
                    <h4><strong><i class="fa fa-chart-bar"></i> @lang('accounting::lang.monthly_summary')</strong></h4>
                    <div class="info-box-content" style="margin-bottom: 15px;">
                        <p class="text-muted"><small>* Kolom perbandingan berdasarkan periode filter yang dipilih</small></p>
                    </div>
                    <table class="table table-bordered" id="monthly_summary_table" style="width:100%">
                        <thead>
                            <tr class="bg-info">
                                <th class="text-center" style="vertical-align: middle; width: 25%;">Keterangan</th>
                                <th class="text-center" style="vertical-align: middle;">
                                    Last Month
                                    <div class="text-muted" style="font-size: 11px;">{{ $last_month_label }}</div>
                                </th>
                                <th class="text-center" style="vertical-align: middle;">
                                    Current Month
                                    <div class="text-muted" style="font-size: 11px;">{{ $current_month_label }}</div>
                                </th>
                                <th class="text-center" style="vertical-align: middle;">
                                    YTD
                                    <div class="text-muted" style="font-size: 11px;">{{ $ytd_label }}</div>
                                </th>
                                <th class="text-center bg-warning" style="vertical-align: middle;">Varian (MoM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                // Note: For now, we use the full period data since monthly breakdown 
                                // per category would require significant controller changes.
                                // This shows YTD = Total (full period)
                                // Current Month and Last Month would need monthly breakdown data
                                
                                // Using approximation: divide by months count for average display
                                $month_count_safe = max(1, $month_count);
                                $avg_income_per_month = $total_income / $month_count_safe;
                                $avg_expense_per_month = $total_expense / $month_count_safe;
                                $avg_net_per_month = $net_profit / $month_count_safe;
                                
                                // Display values (YTD = actual total, months = approximation indicator)
                                $ytd_income = $total_income;
                                $ytd_expense = $total_expense;
                                $ytd_net = $net_profit;
                            @endphp
                            <tr class="success">
                                <td><strong>@lang('accounting::lang.total_income')</strong></td>
                                <td class="text-right">
                                    <span class="text-muted">-</span>
                                </td>
                                <td class="text-right">
                                    <span class="text-muted">-</span>
                                </td>
                                <td class="text-right"><strong>@format_currency($ytd_income)</strong></td>
                                <td class="text-right text-muted">-</td>
                            </tr>
                            <tr class="danger">
                                <td><strong>@lang('accounting::lang.total_expenses')</strong></td>
                                <td class="text-right">
                                    <span class="text-muted">-</span>
                                </td>
                                <td class="text-right">
                                    <span class="text-muted">-</span>
                                </td>
                                <td class="text-right"><strong>@format_currency($ytd_expense)</strong></td>
                                <td class="text-right text-muted">-</td>
                            </tr>
                            <tr class="{{ $net_profit >= 0 ? 'bg-green' : 'bg-red' }}" style="color: white;">
                                <td><strong>{{ $net_profit >= 0 ? __('accounting::lang.net_profit') : __('accounting::lang.net_loss') }}</strong></td>
                                <td class="text-right">
                                    <span>-</span>
                                </td>
                                <td class="text-right">
                                    <span>-</span>
                                </td>
                                <td class="text-right"><strong>@format_currency($ytd_net)</strong></td>
                                <td class="text-right">-</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> 
                        <strong>Info:</strong> Untuk melihat detail Last Month, Current Month, dan YTD per akun, gunakan laporan 
                        <a href="{{ route('accounting.pnlYtd') }}" class="alert-link">P&L Year-to-Date</a>.
                    </div>

                    {{-- Summary --}}
                    <div class="well well-sm">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <h5>@lang('accounting::lang.total_income')</h5>
                                <h3 class="text-success">@format_currency($total_income)</h3>
                            </div>
                            <div class="col-md-4 text-center">
                                <h5>@lang('accounting::lang.total_expenses')</h5>
                                <h3 class="text-primary">@format_currency($total_expense)</h3>
                            </div>
                            <div class="col-md-4 text-center">
                                <h5>{{ $net_profit >= 0 ? __('accounting::lang.net_profit') : __('accounting::lang.net_loss') }}</h5>
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

        // Date range picker
        dateRangeSettings.startDate = moment('{{ $start_date }}');
        dateRangeSettings.endDate = moment('{{ $end_date }}');
        $('#date_range_filter').daterangepicker(
            dateRangeSettings,
            function (start, end) {
                $('#date_range_filter').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                apply_filter();
            }
        );
        $('#date_range_filter').val(moment('{{ $start_date }}').format(moment_date_format) + ' ~ ' + moment('{{ $end_date }}').format(moment_date_format));

        function apply_filter(){
            var dateRange = $('#date_range_filter').val();
            var dates = dateRange.split(' ~ ');
            var start_date = moment(dates[0], moment_date_format).format('YYYY-MM-DD');
            var end_date = moment(dates[1], moment_date_format).format('YYYY-MM-DD');

            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('start_date', start_date);
            urlParams.set('end_date', end_date);
            window.location.search = urlParams;
        }

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
            exportTableToExcel('income_report_table', 'Income_Bisnis_Report_{{$start_date}}_to_{{$end_date}}');
        });

        $('#export_expense_excel').on('click', function() {
            exportTableToExcel('expense_report_table', 'Expense_Bisnis_Report_{{$start_date}}_to_{{$end_date}}');
        });

        $('#export_all_excel').on('click', function() {
            var categoryCount = {{ count($business_categories) }};
            // GL Code + Name + Categories + Pro Shop + Sudest Cafe + Other + Total
            // 2 + N + 3 + 1 = 6 + N
            var totalCols = 6 + categoryCount;
            
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
            html += '<tr><th colspan="' + totalCols + '" style="text-align:center; font-size:18px;">P&L Bisnis Report</th></tr>';
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
            downloadLink.download = 'PNL_Bisnis_Report_{{$start_date}}_to_{{$end_date}}.xls';
            downloadLink.click();
            document.body.removeChild(downloadLink);
        });

        // Initialize DataTables
        $('#income_report_table').DataTable({
            dom: 'frtip',
            paging: false,
            searching: true,
            info: false,
            ordering: true,
            order: [[0, 'asc']]
        });

        $('#expense_report_table').DataTable({
            dom: 'frtip',
            paging: false,
            searching: true,
            info: false,
            ordering: true,
            order: [[0, 'asc']]
        });
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
