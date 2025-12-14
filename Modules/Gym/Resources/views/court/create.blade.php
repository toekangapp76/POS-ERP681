<div class="modal-dialog" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">@lang('gym::lang.add_court')</h4>
        </div>

        {!! Form::open(['url' => action([\Modules\Gym\Http\Controllers\CourtController::class, 'store']), 'method' => 'post', 'id' => 'court_form']) !!}

        <div class="modal-body">
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('name', __('gym::lang.name') . ':*') !!}
                {!! Form::text('name', null, ['class' => 'form-control', 'required', 'placeholder' => __('gym::lang.court_name_placeholder')]) !!}
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('code', __('gym::lang.code') . ':') !!}
                {!! Form::text('code', null, ['class' => 'form-control', 'placeholder' => 'e.g., P1, P2']) !!}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('gym_class_id', __('gym::lang.class') . ':*') !!}
                {!! Form::select('gym_class_id', $classes, null, ['class' => 'form-control select2', 'required', 'placeholder' => __('gym::lang.select_class')]) !!}
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('price_per_hour', __('gym::lang.price_per_hour') . ':') !!}
                {!! Form::text('price_per_hour', 0, ['class' => 'form-control input_number']) !!}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('capacity', __('gym::lang.capacity') . ':') !!}
                {!! Form::number('capacity', 4, ['class' => 'form-control', 'min' => 1]) !!}
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('sort_order', __('gym::lang.sort_order') . ':') !!}
                {!! Form::number('sort_order', 0, ['class' => 'form-control', 'min' => 0]) !!}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <div class="form-group">
                {!! Form::label('description', __('gym::lang.description') . ':') !!}
                {!! Form::textarea('description', null, ['class' => 'form-control', 'rows' => 2]) !!}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
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
    <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
        <i class="fa fa-save"></i> @lang('messages.save')
    </button>
    <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">
        @lang('messages.close')
    </button>
</div>

{!! Form::close() !!}

<script>
$('#court_form').validate({
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
                    $('.court_modal').modal('hide');
                    toastr.success(result.msg);
                    $('#courts_table').DataTable().ajax.reload();
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
