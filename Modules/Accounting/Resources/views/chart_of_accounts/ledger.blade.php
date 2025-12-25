@extends('layouts.app')

@section('title', __('accounting::lang.ledger'))

@section('content')

@include('accounting::layouts.nav')
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('accounting::lang.ledger') - <span class="account-details-name">{{$account->name}}</span></h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-5">
            <div class="box box-solid">
                <div class="box-body">
                    <table class="table table-condensed">
                        <tr>
                            <th>@lang('user.name'):</th>
                            <td class="account-details-name">
                                {{$account->name}}

                                @if(!empty($account->gl_code))
                                    ({{$account->gl_code}})
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>@lang('accounting::lang.account_type'):</th>
                            <td class="account-details-type">
                                @if(!empty($account->account_primary_type))
                                    {{__('accounting::lang.' . $account->account_primary_type)}}
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>@lang('accounting::lang.account_sub_type'):</th>
                            <td class="account-details-subtype">
                                @if(!empty($account->account_sub_type))
                                    {{__('accounting::lang.' . $account->account_sub_type->name)}}
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <th>@lang('accounting::lang.detail_type'):</th>
                            <td class="account-details-detailtype">
                                @if(!empty($account->detail_type))
                                    {{__('accounting::lang.' . $account->detail_type->name)}}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>@lang('lang_v1.balance'):</th>
                            <td class="account-details-balance">@format_currency($current_bal)</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-7">

            <div class="box box-solid">
                <div class="box-header">
                    <h3 class="box-title"> <i class="fa fa-filter" aria-hidden="true"></i> @lang('report.filters'):</h3>
                </div>
                <div class="box-body">
                    <div class="col-sm-6">
                        <div class="form-group">
                            {!! Form::label('transaction_date_range', __('report.date_range') . ':') !!}
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                                {!! Form::text('transaction_date_range', null, ['class' => 'form-control', 'readonly', 'placeholder' => __('report.date_range')]) !!}
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-6">
                        <div class="form-group">
                            {!! Form::label('all_accounts', __('accounting::lang.account') . ':') !!}
                            {{-- Search Input for Accounts --}}
                            <div class="input-group" style="margin-bottom: 8px;">
                                <span class="input-group-addon"><i class="fa fa-search"></i></span>
                                <input type="text" id="account_search" class="form-control" placeholder="Cari akun...">
                            </div>

                            {{-- Scrollable Account List with Checkboxes --}}
                            <div id="account_checkbox_list"
                                style="max-height: 100px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #fff;">
                                @foreach($all_accounts as $acc_id => $acc_name)
                                    <div class="checkbox account-checkbox-item" data-name="{{ strtolower($acc_name) }}"
                                        style="margin: 3px 0;">
                                        <label style="font-weight: normal;">
                                            <input type="checkbox" class="account-checkbox" value="{{ $acc_id }}" {{ $acc_id == $account->id ? 'checked' : '' }}>
                                            {{ $acc_name }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Hidden select for compatibility with existing code --}}
                            <select name="account_filter[]" id="account_filter" multiple="multiple" class="hidden"
                                data-default="{{ $account->id }}">
                                @foreach($all_accounts as $acc_id => $acc_name)
                                    <option value="{{ $acc_id }}" {{ $acc_id == $account->id ? 'selected' : '' }}>
                                        {{ $acc_name }}
                                    </option>
                                @endforeach
                            </select>

                            <div class="btn-group" style="margin-top:6px;">
                                <button type="button" id="select_all_accounts" class="btn btn-xs btn-default"><i
                                        class="fa fa-check-square-o"></i> @lang('accounting::lang.select_all')</button>
                                <button type="button" id="deselect_all_accounts" class="btn btn-xs btn-default"><i
                                        class="fa fa-square-o"></i> @lang('accounting::lang.deselect_all')</button>
                            </div>
                            <div style="margin-top: 5px;">
                                <small class="text-muted" id="selected_count_display">1 akun dipilih</small>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-sm-12">
            <div class="box">
                <div class="box-body">
                    @can('account.access')
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="ledger">
                                <thead>
                                    <tr>
                                        <th>@lang('messages.date')</th>
                                        <th>@lang('accounting::lang.account')</th>
                                        <th>@lang('lang_v1.description')</th>
                                        <th>@lang('brand.note')</th>
                                        <th>@lang('lang_v1.added_by')</th>
                                        <th>@lang('account.debit')</th>
                                        <th>@lang('account.credit')</th>
                                        <th>@lang( 'lang_v1.balance' )</th>
                                        <!-- <th>@lang('messages.action')</th> -->
                                    </tr>
                                </thead>



                                <tfoot>
                                    <tr class="bg-gray font-17 footer-total text-center">
                                        <td colspan="5"><strong>@lang('sale.total'):</strong></td>
                                        <td class="footer_total_debit"></td>
                                        <td class="footer_total_credit"></td>
                                        <td class="footer_total_balance"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</section>

@stop

@section('javascript')
@include('accounting::accounting.common_js')
<script>
    $(document).ready(function () {
        // Sync checkboxes with hidden select
        function syncCheckboxesToSelect() {
            var selectedValues = [];
            $('.account-checkbox:checked').each(function () {
                selectedValues.push($(this).val());
            });
            $('#account_filter').val(selectedValues);
            updateSelectedCountDisplay();
            updateAccountDetails();
            if (typeof ledger !== 'undefined') {
                ledger.ajax.reload();
            }
        }

        // Update selected count display
        function updateSelectedCountDisplay() {
            var count = $('.account-checkbox:checked').length;
            if (count === 0) {
                $('#selected_count_display').text('Tidak ada akun dipilih');
            } else if (count === 1) {
                $('#selected_count_display').text('1 akun dipilih');
            } else {
                $('#selected_count_display').text(count + ' akun dipilih');
            }
        }

        // Checkbox change handler
        $(document).on('change', '.account-checkbox', function () {
            syncCheckboxesToSelect();
        });

        // Search functionality for accounts
        $('#account_search').on('keyup', function () {
            var searchText = $(this).val().toLowerCase();
            $('.account-checkbox-item').each(function () {
                var itemName = $(this).data('name');
                if (itemName.indexOf(searchText) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Function to update account details box
        function updateAccountDetails() {
            var account_ids = $('#account_filter').val();
            if (!account_ids || account_ids.length === 0) {
                $('.account-details-name').html('-');
                $('.account-details-type').html('-');
                $('.account-details-subtype').html('-');
                $('.account-details-detailtype').html('-');
                $('.account-details-balance').html('@format_currency(0)');
                return;
            }

            $.ajax({
                url: '{{action([\Modules\Accounting\Http\Controllers\CoaController::class, 'getAccountDetails'])}}',
                type: 'POST',
                data: { account_ids: account_ids },
                success: function (response) {
                    if (response.count === 1) {
                        // Single account - show full details
                        var acc = response.accounts[0];
                        var nameHtml = acc.name;
                        if (acc.gl_code) {
                            nameHtml += ' (' + acc.gl_code + ')';
                        }
                        $('.account-details-name').html(nameHtml);
                        $('.account-details-type').html(acc.account_primary_type ? acc.account_primary_type : '-');
                        $('.account-details-subtype').html(acc.account_sub_type ? acc.account_sub_type : '-');
                        $('.account-details-detailtype').html(acc.detail_type ? acc.detail_type : '-');
                    } else {
                        // Multiple accounts - show summary (MAX 5 names only)
                        var maxDisplay = 5;
                        var displayAccounts = response.accounts.slice(0, maxDisplay);
                        var names = displayAccounts.map(function (a) {
                            return a.name + (a.gl_code ? ' (' + a.gl_code + ')' : '');
                        }).join(', ');

                        // Add "and X more" if there are more than 5
                        if (response.count > maxDisplay) {
                            names += ' <span class="text-muted">... dan ' + (response.count - maxDisplay) + ' lainnya</span>';
                        }

                        $('.account-details-name').html('<strong>' + response.count + ' akun dipilih:</strong><br><small>' + names + '</small>');
                        $('.account-details-type').html('-');
                        $('.account-details-subtype').html('-');
                        $('.account-details-detailtype').html('-');
                    }
                    // Update balance
                    $('.account-details-balance').html(__currency_trans_from_en(response.total_balance, true));
                },
                error: function () {
                    console.error('Failed to fetch account details');
                }
            });
        }

        // Select All - only visible accounts (filtered by search)
        $('#select_all_accounts').click(function () {
            $('.account-checkbox-item:visible .account-checkbox').prop('checked', true);
            syncCheckboxesToSelect();
        });

        // Deselect All - only visible accounts (filtered by search)
        $('#deselect_all_accounts').click(function () {
            $('.account-checkbox-item:visible .account-checkbox').prop('checked', false);
            syncCheckboxesToSelect();
        });

        // Initial display update
        updateSelectedCountDisplay();

        dateRangeSettings.startDate = moment().subtract(6, 'days');
        dateRangeSettings.endDate = moment();
        $('#transaction_date_range').daterangepicker(
            dateRangeSettings,
            function (start, end) {
                $('#transaction_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));

                ledger.ajax.reload();
            }
        );

        // Helper function to strip HTML tags
        function stripHtml(html) {
            var tmp = document.createElement("DIV");
            tmp.innerHTML = html;
            return tmp.textContent || tmp.innerText || "";
        }

        // Helper function to parse Indonesian formatted number
        function parseIndonesianNumber(str) {
            if (!str) return 0;
            var cleaned = stripHtml(str);
            cleaned = cleaned.replace(/Rp\s*/gi, '').trim();
            cleaned = cleaned.replace(/[↑↓▲▼]/g, '').trim();
            
            // Check if it looks like a number
            if (!cleaned.match(/^-?[\d.,]+$/)) {
                return 0;
            }
            
            // Detect format by finding the last occurrence of . or ,
            var lastDot = cleaned.lastIndexOf('.');
            var lastComma = cleaned.lastIndexOf(',');
            
            // If both exist, the one that comes last is the decimal separator
            if (lastDot > lastComma) {
                // Format: 1,234.56 (comma = thousand, dot = decimal) - International
                cleaned = cleaned.replace(/,/g, ''); // Remove thousand separator
            } else if (lastComma > lastDot) {
                // Format: 1.234,56 (dot = thousand, comma = decimal) - Indonesian
                cleaned = cleaned.replace(/\./g, '').replace(',', '.'); // Remove thousand, convert decimal
            } else if (lastDot !== -1) {
                // Only dot exists
                if (cleaned.match(/\.\d{2}$/)) {
                    // Likely decimal: 123.45
                } else {
                    // Likely thousand separator: 1.234
                    cleaned = cleaned.replace(/\./g, '');
                }
            } else if (lastComma !== -1) {
                // Only comma exists - treat as decimal
                cleaned = cleaned.replace(',', '.');
            }
            
            var num = parseFloat(cleaned);
            return isNaN(num) ? 0 : num;
        }

        // Account Book
        ledger = $('#ledger').DataTable({
            processing: true,
            serverSide: true,
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: '<i class="fa fa-file-excel-o"></i> Excel',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7],
                        format: {
                            body: function (data, row, column, node) {
                                // For columns with currency (columns 5-7), extract numeric value
                                if (column >= 5 && column <= 7) {
                                    var $el = $(data);
                                    if ($el.data('orig-value') !== undefined) {
                                        return parseFloat($el.data('orig-value'));
                                    }
                                    return parseIndonesianNumber(data);
                                }
                                // Strip HTML from all other columns
                                return stripHtml(data);
                            }
                        }
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fa fa-file-pdf-o"></i> PDF',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7],
                        format: {
                            body: function (data, row, column, node) {
                                if (column >= 5 && column <= 7) {
                                    var $el = $(data);
                                    if ($el.data('orig-value') !== undefined) {
                                        return parseFloat($el.data('orig-value')).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                    var num = parseIndonesianNumber(data);
                                    return num.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                }
                                return stripHtml(data);
                            }
                        }
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fa fa-print"></i> Print',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7]
                    }
                }
            ],
            ajax: {
                url: '{{action([\Modules\Accounting\Http\Controllers\CoaController::class, 'ledger'], [$account->id])}}',
                type: 'POST',
                data: function (d) {
                    var start = '';
                    var end = '';
                    if ($('#transaction_date_range').val()) {
                        start = $('input#transaction_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                        end = $('input#transaction_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    }
                    var account_ids = $('#account_filter').val();
                    d.start_date = start;
                    d.end_date = end;
                    d.account_ids = account_ids;
                }
            },
            "ordering": false,
            columns: [
                { data: 'operation_date', name: 'operation_date' },
                { data: 'account_name', name: 'AA.name' },
                { data: 'ref_no', name: 'ATM.ref_no' },
                { data: 'note', name: 'ATM.note' },
                { data: 'added_by', name: 'added_by' },
                { data: 'debit', name: 'amount', searchable: false },
                { data: 'credit', name: 'amount', searchable: false },
                { data: 'balance', name: 'balance', searchable: false, orderable: false }
                // { data: 'action', name: 'action', searchable: false }
            ],
            "fnDrawCallback": function (oSettings) {
                __currency_convert_recursively($('#ledger'));
                
                // Calculate running balance for each row
                var runningBalance = 0;
                var api = this.api();
                
                api.rows({ page: 'current' }).every(function(rowIdx) {
                    var data = this.data();
                    var $balanceCell = $(data.balance);
                    var change = parseFloat($balanceCell.data('change')) || 0;
                    
                    runningBalance += change;
                    
                    // Update the balance cell in the table
                    var $row = $(api.row(rowIdx).node());
                    var balanceClass = runningBalance >= 0 ? 'text-success' : 'text-danger';
                    $row.find('td:eq(7)').html('<span class="' + balanceClass + '" data-orig-value="' + runningBalance + '">' + __currency_trans_from_en(Math.abs(runningBalance), true) + (runningBalance < 0 ? ' (Cr)' : ' (Dr)') + '</span>');
                });
            },
            "footerCallback": function (row, data, start, end, display) {
                var footer_total_debit = 0;
                var footer_total_credit = 0;
                var final_balance = 0;

                for (var r in data) {
                    footer_total_debit += $(data[r].debit).data('orig-value') ? parseFloat($(data[r].debit).data('orig-value')) : 0;
                    footer_total_credit += $(data[r].credit).data('orig-value') ? parseFloat($(data[r].credit).data('orig-value')) : 0;
                    
                    // Calculate balance change from each row
                    var $balanceCell = $(data[r].balance);
                    var change = parseFloat($balanceCell.data('change')) || 0;
                    final_balance += change;
                }

                $('.footer_total_debit').html(__currency_trans_from_en(footer_total_debit));
                $('.footer_total_credit').html(__currency_trans_from_en(footer_total_credit));
                
                // Display final balance
                var balanceClass = final_balance >= 0 ? 'text-success' : 'text-danger';
                var balanceText = __currency_trans_from_en(Math.abs(final_balance), true) + (final_balance < 0 ? ' (Cr)' : ' (Dr)');
                $('.footer_total_balance').html('<span class="' + balanceClass + '">' + balanceText + '</span>');
            }
        });
        $('#transaction_date_range').on('cancel.daterangepicker', function (ev, picker) {
            $('#transaction_date_range').val('');
            ledger.ajax.reload();
        });
    });
</script>
@stop