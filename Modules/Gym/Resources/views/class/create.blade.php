<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">

        {!! Form::open([
            'url' => action([\Modules\Gym\Http\Controllers\ClassController::class, 'store']),
            'method' => 'post',
            'id' => 'add_class',
        ]) !!}

        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                    aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">@lang('gym::lang.classes')</h4>
        </div>

        <div class="modal-body">
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('name', __('gym::lang.name') . '*') !!}
                        {!! Form::text('name', null, [
                            'class' => 'form-control',
                            'required',
                            'placeholder' => __('gym::lang.name'),
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('class_type', __('gym::lang.class_type_label') . '*') !!}
                        {!! Form::select('class_type', [
                            'gym' => __('gym::lang.gym'),
                            'court' => __('gym::lang.court'),
                            'class' => __('gym::lang.class_type'),
                        ], 'gym', ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('default_duration', __('gym::lang.default_duration')) !!}
                        {!! Form::select('default_duration', [
                            30 => '30 ' . __('gym::lang.minutes'),
                            60 => '1 ' . __('gym::lang.hour'),
                            90 => '1.5 ' . __('gym::lang.hours'),
                            120 => '2 ' . __('gym::lang.hours'),
                        ], 60, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('capacity', __('gym::lang.capacity')) !!}
                        {!! Form::number('capacity', null, [
                            'class' => 'form-control',
                            'min' => 1,
                            'placeholder' => __('gym::lang.capacity'),
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('price_per_hour', __('gym::lang.price_per_hour')) !!}
                        {!! Form::text('price_per_hour', 0, [
                            'class' => 'form-control input_number',
                        ]) !!}
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('start_time', __('gym::lang.start_time')) !!}
                        {!! Form::text('start_time', null, [
                            'class' => 'form-control time_picker',
                            'readonly',
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('end_time', __('gym::lang.end_time')) !!}
                        {!! Form::text('end_time', null, [
                            'class' => 'form-control time_picker',
                            'readonly',
                        ]) !!}
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('min_booking_hours', __('gym::lang.min_booking_hours')) !!}
                        {!! Form::number('min_booking_hours', 1, [
                            'class' => 'form-control',
                            'min' => 1,
                            'max' => 24,
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('max_booking_hours', __('gym::lang.max_booking_hours')) !!}
                        {!! Form::number('max_booking_hours', 4, [
                            'class' => 'form-control',
                            'min' => 1,
                            'max' => 24,
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('advance_booking_days', __('gym::lang.advance_booking_days')) !!}
                        {!! Form::number('advance_booking_days', 7, [
                            'class' => 'form-control',
                            'min' => 1,
                        ]) !!}
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('max_reschedule', __('gym::lang.max_reschedule')) !!}
                        {!! Form::number('max_reschedule', 2, [
                            'class' => 'form-control',
                            'min' => 0,
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('cancellation_hours', __('gym::lang.cancellation_hours')) !!}
                        {!! Form::number('cancellation_hours', 24, [
                            'class' => 'form-control',
                            'min' => 0,
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('color', __('gym::lang.color')) !!}
                        {!! Form::color('color', '#667eea', [
                            'class' => 'form-control',
                        ]) !!}
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('description', __('gym::lang.description')) !!}
                        {!! Form::textarea('description', null, [
                            'class' => 'form-control',
                            'placeholder' => __('gym::lang.description'),
                            'rows' => 2,
                        ]) !!}
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-6">
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('has_courts', 1, false) !!}
                            @lang('gym::lang.has_courts')
                        </label>
                        <p class="help-block">@lang('gym::lang.has_courts_help')</p>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('is_active', 1, true) !!}
                            @lang('gym::lang.is_active')
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.save')</button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white"
                data-dismiss="modal">@lang('messages.close')</button>
        </div>

        {!! Form::close() !!}

    </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->
