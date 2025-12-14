@extends('layouts.app')

@section('title', __('accounting::lang.journal_entry'))

@section('content')

@include('accounting::layouts.nav')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang( 'accounting::lang.journal_entry' )</h1>
</section>
<section class="content no-print">
<div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('journal_entry_date_range_filter', __('report.date_range') . ':') !!}
                        {!! Form::text('journal_entry_date_range_filter', null, 
                            ['placeholder' => __('lang_v1.select_a_date_range'), 
                            'class' => 'form-control', 'readonly']); !!}
                    </div>
                </div>
            @endcomponent
        </div>
    </div>
	@component('components.widget', ['class' => 'box-solid'])
        @can('accounting.add_journal')
            @slot('tool')
                <div class="box-tools">
                    <a class="btn btn-block btn-primary" 
                        href="{{action([\Modules\Accounting\Http\Controllers\JournalEntryController::class, 'create'])}}">
                        <i class="fas fa-plus"></i> @lang( 'messages.add' )</a>
                    <button type="button" class="btn btn-block btn-success" data-toggle="modal" data-target="#import_journal_modal">
                        <i class="fas fa-file-import"></i> @lang('accounting::lang.import')
                    </button>
                </div>
            @endslot
        @endcan
        
        <div class="table-responsive">
        <table class="table table-bordered table-striped" id="journal_table">
            <thead>
                <tr>
                    <th>@lang('messages.action')</th>
                    <th>@lang('accounting::lang.gl_date')</th>
                    <th>@lang('accounting::lang.gl_number')</th>
                    <th>@lang('accounting::lang.gl_code')</th>
                    <th>@lang('accounting::lang.account_name')</th>
                    <th>@lang('accounting::lang.description')</th>
                    <th>@lang('accounting::lang.debit')</th>
                    <th>@lang('accounting::lang.credit')</th>
                    <th>@lang('accounting::lang.balance')</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        </div>

        
    @endcomponent
</section>

@stop

@section('javascript')
<div class="modal fade" id="import_journal_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">@lang('accounting::lang.import')</h4>
            </div>
            {!! Form::open(['url' => action([\Modules\Accounting\Http\Controllers\JournalEntryController::class, 'import']), 'method' => 'post', 'enctype' => 'multipart/form-data', 'id' => 'journal_import_form']) !!}
            <div class="modal-body">
                <div class="form-group">
                    {!! Form::label('journal_import', __('accounting::lang.file_to_import')) !!}
                    {!! Form::file('journal_import', ['class' => 'form-control', 'accept' => '.xls,.xlsx,.csv', 'required']); !!}
                    <p class="help-block">@lang('accounting::lang.gl_date'), @lang('accounting::lang.gl_number'), @lang('accounting::lang.account'), @lang('accounting::lang.account_name'), @lang('accounting::lang.description'), @lang('accounting::lang.debit'), @lang('accounting::lang.credit'), @lang('accounting::lang.balance')</p>
                </div>
                <div class="form-group">
                    <a href="{{ asset('files/journal_template.xlsx') }}" download>@lang('accounting::lang.download_template')</a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                <button type="submit" class="btn btn-primary">@lang('accounting::lang.import')</button>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>
<script type="text/javascript">

    $(document).ready( function(){
        $('#journal_import_form').on('submit', function() {
            $(this).find('button[type="submit"]').attr('disabled', true);
        });
        
        //Journal table
        journal_table = $('#journal_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '/accounting/journal-entry',
                data: function(d) {
                    var start = '';
                    var end = '';
                    if ($('#journal_entry_date_range_filter').val()) {
                        start = $('input#journal_entry_date_range_filter')
                            .data('daterangepicker')
                            .startDate.format('YYYY-MM-DD');
                        end = $('input#journal_entry_date_range_filter')
                            .data('daterangepicker')
                            .endDate.format('YYYY-MM-DD');
                    }
                    d.start_date = start;
                    d.end_date = end;
                },
            },
            aaSorting: [[1, 'desc']],
            columns: [
                { data: 'action', name: 'action', orderable: false, searchable: false },
                { data: 'operation_date', name: 'map.operation_date' },
                { data: 'gl_number', name: 'map.ref_no' },
                { data: 'gl_code', name: 'acc.gl_code' },
                { data: 'account_name', name: 'acc.name' },
                { data: 'description', name: 'map.note' },
                { data: 'debit', name: 'accounting_accounts_transactions.amount', orderable: false, searchable: false, className: 'text-right' },
                { data: 'credit', name: 'accounting_accounts_transactions.amount', orderable: false, searchable: false, className: 'text-right' },
                { data: 'balance', name: 'accounting_accounts_transactions.amount', orderable: false, searchable: false, className: 'text-right' },
            ],
            createdRow: function(row, data) {
                if (data.type === 'debit') {
                    $('td', row).eq(6).addClass('bg-light-green');
                }
                if (data.type === 'credit') {
                    $('td', row).eq(7).addClass('bg-light-green');
                }
            }
        });
        journal_table.on('draw', function() {
            __currency_convert_recursively($('#journal_table'));
        });

        $('#journal_entry_date_range_filter').daterangepicker(
            dateRangeSettings,
            function (start, end) {
                $('#journal_entry_date_range_filter').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                journal_table.ajax.reload();
            }
        );
        $('#journal_entry_date_range_filter').on('cancel.daterangepicker', function(ev, picker) {
            $('#journal_entry_date_range_filter').val('');
            journal_table.ajax.reload();
        });

        //Delete Sale
        $(document).on('click', '.delete_journal_button', function(e) {
            e.preventDefault();
            swal({
                title: LANG.sure,
                icon: 'warning',
                buttons: true,
                dangerMode: true,
            }).then(willDelete => {
                if (willDelete) {
                    var href = $(this).data('href');
                    $.ajax({
                        method: 'DELETE',
                        url: href,
                        dataType: 'json',
                        success: function(result) {
                            if (result.success == true) {
                                toastr.success(result.msg);
                                journal_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        },
                    });
                }
            });
        });

	});

</script>
@endsection




