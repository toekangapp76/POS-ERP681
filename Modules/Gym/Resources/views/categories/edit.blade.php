<div class="modal-dialog" role="document">
    <div class="modal-content">

        {!! Form::open([
            'url' => action([\Modules\Gym\Http\Controllers\GymCategoryController::class, 'update'], ['gym_category' => $category->id]),
            'method' => 'put',
            'id' => 'edit_gym_category',
        ]) !!}

        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                    aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">@lang('gym::lang.edit_gym_category')</h4>
        </div>

        <div class="modal-body">
            <div class="form-group">
                {!! Form::label('name', __('gym::lang.name') . '*') !!}
                {!! Form::text('name', $category->name, [
                    'class' => 'form-control',
                    'required',
                    'placeholder' => __('gym::lang.name'),
                ]) !!}
            </div>
            
            <div class="form-group">
                {!! Form::label('description', __('gym::lang.description')) !!}
                {!! Form::textarea('description', $category->description, [
                    'class' => 'form-control',
                    'placeholder' => __('gym::lang.description'),
                    'rows' => 3,
                ]) !!}
            </div>
            
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox('is_active', 1, $category->is_active, ['class' => 'input-icheck']) !!}
                        @lang('gym::lang.active')
                    </label>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.update')</button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white"
                data-dismiss="modal">@lang('messages.close')</button>
        </div>

        {!! Form::close() !!}

    </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->
