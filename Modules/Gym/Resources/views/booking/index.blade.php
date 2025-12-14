@extends('layouts.app')
@section('title', __('gym::lang.gym_bookings'))

@section('content')
@include('gym::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('gym::lang.gym_bookings')</h1>
</section>

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-sm-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('gym::lang.todays_bookings')</h3>
                </div>
                <div class="box-body">
                    <div class="row" style="margin-bottom: 10px;">
                        <div class="col-sm-4">
                            <select id="filter_class" class="form-control select2">
                                <option value="">@lang('gym::lang.all_classes')</option>
                                @foreach($classes as $class_id => $class_name)
                                    <option value="{{ $class_id }}">{{ $class_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <table class="table table-bordered table-condensed" id="todays_bookings_table">
                        <thead>
                            <tr>
                                <th>@lang('gym::lang.member')</th>
                                <th>@lang('gym::lang.class')</th>
                                <th>@lang('gym::lang.court')</th>
                                <th>@lang('gym::lang.start_time')</th>
                                <th>@lang('gym::lang.end_time')</th>
                                <th>@lang('gym::lang.duration')</th>
                                <th>@lang('gym::lang.agent')</th>
                                <th>@lang('gym::lang.status')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-10">
            <div class="box">
                <div class="box-body">
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="col-sm-3">
                            <select id="calendar_class_filter" class="form-control select2">
                                <option value="">@lang('gym::lang.all_classes')</option>
                                @foreach($classes as $class_id => $class_name)
                                    <option value="{{ $class_id }}">{{ $class_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <select id="calendar_member_filter" class="form-control select2">
                                <option value="">@lang('gym::lang.all_members')</option>
                                @foreach($members as $member_id => $member_name)
                                    <option value="{{ $member_id }}">{{ $member_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 text-right">
                            <button type="button" class="tw-dw-btn tw-dw-btn-primary" id="add_booking_btn">
                                <i class="fa fa-plus"></i> @lang('gym::lang.add_booking')
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <div id="calendar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-2">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('gym::lang.booking_status')</h3>
                </div>
                <div class="box-body">
                    <div class="external-event text-center" style="background-color: #ffc107; padding: 5px; margin-bottom: 5px; border-radius: 3px;">
                        <small style="color: #000;">@lang('gym::lang.pending')</small>
                    </div>
                    <div class="external-event text-center" style="background-color: #28a745; padding: 5px; margin-bottom: 5px; border-radius: 3px;">
                        <small style="color: #fff;">@lang('gym::lang.confirmed')</small>
                    </div>
                    <div class="external-event text-center" style="background-color: #17a2b8; padding: 5px; margin-bottom: 5px; border-radius: 3px;">
                        <small style="color: #fff;">@lang('gym::lang.completed')</small>
                    </div>
                    <div class="external-event text-center" style="background-color: #dc3545; padding: 5px; margin-bottom: 5px; border-radius: 3px;">
                        <small style="color: #fff;">@lang('restaurant.cancelled')</small>
                    </div>
                    <div class="external-event text-center" style="background-color: #6c757d; padding: 5px; margin-bottom: 5px; border-radius: 3px;">
                        <small style="color: #fff;">@lang('gym::lang.no_show')</small>
                    </div>
                    <hr>
                    <small class="text-muted">
                        <i class="fa fa-info-circle"></i> @lang('gym::lang.calendar_help')
                    </small>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Booking Modal -->
<div class="modal fade" id="booking_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" id="booking_modal_content">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade view_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

@endsection

@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    // Initialize DataTable for today's bookings
    var todays_bookings_table = $('#todays_bookings_table').DataTable({
        processing: true,
        serverSide: true,
        ordering: false,
        searching: false,
        pageLength: 10,
        ajax: {
            url: "{{ action([\Modules\Gym\Http\Controllers\BookingController::class, 'getTodaysBookings']) }}",
            data: function(d) {
                d.class_id = $('#filter_class').val();
            }
        },
        columns: [
            {data: 'member', name: 'member'},
            {data: 'class', name: 'class'},
            {data: 'court', name: 'court'},
            {data: 'booking_start', name: 'booking_start'},
            {data: 'booking_end', name: 'booking_end'},
            {data: 'duration', name: 'duration'},
            {data: 'agent', name: 'agent'},
            {data: 'status', name: 'status'},
            {data: 'action', name: 'action', orderable: false, searchable: false}
        ]
    });

    $('#filter_class').change(function() {
        todays_bookings_table.ajax.reload();
    });

    // Initialize FullCalendar
    $('#calendar').fullCalendar({
        header: {
            left: 'prev,next today',
            center: 'title',
            right: 'month,agendaWeek,agendaDay,listWeek'
        },
        defaultView: 'agendaWeek',
        eventLimit: 3,
        events: function(start, end, timezone, callback) {
            $.ajax({
                url: "{{ action([\Modules\Gym\Http\Controllers\BookingController::class, 'index']) }}",
                data: {
                    start: start.format(),
                    end: end.format(),
                    class_id: $('#calendar_class_filter').val(),
                    member_id: $('#calendar_member_filter').val()
                },
                success: function(data) {
                    callback(data);
                }
            });
        },
        eventRender: function(event, element) {
            element.attr('title', event.title);
        },
        eventClick: function(event) {
            loadBookingModal(event.url);
            return false;
        },
        dayClick: function(date, jsEvent, view) {
            var dateStr = date.format('YYYY-MM-DD');
            var timeStr = date.format('HH:mm');
            openCreateBookingModal(dateStr, timeStr);
        },
        slotDuration: '00:30:00',
        minTime: '06:00:00',
        maxTime: '23:00:00',
        allDaySlot: false
    });

    // Filter changes
    $('#calendar_class_filter, #calendar_member_filter').change(function() {
        $('#calendar').fullCalendar('refetchEvents');
    });

    // Add booking button
    $('#add_booking_btn').click(function() {
        openCreateBookingModal();
    });

    // View booking
    $(document).on('click', '.view-booking', function() {
        loadBookingModal($(this).data('href'));
    });

    // Edit booking
    $(document).on('click', '.edit-booking', function() {
        loadBookingModal($(this).data('href'));
    });

    // Delete booking
    $(document).on('click', '.delete-booking', function() {
        var href = $(this).data('href');
        swal({
            title: LANG.sure,
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                    method: "DELETE",
                    url: href,
                    dataType: "json",
                    success: function(result) {
                        if (result.success) {
                            toastr.success(result.msg);
                            $('#calendar').fullCalendar('refetchEvents');
                            todays_bookings_table.ajax.reload();
                            $('.view_modal').modal('hide');
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            }
        });
    });
});

function openCreateBookingModal(date, time) {
    var url = "{{ action([\Modules\Gym\Http\Controllers\BookingController::class, 'create']) }}";
    if (date) {
        url += '?start=' + date;
        if (time) {
            url += '&start_time=' + time;
        }
    }
    loadBookingModal(url);
}

function loadBookingModal(url) {
    $('#booking_modal_content').html('<div class="text-center p-4"><i class="fa fa-spinner fa-spin fa-3x"></i></div>');
    $('#booking_modal').modal('show');
    
    $.get(url, function(html) {
        $('#booking_modal_content').html(html);
        initBookingForm();
    });
}

function initBookingForm() {
    // Initialize select2
    $('#booking_modal .select2').select2({
        dropdownParent: $('#booking_modal')
    });

    // Initialize datetimepickers
    $('#booking_date').datetimepicker({
        format: moment_date_format,
        minDate: moment().startOf('day'),
        ignoreReadonly: true
    });

    $('#booking_time').datetimepicker({
        format: moment_time_format,
        ignoreReadonly: true
    });

    // Show walk-in container if no member selected on load
    if (!$('#contact_id').val()) {
        $('#walkin_container').show();
    }

    // Form validation and submission
    $('#booking_form').validate({
        rules: {
            walkin_name: {
                required: function() {
                    return $('#contact_id').val() == '';
                }
            }
        },
        messages: {
            walkin_name: "{{ __('gym::lang.customer_name_required') }}"
        },
        submitHandler: function(form) {
            var data = $(form).serialize();
            var url = $(form).attr('action');
            var method = $(form).find('input[name="_method"]').val() || 'POST';

            $.ajax({
                method: method,
                url: url,
                dataType: "json",
                data: data,
                beforeSend: function() {
                    $(form).find('button[type="submit"]').prop('disabled', true);
                },
                success: function(result) {
                    if (result.success) {
                        $('#booking_modal').modal('hide');
                        toastr.success(result.msg);
                        $('#calendar').fullCalendar('refetchEvents');
                        $('#todays_bookings_table').DataTable().ajax.reload();
                    } else {
                        toastr.error(result.msg);
                    }
                    $(form).find('button[type="submit"]').prop('disabled', false);
                },
                error: function() {
                    toastr.error("{{ __('messages.something_went_wrong') }}");
                    $(form).find('button[type="submit"]').prop('disabled', false);
                }
            });
        }
    });

    // Class change - load courts
    $('#gym_class_id').change(function() {
        var class_id = $(this).val();
        var courtSelect = $('#court_id');
        
        courtSelect.html('<option value="">{{ __("gym::lang.loading") }}...</option>');
        
        if (class_id) {
            $.get("{{ url('gym/bookings/get-courts') }}/" + class_id, function(courts) {
                courtSelect.html('<option value="">{{ __("gym::lang.auto_assign") }}</option>');
                $.each(courts, function(i, court) {
                    courtSelect.append('<option value="' + court.id + '">' + court.name + '</option>');
                });
                
                if (courts.length > 0) {
                    $('#court_container').show();
                } else {
                    $('#court_container').hide();
                }
            });
        } else {
            courtSelect.html('<option value="">{{ __("gym::lang.select_class_first") }}</option>');
            $('#court_container').hide();
        }

        // Update duration options
        updateDurationOptions(class_id);
    });

    // Member change - load subscriptions and toggle walk-in container
    $('#contact_id').change(function() {
        var member_id = $(this).val();
        var subSelect = $('#subscription_id');
        var walkinContainer = $('#walkin_container');
        var subscriptionInfo = $('#subscription_info');
        
        // Toggle walk-in container based on member selection
        if (member_id) {
            walkinContainer.hide();
            $('#walkin_name, #walkin_phone').val(''); // Clear walk-in fields
        } else {
            walkinContainer.show();
        }
        
        subSelect.html('<option value="">{{ __("gym::lang.loading") }}...</option>');
        subscriptionInfo.html('');
        
        if (member_id) {
            $.get("{{ url('gym/bookings/get-subscriptions') }}/" + member_id, function(subs) {
                subSelect.html('<option value="">{{ __("gym::lang.no_subscription") }}</option>');
                $.each(subs, function(i, sub) {
                    var label = sub.name;
                    if (sub.end_date) {
                        label += ' ({{ __("gym::lang.expires") }}: ' + sub.end_date + ')';
                    }
                    if (sub.remaining_hours) {
                        label += ' [' + sub.remaining_hours + 'h]';
                    }
                    if (sub.remaining_sessions) {
                        label += ' [' + sub.remaining_sessions + ' {{ __("gym::lang.sessions") }}]';
                    }
                    subSelect.append('<option value="' + sub.id + '" data-remaining-hours="' + (sub.remaining_hours || '') + '" data-remaining-sessions="' + (sub.remaining_sessions || '') + '">' + label + '</option>');
                });
            });
        } else {
            subSelect.html('<option value="">{{ __("gym::lang.select_member_first") }}</option>');
        }
    });
    
    // Subscription change - show session info
    $('#subscription_id').change(function() {
        var selected = $(this).find('option:selected');
        var info = [];
        var hours = selected.data('remaining-hours');
        var sessions = selected.data('remaining-sessions');
        
        if (hours) {
            info.push('<i class="fa fa-clock"></i> ' + hours + ' {{ __("gym::lang.hours") }}');
        }
        if (sessions) {
            info.push('<i class="fa fa-ticket"></i> ' + sessions + ' {{ __("gym::lang.sessions") }}');
        }
        
        $('#subscription_info').html(info.join(' | '));
    });

    // Check availability on change
    $('#booking_date, #booking_time, #duration_minutes, #gym_class_id, #court_id').change(function() {
        checkAvailability();
    });
}

function updateDurationOptions(class_id) {
    // Default options
    var options = {
        60: '1 {{ __("gym::lang.hour") }}',
        120: '2 {{ __("gym::lang.hours") }}',
        180: '3 {{ __("gym::lang.hours") }}',
        240: '4 {{ __("gym::lang.hours") }}'
    };
    
    var select = $('#duration_minutes');
    var current = select.val();
    
    select.html('');
    $.each(options, function(val, label) {
        select.append('<option value="' + val + '">' + label + '</option>');
    });
    
    if (current) {
        select.val(current);
    }
}

function checkAvailability() {
    var date = $('#booking_date').val();
    var time = $('#booking_time').val();
    var class_id = $('#gym_class_id').val();
    var court_id = $('#court_id').val();
    var duration = $('#duration_minutes').val();
    var booking_id = $('#booking_id').val();

    if (!date || !time || !class_id) {
        $('#availability_status').html('<span class="text-muted"><i class="fa fa-info-circle"></i> {{ __('gym::lang.select_class_and_time') }}</span>');
        return;
    }

    $.ajax({
        method: 'POST',
        url: "{{ action([\Modules\Gym\Http\Controllers\BookingController::class, 'checkAvailability']) }}",
        dataType: 'json',
        data: {
            date: date,
            time: time,
            class_id: class_id,
            court_id: court_id,
            duration: duration,
            exclude_booking_id: booking_id,
            _token: "{{ csrf_token() }}"
        },
        success: function(result) {
            if (result.available) {
                $('#availability_status').html('<span class="text-success"><i class="fa fa-check"></i> {{ __("gym::lang.slot_available") }}</span>');
            } else {
                var msg = '{{ __("gym::lang.slot_not_available") }}';
                if (result.conflict) {
                    msg += ': ' + result.conflict.member_name + ' (' + result.conflict.start + ' - ' + result.conflict.end + ')';
                }
                $('#availability_status').html('<span class="text-danger"><i class="fa fa-times"></i> ' + msg + '</span>');
            }
        }
    });
}
</script>
@endsection
