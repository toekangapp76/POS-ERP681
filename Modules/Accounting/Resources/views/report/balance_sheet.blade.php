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
                    
                    <table class="table table-striped table-bordered" id="balance_sheet_table">
                        <thead>
                            <tr>
                                <th>@lang('accounting::lang.gl_code')</th>
                                <th>@lang('user.name')</th>
                                <th>@lang('accounting::lang.account_type')</th>
                                <th>@lang('accounting::lang.account_sub_type')</th>
                                <th class="text-right">@lang('sale.total')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $total_assets = 0;
                                $total_liabilities = 0;
                                $total_equity = 0;
                            @endphp
                            @foreach($all_accounts as $account)
                                @php
                                    if ($account->account_primary_type == 'asset') {
                                        $total_assets += $account->balance;
                                    } elseif ($account->account_primary_type == 'liability') {
                                        $total_liabilities += $account->balance;
                                    } elseif ($account->account_primary_type == 'equity') {
                                        $total_equity += $account->balance;
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $account->gl_code ?? '-' }}</td>
                                    <td>{{ $account->name }}</td>
                                    <td>{{ __('accounting::lang.' . $account->account_primary_type) }}</td>
                                    <td>{{ $account->sub_type ? __('accounting::lang.' . $account->sub_type) : '-' }}</td>
                                    <td class="text-right"><span data-orig-value="{{ $account->balance }}">@format_currency($account->balance)</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-success">
                                <th colspan="4" class="text-right">@lang('accounting::lang.total_assets'):</th>
                                <th class="text-right total-assets">@format_currency($total_assets)</th>
                            </tr>
                            <tr class="bg-warning">
                                <th colspan="4" class="text-right">@lang('accounting::lang.total_liabilities'):</th>
                                <th class="text-right total-liabilities">@format_currency($total_liabilities)</th>
                            </tr>
                            <tr class="bg-info">
                                <th colspan="4" class="text-right">@lang('accounting::lang.total_equity'):</th>
                                <th class="text-right total-equity">@format_currency($total_equity)</th>
                            </tr>
                            <tr class="bg-primary">
                                <th colspan="4" class="text-right">@lang('accounting::lang.total_liab_owners'):</th>
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

        // Initialize DataTable with export buttons and sorting
        $('#balance_sheet_table').DataTable({
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel-o"></i> Excel',
                    title: 'Balance Sheet - {{@format_date($start_date)}} to {{@format_date($end_date)}}',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fa fa-file-pdf-o"></i> PDF',
                    title: 'Balance Sheet - {{@format_date($start_date)}} to {{@format_date($end_date)}}',
                    exportOptions: {
                        columns: ':visible'
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
            columnDefs: [
                { targets: [4], orderable: true, type: 'num' } // Make balance column sortable as number
            ]
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