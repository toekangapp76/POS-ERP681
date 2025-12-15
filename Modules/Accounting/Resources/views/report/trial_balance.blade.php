@extends('layouts.app')

@section('title', __('accounting::lang.trial_balance'))

@section('content')

@include('accounting::layouts.nav')

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
                    <h2 class="box-title">@lang( 'accounting::lang.trial_balance')</h2>
                    <p>{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</p>
                </div>
    
                <div class="box-body">
                    <table class="table table-stripped" id="trial_balance_table">
                        <thead>
                            <tr>
                                <th>@lang( 'accounting::lang.gl_code')</th>
                                <th>@lang( 'user.name')</th>
                                <th>@lang( 'accounting::lang.opening_balance')</th>
                                <th>@lang( 'accounting::lang.debit')</th>
                                <th>@lang( 'accounting::lang.credit')</th>
                                <th>@lang( 'lang_v1.balance')</th>
                            </tr>
                        </thead>
    
                        @php
                            $total_beginning = 0;
                            $total_debit = 0;
                            $total_credit = 0;
                            $total_ending = 0;
                        @endphp
    
                        <tbody>
                            @foreach($accounts as $account)
    
                            @php
                                $total_beginning += $account->beginning_balance;
                                $total_debit += $account->debit_balance;
                                $total_credit += $account->credit_balance;
                                $total_ending += $account->ending_balance;
                            @endphp
    
                                <tr>
                                    <td>{{$account->gl_code ?? '-'}}</td>
                                    <td>{{$account->name}}</td>
                                    <td>@format_currency($account->beginning_balance)</td>
                                    <td>@format_currency($account->debit_balance)</td>
                                    <td>@format_currency($account->credit_balance)</td>
                                    <td>@format_currency($account->ending_balance)</td>
                                </tr>
                            @endforeach
                        </tbody>
    
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th></th>
                                <th class="total_beginning">@format_currency($total_beginning)</th>
                                <th class="total_debit">@format_currency($total_debit)</th>
                                <th class="total_credit">@format_currency($total_credit)</th>
                                <th class="total_ending">@format_currency($total_ending)</th>
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

        // Initialize DataTable with export buttons
        $('#trial_balance_table').DataTable({
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel-o"></i> Excel',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fa fa-file-pdf-o"></i> PDF',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fa fa-print"></i> Print',
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ],
            paging: false,
            searching: false,
            info: false,
            ordering: false
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