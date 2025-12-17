@extends('layouts.app')

@section('title', __('accounting::lang.trial_balance'))

@section('content')

@include('accounting::layouts.nav')

<section class="content">
    <div class="row">
        <div class="col-md-3 col-md-offset-1">
            <div class="form-group">
                {!! Form::label('month_filter', __('lang_v1.month') . ':') !!}
                {!! Form::month('month_filter', $month, 
                    ['class' => 'form-control', 'id' => 'month_filter']); !!}
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
                                <th>@lang( 'accounting::lang.ending_balance')</th>
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
                                    <td><span data-orig-value="{{$account->beginning_balance}}">@format_currency($account->beginning_balance)</span></td>
                                    <td><span data-orig-value="{{$account->debit_balance}}">@format_currency($account->debit_balance)</span></td>
                                    <td><span data-orig-value="{{$account->credit_balance}}">@format_currency($account->credit_balance)</span></td>
                                    <td><span data-orig-value="{{$account->ending_balance}}">@format_currency($account->ending_balance)</span></td>
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

        // Initialize DataTable with export buttons and sorting
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
            searching: true,
            info: false,
            ordering: true,
            order: [[0, 'asc']], // Sort by GL Code by default
            footerCallback: function (row, data, start, end, display) {
                var api = this.api();
                
                // Calculate totals
                var total_beginning = api.column(2).data().reduce(function (a, b) {
                    return parseFloat(a) + parseFloat($(b).data('orig-value') || 0);
                }, 0);
                
                var total_debit = api.column(3).data().reduce(function (a, b) {
                    return parseFloat(a) + parseFloat($(b).data('orig-value') || 0);
                }, 0);
                
                var total_credit = api.column(4).data().reduce(function (a, b) {
                    return parseFloat(a) + parseFloat($(b).data('orig-value') || 0);
                }, 0);
                
                var total_ending = api.column(5).data().reduce(function (a, b) {
                    return parseFloat(a) + parseFloat($(b).data('orig-value') || 0);
                }, 0);
                
                // Update footer
                $(api.column(2).footer()).html(__currency_trans_from_en(total_beginning, true));
                $(api.column(3).footer()).html(__currency_trans_from_en(total_debit, true));
                $(api.column(4).footer()).html(__currency_trans_from_en(total_credit, true));
                $(api.column(5).footer()).html(__currency_trans_from_en(total_ending, true));
            }
        });

        // Month filter change handler
        $('#month_filter').on('change', function() {
            var month = $(this).val();
            if (month) {
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('month', month);
                window.location.search = urlParams;
            }
        });
    });

</script>

@stop