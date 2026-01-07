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
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('date_range_filter', __('gym::lang.recognition_date') . ':') !!}
                            {!! Form::text('date_range_filter', null, 
                                ['placeholder' => __('lang_v1.select_a_date_range'), 
                                'class' => 'form-control', 'readonly', 'id' => 'date_range_filter']); !!}
                        </div>
                    </div>
                    <div class="col-md-2">
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
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button class="btn btn-primary btn-block" id="filter_btn">
                                <i class="fa fa-search"></i> @lang('report.apply_filters')
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button class="btn btn-success btn-block" id="process_all_btn">
                                <i class="fa fa-play"></i> @lang('gym::lang.process_recognition')
                            </button>
                        </div>
                    </div>
                </div>
                <div class="row" style="margin-top: 10px;">
                    <div class="col-md-2">
                        <button class="btn btn-warning" id="generate_missing_btn">
                            <i class="fa fa-magic"></i> Generate Missing Schedules
                        </button>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-info" id="diagnostic_btn">
                            <i class="fa fa-stethoscope"></i> Diagnostic
                        </button>
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
                            <th>No Reff GL</th>
                            <th class="text-right">Total Membership</th>
                            <th>@lang('gym::lang.recognition_amount')</th>
                            <th class="text-right">Remaining Value</th>
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
                scrollX: true,
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
                    { data: 'ref_no', name: 'ref_no', orderable: false, searchable: false },
                    { data: 'total_membership', name: 'total_membership', className: 'text-right', orderable: false, searchable: false },
                    { data: 'recognition_amount', name: 'recognition_amount', className: 'text-right' },
                    { data: 'remaining_value', name: 'remaining_value', className: 'text-right', orderable: false, searchable: false },
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

            // Generate Missing Schedules
            $('#generate_missing_btn').on('click', function () {
                var btn = $(this);
                
                if (!confirm('This will generate deferred revenue schedules for existing subscriptions that don\'t have schedules yet. Continue?')) {
                    return;
                }
                
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating...');

                $.ajax({
                    url: '{{ route("gym.deferred-revenue.generate-missing") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function (response) {
                        if (response.success) {
                            toastr.success(response.msg);
                            deferred_table.ajax.reload();
                            location.reload();
                            
                            if (response.errors && response.errors.length > 0) {
                                console.log('Skipped subscriptions:', response.errors);
                            }
                        } else {
                            toastr.error(response.msg);
                        }
                    },
                    error: function (xhr) {
                        var msg = xhr.responseJSON ? xhr.responseJSON.msg : 'Something went wrong';
                        toastr.error(msg);
                    },
                    complete: function () {
                        btn.prop('disabled', false).html('<i class="fa fa-magic"></i> Generate Missing Schedules');
                    }
                });
            });

            // Diagnostic
            $('#diagnostic_btn').on('click', function () {
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');

                $.ajax({
                    url: '{{ route("gym.deferred-revenue.diagnostic") }}',
                    type: 'GET',
                    success: function (response) {
                        if (response.success) {
                            var html = '<div class="modal fade" id="diagnosticModal" tabindex="-1">';
                            html += '<div class="modal-dialog modal-lg"><div class="modal-content">';
                            html += '<div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button>';
                            html += '<h4 class="modal-title"><i class="fa fa-stethoscope"></i> Deferred Revenue Diagnostic</h4></div>';
                            html += '<div class="modal-body">';
                            
                            // Summary
                            html += '<div class="alert alert-info">';
                            html += '<strong>Summary:</strong><br>';
                            html += 'Total Subscriptions: ' + response.summary.total_subscriptions + '<br>';
                            html += 'With Schedule: ' + response.summary.with_schedule + '<br>';
                            html += 'Without Schedule: <strong class="text-danger">' + response.summary.without_schedule + '</strong>';
                            html += '</div>';
                            
                            // Packages
                            html += '<h4>Package Settings</h4>';
                            html += '<table class="table table-bordered table-condensed">';
                            html += '<thead><tr><th>Package</th><th>Deferred Enabled</th><th>Deposit Account</th><th>Revenue Account</th></tr></thead>';
                            html += '<tbody>';
                            response.packages.forEach(function(pkg) {
                                var enabled = pkg.enable_deferred_revenue ? '<span class="label label-success">Yes</span>' : '<span class="label label-danger">No</span>';
                                var deposit = pkg.deposit_account ? pkg.deposit_account.gl_code + ' - ' + pkg.deposit_account.name : '<span class="text-danger">Not Set</span>';
                                var revenue = pkg.revenue_account ? pkg.revenue_account.gl_code + ' - ' + pkg.revenue_account.name : '<span class="text-danger">Not Set</span>';
                                html += '<tr><td>' + pkg.name + '</td><td>' + enabled + '</td><td>' + deposit + '</td><td>' + revenue + '</td></tr>';
                            });
                            html += '</tbody></table>';
                            
                            // Subscriptions without schedule
                            if (response.subscriptions_without_schedule.length > 0) {
                                html += '<h4 class="text-warning">Subscriptions Without Schedule (' + response.subscriptions_without_schedule.length + ')</h4>';
                                html += '<table class="table table-bordered table-condensed">';
                                html += '<thead><tr><th>ID</th><th>Member</th><th>Start</th><th>End</th><th>Amount</th><th>Status</th></tr></thead>';
                                html += '<tbody>';
                                response.subscriptions_without_schedule.forEach(function(sub) {
                                    var memberName = sub.contact ? sub.contact.name : '-';
                                    html += '<tr>';
                                    html += '<td>' + sub.id + '</td>';
                                    html += '<td>' + memberName + '</td>';
                                    html += '<td>' + (sub.gym_package_start_date || '-') + '</td>';
                                    html += '<td>' + (sub.gym_package_end_date || 'Lifetime') + '</td>';
                                    html += '<td>' + parseFloat(sub.final_total).toLocaleString() + '</td>';
                                    html += '<td>' + sub.payment_status + '</td>';
                                    html += '</tr>';
                                });
                                html += '</tbody></table>';
                            }
                            
                            html += '</div>';
                            html += '<div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Close</button></div>';
                            html += '</div></div></div>';
                            
                            // Remove existing modal if any
                            $('#diagnosticModal').remove();
                            $('body').append(html);
                            $('#diagnosticModal').modal('show');
                        } else {
                            toastr.error(response.msg);
                        }
                    },
                    error: function () {
                        toastr.error('Something went wrong');
                    },
                    complete: function () {
                        btn.prop('disabled', false).html('<i class="fa fa-stethoscope"></i> Diagnostic');
                    }
                });
            });
        });
    </script>
@endsection
