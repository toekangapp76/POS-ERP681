@extends('layouts.app')

@section('title', __('accounting::lang.profit_loss'))

@section('content')

@include('accounting::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1>@lang( 'accounting::lang.profit_loss' )</h1>
</section>

<section class="content no-print" style="min-height:auto !important;">
    <div class="row">
        <div class="col-md-3 col-md-offset-1">
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
    </div>
</section>

<section class="content">
    <div class="print_section">
        <h2>{{ session()->get('business.name') }} - @lang('accounting::lang.profit_loss')</h2>
        <p>{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</p>
    </div>

    <div class="row">
        <div class="col-md-10 col-md-offset-1">
            <div class="box box-success">
                <div class="box-header with-border text-center">
                    <h2 class="box-title">@lang( 'accounting::lang.profit_loss')</h2>
                    <p>{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</p>
                </div>
    
                <div class="box-body">
                    {{-- Income Section --}}
                    <h4 class="text-success"><strong><i class="fa fa-arrow-up"></i> @lang('accounting::lang.income')</strong></h4>
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr class="success">
                                <th style="width:120px;">@lang('accounting::lang.gl_code')</th>
                                <th>@lang('user.name')</th>
                                <th class="text-right" style="width:150px;">@lang('sale.total')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($income_accounts as $account)
                                <tr>
                                    <td>{{ $account->gl_code }}</td>
                                    <td>{{ $account->name }}</td>
                                    <td class="text-right">@format_currency($account->balance)</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">@lang('lang_v1.no_data')</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="success">
                                <th colspan="2" class="text-right"><strong>@lang('accounting::lang.total_income')</strong></th>
                                <th class="text-right"><strong>@format_currency($total_income)</strong></th>
                            </tr>
                        </tfoot>
                    </table>

                    <br/>

                    {{-- Expense Section --}}
                    <h4 class="text-danger"><strong><i class="fa fa-arrow-down"></i> @lang('accounting::lang.expenses')</strong></h4>
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr class="danger">
                                <th style="width:120px;">@lang('accounting::lang.gl_code')</th>
                                <th>@lang('user.name')</th>
                                <th class="text-right" style="width:150px;">@lang('sale.total')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expense_accounts as $account)
                                <tr>
                                    <td>{{ $account->gl_code }}</td>
                                    <td>{{ $account->name }}</td>
                                    <td class="text-right">@format_currency($account->balance)</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">@lang('lang_v1.no_data')</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="danger">
                                <th colspan="2" class="text-right"><strong>@lang('accounting::lang.total_expenses')</strong></th>
                                <th class="text-right"><strong>@format_currency($total_expense)</strong></th>
                            </tr>
                        </tfoot>
                    </table>

                    <br/>

                    {{-- Net Profit/Loss Section --}}
                    <table class="table table-bordered">
                        <tr class="{{ $net_profit >= 0 ? 'bg-green' : 'bg-red' }}">
                            <th class="text-right" style="font-size: 18px;">
                                <strong>
                                    @if($net_profit >= 0)
                                        <i class="fa fa-check-circle"></i> @lang('accounting::lang.net_profit')
                                    @else
                                        <i class="fa fa-times-circle"></i> @lang('accounting::lang.net_loss')
                                    @endif
                                </strong>
                            </th>
                            <th class="text-right" style="width:150px; font-size: 18px;">
                                <strong>@format_currency(abs($net_profit))</strong>
                            </th>
                        </tr>
                    </table>

                    {{-- Summary --}}
                    <div class="well well-sm">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <h5>@lang('accounting::lang.total_income')</h5>
                                <h3 class="text-success">@format_currency($total_income)</h3>
                            </div>
                            <div class="col-md-4 text-center">
                                <h5>@lang('accounting::lang.total_expenses')</h5>
                                <h3 class="text-danger">@format_currency($total_expense)</h3>
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
