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

        // Helper function to strip HTML tags
        function stripHtml(html) {
            var tmp = document.createElement("DIV");
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || "";
        }

        // Initialize DataTable with export buttons and sorting
        $('#trial_balance_table').DataTable({
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel-o"></i> Excel',
                    exportOptions: {
                        columns: ':visible',
                        format: {
                            body: function(data, row, column, node) {
                                // For columns with currency (columns 2-5), extract numeric value from data-orig-value
                                if (column >= 2 && column <= 5) {
                                    var $el = $(data);
                                    if ($el.data('orig-value') !== undefined) {
                                        return parseFloat($el.data('orig-value'));
                                    }
                                    // Fallback: strip HTML and return text
                                    return stripHtml(data);
                                }
                                return stripHtml(data);
                            }
                        }
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fa fa-file-pdf-o"></i> PDF',
                    exportOptions: {
                        columns: ':visible',
                        format: {
                            body: function(data, row, column, node) {
                                if (column >= 2 && column <= 5) {
                                    var $el = $(data);
                                    if ($el.data('orig-value') !== undefined) {
                                        // Format number with Indonesian locale for display
                                        return parseFloat($el.data('orig-value')).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    }
                                    return stripHtml(data);
                                }
                                return stripHtml(data);
                            }
                        }
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

        dateRangeSettings.startDate = moment('{{$start_date}}');
        dateRangeSettings.endDate = moment('{{$end_date}}');
        $('#date_range_filter').daterangepicker(
            dateRangeSettings,
            function(start, end) {
                $('#date_range_filter').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));

                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('start_date', start.format('YYYY-MM-DD'));
                urlParams.set('end_date', end.format('YYYY-MM-DD'));
                urlParams.delete('month');
                window.location.search = urlParams;
            }
        );
        $('#date_range_filter').val(
            moment('{{$start_date}}').format(moment_date_format) + ' ~ ' + moment('{{$end_date}}').format(moment_date_format)
        );

        $('#date_range_filter').on('cancel.daterangepicker', function(ev, picker) {
            $('#date_range_filter').val('');
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.delete('start_date');
            urlParams.delete('end_date');
            urlParams.delete('month');
            window.location.search = urlParams;
        });
    });

</script>

@stop
