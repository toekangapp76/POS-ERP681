@extends('layouts.app')
@section('title', __('gym::lang.attendance'))
@section('content')
    @include('gym::layouts.nav')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('gym::lang.attendance')</h1>
    </section>

    <!-- Main content -->
    <section class="content">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('filter_date', __('gym::lang.date') . ':') !!}
                    {!! Form::text('filter_date', date('Y-m-d'), [
                        'class' => 'form-control',
                        'id' => 'filter_date',
                        'placeholder' => __('gym::lang.date'),
                        'readonly'
                    ]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <br>
                    <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-text-white" id="filter_btn">
                        <i class="fa fa-filter"></i> @lang('report.apply_filters')
                    </button>
                    <button type="button" class="tw-dw-btn tw-dw-btn-outline" id="reset_filter_btn">
                        {{-- <i class="fa fa-refresh"></i>  --}}
                        @lang('gym::lang.clear')
                    </button>
                </div>
            </div>
        @endcomponent

        @component('components.widget')
            <div class="box-tools tw-flex tw-justify-end tw-gap-2.5 tw-mb-4">
                <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full"
                    href="{{ route('gym.public_scanner') }}" target="_blank">
                    <i class="fa fa-qrcode"></i> @lang('gym::lang.public_scanner')
                </a>
            </div>
            <table class="table table-bordered table-striped" id="attendance_table">
                <thead>
                    <tr>
                        <th>@lang('contact.name')</th>
                        <th>@lang('business.email')</th>
                        <th>@lang('contact.mobile')</th>
                        <th>@lang('gym::lang.in_time')</th>
                        <th>@lang('gym::lang.out_time')</th>
                        <th>@lang('gym::lang.duration')</th>
                        <th>@lang('messages.action')</th>
                    </tr>
                </thead>
            </table>
        @endcomponent

    </section>
    <div class="modal fade view_modal_in_out" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
    </div>
    <!-- /.content -->
@endsection

@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            // Initialize date picker with proper format
            $('#filter_date').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true
            }).datepicker('setDate', new Date());

            // Initialize DataTable
            attendance_table = $('#attendance_table').DataTable({
                processing: true,
                serverSide: true,
                fixedHeader: false,
                ajax: {
                    url: "{{ action([\Modules\Gym\Http\Controllers\AttendanceController::class, 'index']) }}",
                    data: function(d) {
                        d.filter_date = $('#filter_date').val();
                    }
                },
                aaSorting: [[0, 'asc']],
                columns: [
                    { data: 'name', name: 'contacts.name' },
                    { data: 'email', name: 'contacts.email' },
                    { data: 'mobile', name: 'contacts.mobile' },
                    { data: 'in', name: 'in', orderable: false, searchable: false },
                    { data: 'out', name: 'out', orderable: false, searchable: false },
                    { data: 'duration', name: 'duration', orderable: false, searchable: false },
                    { data: 'action', name: 'action', orderable: false, searchable: false }
                ],
            });

            // Filter button
            $('#filter_btn').on('click', function() {
                attendance_table.ajax.reload();
            });

            // Reset filter
            $('#reset_filter_btn').on('click', function() {
                $('#filter_date').datepicker('setDate', new Date());
                attendance_table.ajax.reload();
            });

            // Delete attendance
            $(document).on('click', '.delete-attendance', function(e) {
                e.preventDefault();
                var id = $(this).data('id');
                
                swal({
                    title: LANG.sure,
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then((confirmed) => {
                    if (confirmed) {
                        $.ajax({
                            url: "{{ route('gym.delete_attendance') }}",
                            method: 'POST',
                            data: {
                                id: id,
                                _token: '{{ csrf_token() }}'
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    toastr.success(response.msg);
                                    attendance_table.ajax.reload();
                                } else {
                                    toastr.error(response.message);
                                }
                            },
                            error: function() {
                                toastr.error('Something went wrong');
                            }
                        });
                    }
                });
            });

            // Modal handlers (keeping for backwards compatibility if needed)
            $(document).on('click', '.btn-modal-in', function(e) {
                e.preventDefault();
                $.ajax({
                    url: $(this).attr('href'),
                    dataType: 'html',
                    success: function(result) {
                        $('.view_modal_in_out')
                            .html(result)
                            .modal('show');
                    },
                });
            });

            $('.view_modal_in_out').on('shown.bs.modal', function() {

                $('#add_edit_in_time').on('submit', function(event) {
                    event.preventDefault();
                    var formData = $(this).serialize();

                    $.ajax({
                        url: $(this).attr('action'),
                        method: $(this).attr('method'),
                        data: formData,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                $('.view_modal_in_out').modal('hide');
                                attendance_table.ajax.reload();
                                toastr.success(response.msg);
                            } else {
                                alert(response.message || 'An error occurred.');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log(xhr.responseText);
                            alert('Something went wrong. Please try again.');
                        }
                    });
                });

                $('#add_edit_out_time').on('submit', function(event) {
                    event.preventDefault();
                    var formData = $(this).serialize();

                    $.ajax({
                        url: $(this).attr('action'),
                        method: $(this).attr('method'),
                        data: formData,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                $('.view_modal_in_out').modal('hide');
                                attendance_table.ajax.reload();
                                toastr.success(response.msg);
                                
                                if (response.duration_minutes) {
                                    var hours = Math.floor(response.duration_minutes / 60);
                                    var mins = response.duration_minutes % 60;
                                    var durationText = hours > 0 ? hours + 'h ' + mins + 'm' : mins + 'm';
                                    toastr.info('Duration: ' + durationText);
                                }
                            } else {
                                alert(response.message || 'An error occurred.');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log(xhr.responseText);
                            alert('Something went wrong. Please try again.');
                        }
                    });
                });

                $('.time_picker').datetimepicker({
                    format: moment_time_format,
                    ignoreReadonly: true,
                    defaultDate: moment(),
                });
            });

            $(document).on('click', '.btn-modal-out', function(e) {
                e.preventDefault();
                $.ajax({
                    url: $(this).attr('href'),
                    dataType: 'html',
                    success: function(result) {
                        $('.view_modal_in_out')
                            .html(result)
                            .modal('show');
                    },
                });
            });
        });
    </script>
@endsection
