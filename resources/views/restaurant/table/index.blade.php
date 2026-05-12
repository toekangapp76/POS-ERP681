@extends('layouts.app')
@section('title', __('restaurant.tables'))

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('restaurant.tables')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('restaurant.manage_your_tables')</small>
        </h1>
        <!-- <ol class="breadcrumb">
            <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
            <li class="active">Here</li>
        </ol> -->
    </section>

    <!-- Main content -->
    <section class="content">

        {{-- <div class="box">
        <div class="box-header">
        	<h3 class="box-title">@lang( 'restaurant.all_your_tables' )</h3>
            @can('restaurant.create')
            	<div class="box-tools">
                    <button type="button" class="btn btn-block btn-primary btn-modal" 
                    	data-href="{{action([\App\Http\Controllers\Restaurant\TableController::class, 'create'])}}" 
                    	data-container=".tables_modal">
                    	<i class="fa fa-plus"></i> @lang( 'messages.add' )</button>
                </div>
            @endcan
        </div>
        <div class="box-body">
            @can('restaurant.view')
            	<table class="table table-bordered table-striped" id="tables_table">
            		<thead>
            			<tr>
            				<th>@lang( 'restaurant.table' )</th>
                            <th>@lang( 'purchase.business_location' )</th>
            				<th>@lang( 'restaurant.description' )</th>
            				<th>@lang( 'messages.action' )</th>
            			</tr>
            		</thead>
            	</table>
            @endcan
        </div>
    </div> --}}

        @component('components.widget')
            <div class="box-header">
                <h3 class="box-title">@lang('restaurant.all_your_tables')</h3>
                @can('restaurant.create')
                    <div class="box-tools">
                        {{-- <button type="button" class="btn btn-block btn-primary btn-modal"
                            data-href="{{ action([\App\Http\Controllers\Restaurant\TableController::class, 'create']) }}"
                            data-container=".tables_modal">
                            <i class="fa fa-plus"></i> @lang('messages.add')</button> --}}
                        <button class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full btn-modal"
                            data-href="{{ action([\App\Http\Controllers\Restaurant\TableController::class, 'create']) }}"
                            data-container=".tables_modal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                            class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                            <path d="M12 5l0 14" />
                            <path d="M5 12l14 0" />
                        </svg> @lang('messages.add')
                        </button>
                    </div>
                @endcan
            </div>
            <div class="box-body">
                @can('restaurant.view')
                    <table class="table table-bordered table-striped" id="tables_table">
                        <thead>
                            <tr>
                                <th>@lang('restaurant.table')</th>
                                <th>@lang('purchase.business_location')</th>
                                <th>@lang('restaurant.description')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                    </table>
                @endcan
            </div>
        @endcomponent

        <div class="modal fade tables_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        </div>

        {{-- QR Code Modal --}}
        <div class="modal fade" id="qr_modal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-sm" style="max-width:340px">
                <div class="modal-content">
                    <div class="modal-header" style="background:#2d3a8c;color:#fff;border-radius:4px 4px 0 0">
                        <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-qrcode"></i> QR Self-Order — <span id="qr_table_name"></span></h4>
                    </div>
                    <div class="modal-body" style="text-align:center;padding:24px">
                        <div id="qr_code_container" style="display:inline-block;padding:12px;border:2px solid #eee;border-radius:8px;background:#fff"></div>
                        <p style="margin-top:12px;font-size:12px;color:#888;word-break:break-all" id="qr_url_text"></p>
                    </div>
                    <div class="modal-footer" style="border-top:1px solid #eee;display:flex;gap:8px">
                        <button type="button" class="btn btn-default" data-dismiss="modal" style="flex:1">Tutup</button>
                        <button type="button" class="btn btn-primary" id="btn_copy_url" style="flex:1"><i class="fa fa-copy"></i> Copy Link</button>
                        <button type="button" class="btn btn-success" onclick="printQr()" style="flex:1"><i class="fa fa-print"></i> Print QR</button>
                    </div>
                </div>
            </div>
        </div>

    </section>
    <!-- /.content -->

@endsection

@section('javascript')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script type="text/javascript">
        var _qrInstance = null;

        function printQr() {
            var url  = document.getElementById('qr_url_text').textContent;
            var name = document.getElementById('qr_table_name').textContent;
            var w = window.open('', '_blank', 'width=400,height=500');
            w.document.write(`<!DOCTYPE html><html><head><title>QR Meja ${name}</title>
                <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:sans-serif;text-align:center;padding:30px}
                h2{font-size:20px;margin-bottom:8px}p{font-size:12px;color:#666;margin-bottom:20px}
                #qr{display:inline-block;padding:12px;border:2px solid #ddd;border-radius:8px}
                .footer{margin-top:16px;font-size:11px;color:#aaa}</style></head>
                <body onload="window.print()">
                <h2>Meja: ${name}</h2>
                <p>Scan untuk memesan</p>
                <div id="qr"></div>
                <div class="footer">${url}</div>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>
                <script>new QRCode(document.getElementById('qr'),{text:'${url}',width:220,height:220,correctLevel:QRCode.CorrectLevel.M})<\/script>
                </body></html>`);
            w.document.close();
        }

        $(document).ready(function() {

            $(document).on('submit', 'form#table_add_form', function(e) {
                e.preventDefault();
                var data = $(this).serialize();

                $.ajax({
                    method: "POST",
                    url: $(this).attr("action"),
                    dataType: "json",
                    data: data,
                    success: function(result) {
                        if (result.success == true) {
                            $('div.tables_modal').modal('hide');
                            toastr.success(result.msg);
                            tables_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            });

            //Brands table
            var tables_table = $('#tables_table').DataTable({
                processing: true,
                serverSide: true,
                fixedHeader:false,
                ajax: '/modules/tables',
                columnDefs: [{
                    "targets": 3,
                    "orderable": false,
                    "searchable": false
                }],
                columns: [{
                        data: 'name',
                        name: 'res_tables.name'
                    },
                    {
                        data: 'location',
                        name: 'BL.name'
                    },
                    {
                        data: 'description',
                        name: 'description'
                    },
                    {
                        data: 'action',
                        name: 'action'
                    }
                ],
            });

            $(document).on('click', 'button.edit_table_button', function() {

                $("div.tables_modal").load($(this).data('href'), function() {

                    $(this).modal('show');

                    $('form#table_edit_form').submit(function(e) {
                        e.preventDefault();
                        var data = $(this).serialize();

                        $.ajax({
                            method: "POST",
                            url: $(this).attr("action"),
                            dataType: "json",
                            data: data,
                            success: function(result) {
                                if (result.success == true) {
                                    $('div.tables_modal').modal('hide');
                                    toastr.success(result.msg);
                                    tables_table.ajax.reload();
                                } else {
                                    toastr.error(result.msg);
                                }
                            }
                        });
                    });
                });
            });

            // QR Code button
            $(document).on('click', 'button.show_qr_button', function() {
                var token = $(this).data('token');
                var name  = $(this).data('name');
                var url   = window.location.origin + '/self-order/' + token;

                document.getElementById('qr_table_name').textContent = name;
                document.getElementById('qr_url_text').textContent   = url;

                var container = document.getElementById('qr_code_container');
                container.innerHTML = '';
                if (_qrInstance) { _qrInstance = null; }
                _qrInstance = new QRCode(container, {
                    text:  url,
                    width: 200,
                    height: 200,
                    correctLevel: QRCode.CorrectLevel.M,
                });

                $('#qr_modal').modal('show');
            });

            $('#btn_copy_url').on('click', function() {
                var url = document.getElementById('qr_url_text').textContent;
                navigator.clipboard.writeText(url).then(function() {
                    toastr.success('Link disalin!');
                });
            });

            $(document).on('click', 'button.delete_table_button', function() {
                swal({
                    title: LANG.sure,
                    text: LANG.confirm_delete_table,
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then((willDelete) => {
                    if (willDelete) {
                        var href = $(this).data('href');
                        var data = $(this).serialize();

                        $.ajax({
                            method: "DELETE",
                            url: href,
                            dataType: "json",
                            data: data,
                            success: function(result) {
                                if (result.success == true) {
                                    toastr.success(result.msg);
                                    tables_table.ajax.reload();
                                } else {
                                    toastr.error(result.msg);
                                }
                            }
                        });
                    }
                });
            });
        });
    </script>
@endsection
