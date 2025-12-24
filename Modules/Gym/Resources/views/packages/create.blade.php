<div class="modal-dialog" role="document">
    <div class="modal-content">

        {!! Form::open([
            'url' => action([\Modules\Gym\Http\Controllers\PackageController::class, 'store']),
            'method' => 'post',
            'id' => 'add_package',
        ]) !!}

        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                    aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">@lang('gym::lang.packages')</h4>
        </div>

        <div class="modal-body">
            <div class="form-group">
                {!! Form::label('name', __('gym::lang.name') . '*') !!}
                {!! Form::text('name', null, [
                    'class' => 'form-control',
                    'required',
                    'placeholder' => __('gym::lang.name'),
                ]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('amount', __('gym::lang.amount') . '*') !!}
                {!! Form::number('amount', null, [
                    'class' => 'form-control',
                    'required',
                    'step' => '0.01',
                    'placeholder' => __('gym::lang.amount'),
                ]) !!}
            </div>
            <div class="form-group">
                {!! Form::label('duration', __('gym::lang.duration') . '*') !!}
                {!! Form::select('duration', $durations, '', [
                    'class' => 'form-control',
                    'required',
                ]) !!}
            </div>
            
            {{-- Session Time Limit Section (Hours/Minutes) --}}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-clock"></i> @lang('gym::lang.session_time_limit')
                    </h4>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('session_limit_enabled', 1, false, ['class' => 'input-icheck', 'id' => 'session_limit_enabled']) !!}
                                @lang('gym::lang.enable_session_limit')
                            </label>
                            <p class="help-block text-muted">@lang('gym::lang.session_limit_help')</p>
                        </div>
                    </div>
                    <div class="row session-limit-fields" style="display: none;">
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('session_limit_hours', __('gym::lang.hours')) !!}
                                {!! Form::number('session_limit_hours', 0, [
                                    'class' => 'form-control',
                                    'min' => '0',
                                    'placeholder' => __('gym::lang.hours'),
                                ]) !!}
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('session_limit_minutes', __('gym::lang.minutes')) !!}
                                {!! Form::number('session_limit_minutes', 0, [
                                    'class' => 'form-control',
                                    'min' => '0',
                                    'max' => '59',
                                    'placeholder' => __('gym::lang.minutes'),
                                ]) !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Session Count Limit Section (Per Visit) --}}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-ticket"></i> @lang('gym::lang.session_count_limit')
                    </h4>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('session_count_enabled', 1, false, ['class' => 'input-icheck', 'id' => 'session_count_enabled']) !!}
                                @lang('gym::lang.enable_session_count_limit')
                            </label>
                            <p class="help-block text-muted">@lang('gym::lang.session_count_limit_help')</p>
                        </div>
                    </div>
                    <div class="row session-count-fields" style="display: none;">
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('session_count_limit', __('gym::lang.number_of_sessions')) !!}
                                {!! Form::number('session_count_limit', null, [
                                    'class' => 'form-control',
                                    'min' => '1',
                                    'placeholder' => __('gym::lang.enter_number_of_sessions'),
                                ]) !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {!! Form::label('duration', __('gym::lang.classes')) !!}
            <div class="row">
                @foreach ($classes as $key => $class)
                    <div class="col-md-4">
                        <div class="form-group">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="classes[]" value="{{ $class->id }}">
                                    {{ $class->name }}  ({{$class->start_time ? @format_time($class->start_time) : ''}} {{ $class->start_time || $class->end_time ? ' - ' : '' }} {{$class->end_time ? @format_time($class->end_time) : ''}})
                                </label>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="form-group">
                {!! Form::label('notes', __('gym::lang.notes')) !!}
                {!! Form::textarea('notes', null, [
                    'class' => 'form-control',
                    'placeholder' => __('gym::lang.notes'),
                    'rows' => 3,
                ]) !!}
            </div>
            
            {{-- Accounting Mapping Section --}}
            @if(isset($accounting_accounts) && count($accounting_accounts) > 1)
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4 class="panel-title">
                        <i class="fa fa-book"></i> @lang('gym::lang.accounting_mapping')
                    </h4>
                </div>
                <div class="panel-body">
                    <p class="text-muted">@lang('gym::lang.accounting_mapping_help')</p>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                {!! Form::label('bank_account_id', __('gym::lang.bank_cash_account')) !!}
                                {!! Form::select('bank_account_id', $accounting_accounts, null, [
                                    'class' => 'form-control select2',
                                    'style' => 'width: 100%',
                                    'placeholder' => __('messages.please_select'),
                                ]) !!}
                                <p class="help-block text-muted small">@lang('gym::lang.bank_account_help')</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                {!! Form::label('revenue_account_id', __('gym::lang.revenue_account')) !!}
                                {!! Form::select('revenue_account_id', $accounting_accounts, null, [
                                    'class' => 'form-control select2',
                                    'style' => 'width: 100%',
                                    'placeholder' => __('messages.please_select'),
                                ]) !!}
                                <p class="help-block text-muted small">@lang('gym::lang.revenue_account_help')</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                {!! Form::label('tax_account_id', __('gym::lang.tax_account')) !!}
                                {!! Form::select('tax_account_id', $accounting_accounts, null, [
                                    'class' => 'form-control select2',
                                    'style' => 'width: 100%',
                                    'placeholder' => __('messages.please_select'),
                                ]) !!}
                                <p class="help-block text-muted small">@lang('gym::lang.tax_account_help')</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
            
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox('enable', 1, ['class' => 'input-icheck']) !!}
                        @lang('gym::lang.enable')
                    </label>
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

<script>
$(document).ready(function() {
    $('#session_limit_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('.session-limit-fields').slideDown();
        } else {
            $('.session-limit-fields').slideUp();
        }
    });
    
    $('#session_count_enabled').on('change', function() {
        if ($(this).is(':checked')) {
            $('.session-count-fields').slideDown();
        } else {
            $('.session-count-fields').slideUp();
        }
    });
});
</script>
