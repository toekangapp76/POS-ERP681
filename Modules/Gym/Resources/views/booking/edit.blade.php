<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <h4 class="modal-title">@lang('gym::lang.edit_booking')</h4>
</div>

{!! Form::open(['url' => action([\Modules\Gym\Http\Controllers\BookingController::class, 'update'], $booking->id), 'method' => 'put', 'id' => 'booking_form']) !!}
<input type="hidden" name="booking_id" id="booking_id" value="{{ $booking->id }}">

<div class="modal-body">
    <div class="row">
        <!-- Member Selection (Optional - for walk-in customers) -->
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('contact_id', __('gym::lang.member') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-user"></i></span>
                    {!! Form::select('contact_id', $members, $booking->contact_id, [
                        'class' => 'form-control select2',
                        'id' => 'contact_id',
                        'placeholder' => __('gym::lang.select_member')
                    ]) !!}
                </div>
                <small class="text-muted">@lang('gym::lang.member_optional_walkin')</small>
            </div>
        </div>

        <!-- Subscription Selection -->
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('subscription_id', __('gym::lang.subscription') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-id-card"></i></span>
                    <select name="subscription_id" id="subscription_id" class="form-control">
                        <option value="">@lang('gym::lang.no_subscription')</option>
                        @if($booking->subscription)
                            <option value="{{ $booking->subscription_id }}" selected>
                                {{ $booking->subscription->gym_package->name ?? 'Subscription #' . $booking->subscription_id }}
                            </option>
                        @endif
                    </select>
                </div>
                <small class="text-muted" id="subscription_info"></small>
            </div>
        </div>
    </div>

    <!-- Walk-in Customer Info (shown when no member selected) -->
    <div class="row" id="walkin_container" style="{{ $booking->contact_id ? 'display: none;' : '' }}">
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('walkin_name', __('gym::lang.customer_name') . ':') !!}
                {!! Form::text('walkin_name', $booking->walkin_name, ['class' => 'form-control', 'placeholder' => __('gym::lang.enter_name')]) !!}
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('walkin_phone', __('gym::lang.phone') . ':') !!}
                {!! Form::text('walkin_phone', $booking->walkin_phone, ['class' => 'form-control', 'placeholder' => __('gym::lang.enter_phone')]) !!}
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Class Selection -->
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('gym_class_id', __('gym::lang.class') . ':*') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-futbol"></i></span>
                    <select name="gym_class_id" id="gym_class_id" class="form-control select2" required>
                        <option value="">@lang('gym::lang.select_class')</option>
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" 
                                data-type="{{ $class->class_type }}"
                                data-has-courts="{{ $class->has_courts ? 1 : 0 }}"
                                {{ $booking->gym_class_id == $class->id ? 'selected' : '' }}>
                                {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Court Selection -->
        <div class="col-sm-6" id="court_container" @if(!$booking->gymClass || !$booking->gymClass->has_courts) style="display: none;" @endif>
            <div class="form-group">
                {!! Form::label('court_id', __('gym::lang.court') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-th-large"></i></span>
                    <select name="court_id" id="court_id" class="form-control">
                        <option value="">@lang('gym::lang.auto_assign')</option>
                        @foreach($courts as $court_id => $court_name)
                            <option value="{{ $court_id }}" {{ $booking->court_id == $court_id ? 'selected' : '' }}>
                                {{ $court_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Booking Date -->
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('booking_date', __('gym::lang.booking_date') . ':*') !!}
                <div class="input-group date">
                    <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                    {!! Form::text('booking_date', $booking->booking_start->format('Y-m-d'), [
                        'class' => 'form-control',
                        'id' => 'booking_date',
                        'required',
                        'readonly'
                    ]) !!}
                </div>
            </div>
        </div>

        <!-- Booking Time -->
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('booking_time', __('gym::lang.booking_time') . ':*') !!}
                <div class="input-group date">
                    <span class="input-group-addon"><i class="fa fa-clock"></i></span>
                    {!! Form::text('booking_time', $booking->booking_start->format('H:i'), [
                        'class' => 'form-control',
                        'id' => 'booking_time',
                        'required',
                        'readonly'
                    ]) !!}
                </div>
            </div>
        </div>

        <!-- Duration -->
        <div class="col-sm-4">
            <div class="form-group">
                {!! Form::label('duration_minutes', __('gym::lang.duration') . ':*') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-hourglass-half"></i></span>
                    <select name="duration_minutes" id="duration_minutes" class="form-control" required>
                        <option value="60" {{ $booking->duration_minutes == 60 ? 'selected' : '' }}>1 @lang('gym::lang.hour')</option>
                        <option value="120" {{ $booking->duration_minutes == 120 ? 'selected' : '' }}>2 @lang('gym::lang.hours')</option>
                        <option value="180" {{ $booking->duration_minutes == 180 ? 'selected' : '' }}>3 @lang('gym::lang.hours')</option>
                        <option value="240" {{ $booking->duration_minutes == 240 ? 'selected' : '' }}>4 @lang('gym::lang.hours')</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Info -->
    @if($booking->reschedule_count > 0)
    <div class="row">
        <div class="col-sm-12">
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i>
                @lang('gym::lang.reschedule_count'): {{ $booking->reschedule_count }} / {{ $booking->max_reschedule }}
                @if(!$booking->canReschedule())
                    <br><span class="text-danger">@lang('gym::lang.reschedule_limit_reached')</span>
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Availability Status -->
    <div class="row">
        <div class="col-sm-12">
            <div id="availability_status" class="text-center" style="padding: 10px; background: #f5f5f5; border-radius: 5px; margin-bottom: 15px;">
                <span class="text-success"><i class="fa fa-check"></i> @lang('gym::lang.current_booking')</span>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Agent / Coach / PIC -->
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('agent_id', __('gym::lang.agent_coach') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-user-circle"></i></span>
                    {!! Form::select('agent_id', $agents, $booking->agent_id, [
                        'class' => 'form-control select2',
                        'id' => 'agent_id',
                        'placeholder' => __('gym::lang.select_agent')
                    ]) !!}
                </div>
            </div>
        </div>

        <!-- Booking Status -->
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('booking_status', __('gym::lang.status') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-flag"></i></span>
                    {!! Form::select('booking_status', $booking_statuses, $booking->booking_status, [
                        'class' => 'form-control',
                        'id' => 'booking_status'
                    ]) !!}
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Notes -->
        <div class="col-sm-12">
            <div class="form-group">
                {!! Form::label('booking_note', __('gym::lang.booking_note') . ':') !!}
                {!! Form::textarea('booking_note', $booking->booking_note, [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => __('gym::lang.booking_note_placeholder')
                ]) !!}
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="tw-dw-btn tw-dw-btn-error tw-text-white delete-booking" data-href="{{ action([\Modules\Gym\Http\Controllers\BookingController::class, 'destroy'], $booking->id) }}">
        <i class="fa fa-trash"></i> @lang('messages.delete')
    </button>
    <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
        <i class="fa fa-save"></i> @lang('messages.update')
    </button>
    <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">
        @lang('messages.close')
    </button>
</div>

{!! Form::close() !!}
