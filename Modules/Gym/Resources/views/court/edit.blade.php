<div class="modal-dialog" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">@lang('gym::lang.edit_court')</h4>
        </div>

        {!! Form::open(['url' => action([\Modules\Gym\Http\Controllers\CourtController::class, 'update'], $court->id), 'method' => 'put', 'id' => 'court_form']) !!}

        <div class="modal-body">
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('name', __('gym::lang.name') . ':*') !!}
                {!! Form::text('name', $court->name, ['class' => 'form-control', 'required']) !!}
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('code', __('gym::lang.code') . ':') !!}
                {!! Form::text('code', $court->code, ['class' => 'form-control']) !!}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('gym_class_id', __('gym::lang.class') . ':*') !!}
                {!! Form::select('gym_class_id', $classes, $court->gym_class_id, ['class' => 'form-control select2', 'required']) !!}
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('price_per_hour', __('gym::lang.price_per_hour') . ':') !!}
                {!! Form::text('price_per_hour', $court->price_per_hour, ['class' => 'form-control input_number']) !!}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('capacity', __('gym::lang.capacity') . ':') !!}
                {!! Form::number('capacity', $court->capacity, ['class' => 'form-control', 'min' => 1]) !!}
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('sort_order', __('gym::lang.sort_order') . ':') !!}
                {!! Form::number('sort_order', $court->sort_order, ['class' => 'form-control', 'min' => 0]) !!}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <div class="form-group">
                {!! Form::label('description', __('gym::lang.description') . ':') !!}
                {!! Form::textarea('description', $court->description, ['class' => 'form-control', 'rows' => 2]) !!}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <div class="checkbox">
                <label>
                    {!! Form::checkbox('is_active', 1, $court->is_active) !!}
                    @lang('gym::lang.is_active')
                </label>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
        <i class="fa fa-save"></i> @lang('messages.update')
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
            method: "PUT",
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
