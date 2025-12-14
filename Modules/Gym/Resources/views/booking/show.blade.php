<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <h4 class="modal-title">@lang('gym::lang.booking_details')</h4>
</div>

<div class="modal-body">
    <!-- Status Badge -->
    <div class="text-center" style="margin-bottom: 20px;">
        <span class="badge {{ $booking->getStatusBadgeClass() }}" style="font-size: 14px; padding: 8px 16px;">
            {{ $booking_statuses[$booking->booking_status] ?? $booking->booking_status }}
        </span>
    </div>

    <!-- Member Info -->
    <div class="row">
        <div class="col-sm-6">
            <div class="info-box {{ $booking->member ? 'bg-aqua' : 'bg-orange' }}">
                <span class="info-box-icon"><i class="fa {{ $booking->member ? 'fa-user' : 'fa-walking' }}"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">
                        @if($booking->member)
                            @lang('gym::lang.member')
                        @else
                            @lang('gym::lang.walk_in')
                        @endif
                    </span>
                    <span class="info-box-number">
                        @if($booking->member)
                            {{ $booking->member->name }}
                        @else
                            {{ $booking->walkin_name ?? __('gym::lang.walk_in') }}
                        @endif
                    </span>
                    @if(!$booking->member && $booking->walkin_phone)
                        <small>{{ $booking->walkin_phone }}</small>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="info-box bg-green">
                <span class="info-box-icon"><i class="fa fa-futbol"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">@lang('gym::lang.class')</span>
                    <span class="info-box-number">{{ $booking->gymClass->name ?? '--' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Details -->
    <table class="table table-bordered table-striped">
        <tbody>
            <tr>
                <th width="40%"><i class="fa fa-calendar"></i> @lang('gym::lang.booking_date')</th>
                <td>{{ \Carbon\Carbon::parse($booking->booking_start)->format('d M Y') }}</td>
            </tr>
            <tr>
                <th><i class="fa fa-clock"></i> @lang('gym::lang.time')</th>
                <td>
                    {{ \Carbon\Carbon::parse($booking->booking_start)->format('H:i') }} - 
                    {{ \Carbon\Carbon::parse($booking->booking_end)->format('H:i') }}
                </td>
            </tr>
            <tr>
                <th><i class="fa fa-hourglass-half"></i> @lang('gym::lang.duration')</th>
                <td>{{ $booking->formatted_duration }}</td>
            </tr>
            @if($booking->court)
            <tr>
                <th><i class="fa fa-th-large"></i> @lang('gym::lang.court')</th>
                <td>{{ $booking->court->name }}</td>
            </tr>
            @endif
            @if($booking->agent)
            <tr>
                <th><i class="fa fa-user-circle"></i> @lang('gym::lang.agent_coach')</th>
                <td>{{ $booking->agent->user_full_name }}</td>
            </tr>
            @endif
            @if($booking->member && $booking->subscription)
            <tr>
                <th><i class="fa fa-id-card"></i> @lang('gym::lang.subscription')</th>
                <td>{{ $booking->subscription->gym_package->name ?? 'Subscription #' . $booking->subscription_id }}</td>
            </tr>
            @endif
            @if($booking->member && $booking->hours_deducted > 0)
            <tr>
                <th><i class="fa fa-minus-circle"></i> @lang('gym::lang.hours_deducted')</th>
                <td>{{ number_format($booking->hours_deducted, 1) }} @lang('gym::lang.hours')</td>
            </tr>
            @endif
            <tr>
                <th><i class="fa fa-refresh"></i> @lang('gym::lang.reschedule_count')</th>
                <td>{{ $booking->reschedule_count }} / {{ $booking->max_reschedule }}</td>
            </tr>
            @if($booking->booking_note)
            <tr>
                <th><i class="fa fa-sticky-note"></i> @lang('gym::lang.notes')</th>
                <td>{{ $booking->booking_note }}</td>
            </tr>
            @endif
            <tr>
                <th><i class="fa fa-user-plus"></i> @lang('gym::lang.created_by')</th>
                <td>
                    {{ $booking->createdBy->user_full_name ?? '--' }}
                    <br>
                    <small class="text-muted">{{ $booking->created_at->format('d M Y H:i') }}</small>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Quick Status Update -->
    <div class="row" style="margin-top: 15px;">
        <div class="col-sm-12">
            <label>@lang('gym::lang.quick_status_update'):</label>
            <div class="btn-group btn-group-justified">
                @foreach($booking_statuses as $status_key => $status_label)
                    <a href="javascript:void(0)" 
                       class="btn btn-sm {{ $booking->booking_status == $status_key ? 'btn-primary' : 'btn-default' }} update-status-btn"
                       data-status="{{ $status_key }}"
                       data-booking-id="{{ $booking->id }}">
                        {{ $status_label }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="tw-dw-btn tw-dw-btn-error tw-text-white delete-booking" 
            data-href="{{ action([\Modules\Gym\Http\Controllers\BookingController::class, 'destroy'], $booking->id) }}">
        <i class="fa fa-trash"></i> @lang('messages.delete')
    </button>
    <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-text-white edit-booking" 
            data-href="{{ action([\Modules\Gym\Http\Controllers\BookingController::class, 'edit'], $booking->id) }}">
        <i class="fa fa-edit"></i> @lang('messages.edit')
    </button>
    <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">
        @lang('messages.close')
    </button>
</div>

<script>
$(document).ready(function() {
    $('.update-status-btn').click(function() {
        var status = $(this).data('status');
        var bookingId = $(this).data('booking-id');
        var btn = $(this);
        
        $.ajax({
            url: "{{ action([\Modules\Gym\Http\Controllers\BookingController::class, 'update'], '') }}/" + bookingId,
            method: 'PUT',
            data: {
                booking_status: status,
                _token: "{{ csrf_token() }}"
            },
            success: function(result) {
                if (result.success) {
                    toastr.success(result.msg);
                    $('.update-status-btn').removeClass('btn-primary').addClass('btn-default');
                    btn.removeClass('btn-default').addClass('btn-primary');
                    $('#calendar').fullCalendar('refetchEvents');
                    $('#todays_bookings_table').DataTable().ajax.reload();
                } else {
                    toastr.error(result.msg);
                }
            }
        });
    });
});
</script>
