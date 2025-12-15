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
    </div>
    
    <div class="row">
        <div class="col-md-10 col-md-offset-1">
            <div class="box box-warning">
                <div class="box-header with-border text-center">
                    <h2 class="box-title">@lang( 'accounting::lang.balance_sheet')</h2>
                    <p>{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</p>
                </div>
    
                <div class="box-body">
                    
                    {{-- Export Button --}}
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-success" id="export_balance_sheet_excel">
                                <i class="fa fa-file-excel-o"></i> Export Balance Sheet (Excel)
                            </button>
                        </div>
                    </div>

                    @php
                        $total_assets = 0;
                        $total_liab_owners = 0;
                    @endphp
    
                        <table class="table table-stripped table-bordered" id="balance_sheet_table" style="min-height: 300px">
                            <thead>
                                <tr>
                                    <th class="success" colspan="3">@lang( 'accounting::lang.assets')</th>
                                    <th class="warning" colspan="3">@lang( 'accounting::lang.liab_owners_capital')</th>
                                </tr>
                                <tr>
                                    <th class="success" style="width:80px;">No COA</th>
                                    <th class="success">@lang( 'user.name')</th>
                                    <th class="success">@lang( 'sale.total')</th>
                                    <th class="warning" style="width:80px;">No COA</th>
                                    <th class="warning">@lang( 'user.name')</th>
                                    <th class="warning">@lang( 'sale.total')</th>
                                </tr>
                            </thead>
    
                            <tr>
                                <td class="col-md-6" colspan="3">
                                    <table class="table">
                                        @foreach($assets as $asset)
                                            @php
                                                $total_assets += $asset->balance
                                            @endphp
    
                                            <tr>
                                                <td style="width:100px;">{{$asset->gl_code ?? '-'}}</td>
                                                <th>{{$asset->name}}</th>
                                                <td>@format_currency($asset->balance)</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </td>
    
                                <td class="col-md-6" colspan="3">
                                    <table class="table">
                                        @foreach($liabilities as $liability)
    
                                            @php
                                                $total_liab_owners += $liability->balance
                                            @endphp
    
                                            <tr>
                                                <td style="width:100px;">{{$liability->gl_code ?? '-'}}</td>
                                                <th>{{$liability->name}}</th>
                                                <td>@format_currency($liability->balance)</td>
                                            </tr>
                                        @endforeach
    
                                        @foreach($equities as $equity)
                                            @php
                                                $total_liab_owners += $equity->balance
                                            @endphp
                                            
                                            <tr>
                                                <td style="width:100px;">{{$equity->gl_code ?? '-'}}</td>
                                                <th>{{$equity->name}}</th>
                                                <td>@format_currency($equity->balance)</td>
                                            </tr>
                                        @endforeach
                                    </table>
                                </td>
                            </tr>
    
                            <tr>
                                <td class="col-md-6" colspan="3">
                                    <span>
                                        <strong>@lang( 'accounting::lang.total_assets'): </strong>
                                    </span>
    
                                    <span>@format_currency($total_assets)</span>
                                </td>
    
                                <td class="col-md-6" colspan="3">
                                    <span>
                                        <strong>@lang( 'accounting::lang.total_liab_owners'): </strong>
                                    </span>
    
                                    <span>@format_currency($total_liab_owners)</span>
                                </td>
                            </tr>
    
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

        // Export Balance Sheet to Excel
        $('#export_balance_sheet_excel').on('click', function() {
            var html = '<table border="1">';
            html += '<tr><th colspan="6" style="text-align:center; font-size:18px;">Balance Sheet</th></tr>';
            html += '<tr><th colspan="6" style="text-align:center;">{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</th></tr>';
            html += '<tr><td colspan="6">&nbsp;</td></tr>';
            
            // Header row
            html += '<tr>';
            html += '<th colspan="3" style="background-color:#00a65a; color:white; text-align:center;">{{ __('accounting::lang.assets') }}</th>';
            html += '<th colspan="3" style="background-color:#f39c12; color:white; text-align:center;">{{ __('accounting::lang.liab_owners_capital') }}</th>';
            html += '</tr>';
            
            html += '<tr>';
            html += '<th style="background-color:#00a65a; color:white;">No COA</th>';
            html += '<th style="background-color:#00a65a; color:white;">{{ __('user.name') }}</th>';
            html += '<th style="background-color:#00a65a; color:white;">{{ __('sale.total') }}</th>';
            html += '<th style="background-color:#f39c12; color:white;">No COA</th>';
            html += '<th style="background-color:#f39c12; color:white;">{{ __('user.name') }}</th>';
            html += '<th style="background-color:#f39c12; color:white;">{{ __('sale.total') }}</th>';
            html += '</tr>';
            
            // Prepare assets and liabilities arrays
            var assets = @json($assets);
            var liabilities = @json($liabilities);
            var equities = @json($equities);
            var liab_equity = liabilities.concat(equities);
            
            var maxRows = Math.max(assets.length, liab_equity.length);
            var totalAssets = 0;
            var totalLiabEquity = 0;
            
            // Data rows
            for(var i = 0; i < maxRows; i++) {
                html += '<tr>';
                
                // Assets column
                if(i < assets.length) {
                    var asset = assets[i];
                    totalAssets += parseFloat(asset.balance);
                    html += '<td>' + (asset.gl_code || '-') + '</td>';
                    html += '<td>' + asset.name + '</td>';
                    html += '<td style="text-align:right;">' + parseFloat(asset.balance).toFixed(2) + '</td>';
                } else {
                    html += '<td></td><td></td><td></td>';
                }
                
                // Liabilities & Equity column
                if(i < liab_equity.length) {
                    var liab = liab_equity[i];
                    totalLiabEquity += parseFloat(liab.balance);
                    html += '<td>' + (liab.gl_code || '-') + '</td>';
                    html += '<td>' + liab.name + '</td>';
                    html += '<td style="text-align:right;">' + parseFloat(liab.balance).toFixed(2) + '</td>';
                } else {
                    html += '<td></td><td></td><td></td>';
                }
                
                html += '</tr>';
            }
            
            // Total row
            html += '<tr style="font-weight:bold;">';
            html += '<td colspan="2" style="text-align:right;">{{ __('accounting::lang.total_assets') }}:</td>';
            html += '<td style="text-align:right;">' + totalAssets.toFixed(2) + '</td>';
            html += '<td colspan="2" style="text-align:right;">{{ __('accounting::lang.total_liab_owners') }}:</td>';
            html += '<td style="text-align:right;">' + totalLiabEquity.toFixed(2) + '</td>';
            html += '</tr>';
            
            html += '</table>';
            
            var url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
            var downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            downloadLink.href = url;
            downloadLink.download = 'Balance_Sheet_{{$start_date}}_to_{{$end_date}}.xls';
            downloadLink.click();
            document.body.removeChild(downloadLink);
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

@stop