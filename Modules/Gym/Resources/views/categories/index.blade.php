@extends('layouts.app')
@section('title', __('gym::lang.gym_categories'))
@section('content')
    @include('gym::layouts.nav')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black"> @lang('gym::lang.gym_categories')
        </h1>
    </section>
    <!-- Main content -->
    <section class="content">
        @component('components.widget')
            <div class="box-tools tw-flex tw-justify-end tw-gap-2.5 tw-mb-4">
                <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right btn-modal"
                    data-href="{{ action([\Modules\Gym\Http\Controllers\GymCategoryController::class, 'create']) }}"
                    data-container=".view_modal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 5l0 14" />
                        <path d="M5 12l14 0" />
                    </svg> @lang('messages.add')
                </a>
            </div>
            <table class="table table-bordered table-striped" id="gym_category_table">
                <thead>
                    <tr>
                        <th>@lang('gym::lang.name')</th>
                        <th>@lang('gym::lang.description')</th>
                        <th>@lang('lang_v1.status')</th>
                        <th>@lang('lang_v1.created_at')</th>
                        <th>@lang('messages.action')</th>
                    </tr>
                </thead>
            </table>
        @endcomponent

        <div class="modal fade view_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

    </section>
    <!-- /.content -->

@endsection

@section('javascript')

    <script type="text/javascript">
        $(document).ready(function() {
            gym_category_table = $('#gym_category_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ action([\Modules\Gym\Http\Controllers\GymCategoryController::class, 'index']) }}",
                },
                aaSorting: [
                    [3, 'desc']
                ],
                columns: [
                    {
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'description',
                        name: 'description'
                    },
                    {
                        data: 'is_active',
                        name: 'is_active'
                    },
                    {
                        data: 'created_at',
                        name: 'created_at'
                    },
                    {
                        data: 'action',
                        name: 'action',
                        sorting: false,
                    }
                ],
            });

            $(document).on('click', 'a.delete_category_confirmation', function(e) {
                e.preventDefault();
                swal({
                    title: LANG.sure,
                    text: "{{ __('gym::lang.delete_alert') }}",
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then((confirmed) => {
                    if (confirmed) {
                        window.location.href = $(this).attr('href');
                    }
                });
            });
        });
    </script>
@endsection
