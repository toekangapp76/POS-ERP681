<div class="modal-header">
    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
    <h4 class="modal-title">@lang('gym::lang.add_booking')</h4>
</div>

{!! Form::open(['url' => action([\Modules\Gym\Http\Controllers\BookingController::class, 'store']), 'method' => 'post', 'id' => 'booking_form']) !!}

<div class="modal-body">
    <div class="row">
        <!-- Member Selection (Optional - for walk-in customers) -->
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('contact_id', __('gym::lang.member') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-user"></i></span>
                    {!! Form::select('contact_id', $members, null, [
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
                        <option value="">@lang('gym::lang.select_member_first')</option>
                    </select>
                </div>
                <small class="text-muted" id="subscription_info"></small>
            </div>
        </div>
    </div>
    
    <!-- Walk-in Customer Info (shown when no member selected) -->
    <div class="row" id="walkin_container" style="display: none;">
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('walkin_name', __('gym::lang.customer_name') . ':') !!}
                {!! Form::text('walkin_name', null, ['class' => 'form-control', 'placeholder' => __('gym::lang.enter_name')]) !!}
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('walkin_phone', __('gym::lang.phone') . ':') !!}
                {!! Form::text('walkin_phone', null, ['class' => 'form-control', 'placeholder' => __('gym::lang.enter_phone')]) !!}
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
                                data-default-duration="{{ $class->default_duration }}">
                                {{ $class->name }}
                                @if($class->class_type == 'court')
                                    ({{ __('gym::lang.court') }})
                                @elseif($class->class_type == 'class')
                                    ({{ __('gym::lang.class_type') }})
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <!-- Court Selection (shown for classes with courts) -->
        <div class="col-sm-6" id="court_container" style="display: none;">
            <div class="form-group">
                {!! Form::label('court_id', __('gym::lang.court') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-th-large"></i></span>
                    <select name="court_id" id="court_id" class="form-control">
                        <option value="">@lang('gym::lang.auto_assign')</option>
                    </select>
                </div>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="auto_assign_court" value="1" checked>
                        @lang('gym::lang.auto_assign_court')
                    </label>
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
                    {!! Form::text('booking_date', $start_date ?? date('Y-m-d'), [
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
                    {!! Form::text('booking_time', $start_time ?? '09:00', [
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
                        <option value="60">1 @lang('gym::lang.hour')</option>
                        <option value="120">2 @lang('gym::lang.hours')</option>
                        <option value="180">3 @lang('gym::lang.hours')</option>
                        <option value="240">4 @lang('gym::lang.hours')</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Availability Status -->
    <div class="row">
        <div class="col-sm-12">
            <div id="availability_status" class="text-center" style="padding: 10px; background: #f5f5f5; border-radius: 5px; margin-bottom: 15px;">
                <span class="text-muted"><i class="fa fa-info-circle"></i> @lang('gym::lang.select_class_and_time')</span>
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
                    {!! Form::select('agent_id', $agents, null, [
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
                    {!! Form::select('booking_status', $booking_statuses, 'confirmed', [
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
                {!! Form::textarea('booking_note', null, [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => __('gym::lang.booking_note_placeholder')
                ]) !!}
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
        <i class="fa fa-save"></i> @lang('messages.save')
    </button>
    <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">
        @lang('messages.close')
    </button>
</div>

{!! Form::close() !!}
