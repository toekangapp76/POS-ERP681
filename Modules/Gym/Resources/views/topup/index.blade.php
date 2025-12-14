@extends('layouts.app')
@section('title', __('gym::lang.topup_hours'))

@section('content')
@include('gym::layouts.nav')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        @lang('gym::lang.topup_hours')
    </h1>
</section>

<section class="content">
    @component('components.widget')
        <div class="box-tools tw-flex tw-justify-end tw-gap-2.5 tw-mb-4">
            <button type="button" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full btn-modal"
                    data-href="{{ action([\Modules\Gym\Http\Controllers\TopupController::class, 'create']) }}"
                    data-container=".topup_modal">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                    class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                    <path d="M12 5l0 14" />
                    <path d="M5 12l14 0" />
                </svg> @lang('gym::lang.add_topup')
            </button>
        </div>

        <table class="table table-bordered table-striped" id="topup_table">
            <thead>
                <tr>
                    <th>@lang('gym::lang.ref_no')</th>
                    <th>@lang('gym::lang.member')</th>
                    <th>@lang('gym::lang.package')</th>
                    <th>@lang('gym::lang.hours_added')</th>
                    <th>@lang('gym::lang.amount')</th>
                    <th>@lang('gym::lang.notes')</th>
                    <th>@lang('gym::lang.created_by')</th>
                    <th>@lang('gym::lang.created_at')</th>
                    <th>@lang('messages.action')</th>
                </tr>
            </thead>
        </table>
    @endcomponent

    <div class="modal fade topup_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content"></div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function() {
    var topup_table = $('#topup_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ action([\Modules\Gym\Http\Controllers\TopupController::class, 'index']) }}",
        columns: [
            {data: 'ref_number', name: 'topup_trans.ref_no'},
            {data: 'member_name', name: 'contacts.name'},
            {data: 'package_name', name: 'gym_packages.name'},
            {data: 'hours_added', name: 'gym_hour_topups.hours_added'},
            {data: 'amount', name: 'gym_hour_topups.amount'},
            {data: 'note', name: 'gym_hour_topups.note'},
            {data: 'created_by_name', name: 'users.first_name'},
            {data: 'created_at', name: 'gym_hour_topups.created_at'},
            {data: 'action', name: 'action', orderable: false, searchable: false}
        ],
        order: [[7, 'desc']]
    });

    $(document).on('click', '.delete_topup', function() {
        var href = $(this).data('href');
        swal({
            title: LANG.sure,
            text: "{{ __('gym::lang.delete_alert') }}",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                    method: "DELETE",
                    url: href,
                    dataType: "json",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(result) {
                        if (result.success) {
                            toastr.success(result.msg);
                            topup_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            }
        });
    });

    $('.topup_modal').on('shown.bs.modal', function() {
        $(this).find('.select2').select2({
            dropdownParent: $(this)
        });
    });
});
</script>
@endsection
