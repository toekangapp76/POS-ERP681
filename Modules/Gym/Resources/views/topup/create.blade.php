<div class="modal-dialog" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">@lang('gym::lang.add_topup')</h4>
        </div>

        {!! Form::open(['url' => action([\Modules\Gym\Http\Controllers\TopupController::class, 'store']), 'method' => 'post', 'id' => 'topup_form']) !!}

        <div class="modal-body">
    <div class="row">
        <div class="col-sm-12">
            <div class="form-group">
                {!! Form::label('contact_id', __('gym::lang.member') . ':*') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-user"></i></span>
                    {!! Form::select('contact_id', $members, null, [
                        'class' => 'form-control select2',
                        'id' => 'topup_contact_id',
                        'placeholder' => __('gym::lang.select_member'),
                        'required'
                    ]) !!}
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <div class="form-group">
                {!! Form::label('subscription_id', __('gym::lang.subscription') . ':*') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-id-card"></i></span>
                    <select name="subscription_id" id="topup_subscription_id" class="form-control" required>
                        <option value="">@lang('gym::lang.select_member_first')</option>
                    </select>
                </div>
                <div id="subscription_info" class="text-muted" style="margin-top: 5px;"></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('hours_added', __('gym::lang.hours_added') . ':*') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-clock"></i></span>
                    {!! Form::text('hours_added', null, [
                        'class' => 'form-control input_number',
                        'required',
                        'placeholder' => '0'
                    ]) !!}
                    <span class="input-group-addon">@lang('gym::lang.hours')</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('amount', __('gym::lang.amount') . ':') !!}
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-money"></i></span>
                    {!! Form::text('amount', 0, [
                        'class' => 'form-control input_number'
                    ]) !!}
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <div class="form-group">
                {!! Form::label('note', __('gym::lang.notes') . ':') !!}
                {!! Form::textarea('note', null, [
                    'class' => 'form-control',
                    'rows' => 2,
                    'placeholder' => __('gym::lang.notes')
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

<script>
$('#topup_contact_id').change(function() {
    var member_id = $(this).val();
    var subSelect = $('#topup_subscription_id');
    var infoDiv = $('#subscription_info');
    
    subSelect.html('<option value="">{{ __("gym::lang.loading") }}...</option>');
    infoDiv.html('');
    
    if (member_id) {
        $.get("{{ url('gym/topups/subscriptions') }}/" + member_id, function(subs) {
            if (subs.length === 0) {
                subSelect.html('<option value="">{{ __("gym::lang.no_active_subscription") }}</option>');
            } else {
                subSelect.html('<option value="">{{ __("gym::lang.select_class") }}</option>');
                $.each(subs, function(i, sub) {
                    var label = sub.name;
                    if (sub.end_date) {
                        label += ' ({{ __("gym::lang.expires") }}: ' + sub.end_date + ')';
                    }
                    subSelect.append('<option value="' + sub.id + '" data-remaining="' + sub.remaining_hours + '">' + label + '</option>');
                });
            }
        });
    } else {
        subSelect.html('<option value="">{{ __("gym::lang.select_member_first") }}</option>');
    }
});

$('#topup_subscription_id').change(function() {
    var selected = $(this).find(':selected');
    var remaining = selected.data('remaining');
    var infoDiv = $('#subscription_info');
    
    if (remaining !== undefined) {
        infoDiv.html('<i class="fa fa-info-circle"></i> {{ __("gym::lang.remaining_time") }}: <strong>' + remaining + ' {{ __("gym::lang.hours") }}</strong>');
    } else {
        infoDiv.html('');
    }
});

$('#topup_form').validate({
    submitHandler: function(form) {
        $.ajax({
            method: "POST",
            url: $(form).attr('action'),
            data: $(form).serialize(),
            dataType: "json",
            beforeSend: function() {
                $(form).find('button[type="submit"]').prop('disabled', true);
            },
            success: function(result) {
                if (result.success) {
                    $('.topup_modal').modal('hide');
                    toastr.success(result.msg);
                    if (typeof topup_table !== 'undefined') {
                        topup_table.ajax.reload();
                    }
                } else {
                    toastr.error(result.msg);
                }
                $(form).find('button[type="submit"]').prop('disabled', false);
            }
        });
    }
});
</script>

    </div>
</div>
