@extends('layouts.app')
@section('title', __('gym::lang.courts'))

@section('content')
@include('gym::layouts.nav')
<section class="content-header">
    <h1>@lang('gym::lang.courts')
        <small>@lang('gym::lang.manage_courts')</small>
    </h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">@lang('gym::lang.all_courts')</h3>
            <div class="box-tools">
                <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-dw-btn-sm btn-modal" 
                        data-href="{{ action([\Modules\Gym\Http\Controllers\CourtController::class, 'create']) }}"
                        data-container=".court_modal">
                    <i class="fa fa-plus"></i> @lang('gym::lang.add_court')
                </button>
            </div>
        </div>
        <div class="box-body">
            {{-- <div class="row" style="margin-bottom: 10px;">
                <div class="col-sm-3">
                    <select id="filter_class" class="form-control select2">
                        <option value="">@lang('gym::lang.all_classes')</option>
                        @foreach($classes as $class_id => $class_name)
                            <option value="{{ $class_id }}">{{ $class_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div> --}}
            <table class="table table-bordered table-striped" id="courts_table">
                <thead>
                    <tr>
                        <th>@lang('gym::lang.name')</th>
                        <th>@lang('gym::lang.code')</th>
                        <th>@lang('gym::lang.class')</th>
                        <th>@lang('gym::lang.price_per_hour')</th>
                        <th>@lang('gym::lang.capacity')</th>
                        <th>@lang('gym::lang.status')</th>
                        <th>@lang('messages.action')</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</section>

<div class="modal fade court_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content"></div>
    </div>
</div>
@endsection

@section('javascript')
<script>
$(document).ready(function() {
    var courts_table = $('#courts_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ action([\Modules\Gym\Http\Controllers\CourtController::class, 'index']) }}",
            data: function(d) {
                d.class_id = $('#filter_class').val();
            }
        },
        columns: [
            {data: 'name', name: 'name'},
            {data: 'code', name: 'code'},
            {data: 'gym_class', name: 'gym_classes.name', searchable: true},
            {data: 'price_per_hour', name: 'price_per_hour', searchable: false},
            {data: 'capacity', name: 'capacity'},
            {data: 'is_active', name: 'is_active', searchable: false},
            {data: 'action', name: 'action', orderable: false, searchable: false}
        ]
    });

    $('#filter_class').change(function() {
        courts_table.ajax.reload();
    });

    $(document).on('click', '.delete_court', function() {
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
                            courts_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            }
        });
    });

    $('.court_modal').on('shown.bs.modal', function() {
        $(this).find('.select2').select2({
            dropdownParent: $(this)
        });
    });
});
</script>
@endsection
