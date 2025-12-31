@extends('layouts.app')

@section('title', __('gym::lang.deferred_revenue'))

@section('content')

    @include('accounting::layouts.nav')


    <section class="content-header">
        <h1>@lang('gym::lang.deferred_revenue')
            <small>@lang('gym::lang.deferred_revenue_schedule')</small>
        </h1>
    </section>

    <section class="content">
        {{-- Summary Cards --}}
        <div class="row">
            <div class="col-md-4">
                <div class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fa fa-clock"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">@lang('gym::lang.pending_recognition')</span>
                        <span class="info-box-number">{{ number_format($summary['total_pending'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box bg-green">
                    <span class="info-box-icon"><i class="fa fa-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">@lang('gym::lang.recognized')</span>
                        <span class="info-box-number">{{ number_format($summary['total_recognized'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box bg-red">
                    <span class="info-box-icon"><i class="fa fa-exclamation-triangle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Due for Recognition</span>
                        <span
                            class="info-box-number">{{ number_format($summary['due_for_recognition'], 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="box box-solid">
            <div class="box-header">
                <h3 class="box-title"><i class="fa fa-filter"></i> @lang('report.filters')</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('date_range_filter', __('gym::lang.recognition_date') . ':') !!}
                            {!! Form::text('date_range_filter', null, 
                                ['placeholder' => __('lang_v1.select_a_date_range'), 
                                'class' => 'form-control', 'readonly', 'id' => 'date_range_filter']); !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Status:</label>
                            <select class="form-control" id="status_filter">
                                <option value="all">@lang('lang_v1.all')</option>
                                <option value="pending">Pending</option>
                                <option value="recognized">Recognized</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary btn-block" id="filter_btn">
                                <i class="fa fa-search"></i> @lang('report.apply_filters')
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button class="btn btn-success btn-block" id="process_all_btn">
                                <i class="fa fa-play"></i> @lang('gym::lang.process_recognition')
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Data Table --}}
        <div class="box">
            <div class="box-body">
                <table class="table table-bordered table-striped" id="deferred_revenue_table">
                    <thead>
                        <tr>
                            <th>@lang('gym::lang.recognition_date')</th>
                            <th>@lang('gym::lang.member')</th>
                            <th>@lang('gym::lang.package')</th>
                            <th>@lang('gym::lang.period_start')</th>
                            <th>@lang('gym::lang.period_end')</th>
                            <th>@lang('gym::lang.active_days')</th>
                            <th>@lang('gym::lang.recognition_amount')</th>
                            <th>Deposit Acc</th>
                            <th>Revenue Acc</th>
                            <th>Status</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </section>
@endsection

@section('javascript')
    <script>
        $(document).ready(function () {
            // Declare deferred_table variable first
            var deferred_table;

            // Initialize daterangepicker using global settings like journal-entry
            $('#date_range_filter').daterangepicker(
                dateRangeSettings,
                function (start, end) {
                    $('#date_range_filter').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                    if (deferred_table) {
                        deferred_table.ajax.reload();
                    }
                }
            );
            $('#date_range_filter').on('cancel.daterangepicker', function(ev, picker) {
                $('#date_range_filter').val('');
                if (deferred_table) {
                    deferred_table.ajax.reload();
                }
            });

            // Initialize DataTable
            deferred_table = $('#deferred_revenue_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route("gym.deferred-revenue.index") }}',
                    data: function (d) {
                        d.status = $('#status_filter').val();
                        var dateRange = $('#date_range_filter').val();
                        if (dateRange) {
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
                            d.start_date = start;
                            d.end_date = end;
                        }
                    }
                },
                columns: [
                    { data: 'recognition_date', name: 'recognition_date' },
                    { data: 'member_name', name: 'transaction.contact.name' },
                    { data: 'package_name', name: 'gymPackage.name' },
                    { data: 'period_start', name: 'period_start' },
                    { data: 'period_end', name: 'period_end' },
                    { data: 'active_days', name: 'active_days' },
                    { data: 'recognition_amount', name: 'recognition_amount', className: 'text-right' },
                    { data: 'deposit_account_name', name: 'deposit_account_name' },
                    { data: 'revenue_account_name', name: 'revenue_account_name' },
                    { data: 'status', name: 'status' },
                    { data: 'action', name: 'action', orderable: false, searchable: false },
                ],
                order: [[0, 'asc']],
            });

            // Filter button
            $('#filter_btn').on('click', function () {
                deferred_table.ajax.reload();
            });

            // Process all button
            $('#process_all_btn').on('click', function () {
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

                $.ajax({
                    url: '{{ route("gym.deferred-revenue.process") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        as_of_date: moment().format('YYYY-MM-DD')
                    },
                    success: function (response) {
                        if (response.success) {
                            toastr.success(response.msg);
                            deferred_table.ajax.reload();
                            // Reload page to update summary cards
                            location.reload();
                        } else {
                            toastr.error(response.msg);
                        }
                    },
                    error: function () {
                        toastr.error('Something went wrong');
                    },
                    complete: function () {
                        btn.prop('disabled', false).html('<i class="fa fa-play"></i> @lang("gym::lang.process_recognition")');
                    }
                });
            });

            // Process single
            $(document).on('click', '.process-single', function () {
                var btn = $(this);
                var id = btn.data('id');

                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

                $.ajax({
                    url: '{{ url("gym/deferred-revenue") }}/' + id + '/process',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function (response) {
                        if (response.success) {
                            toastr.success(response.msg);
                            deferred_table.ajax.reload();
                        } else {
                            toastr.error(response.msg);
                        }
                    },
                    error: function () {
                        toastr.error('Something went wrong');
                    },
                    complete: function () {
                        btn.prop('disabled', false).html('<i class="fa fa-check"></i> @lang("gym::lang.process_recognition")');
                    }
                });
            });
        });
    </script>
@endsection