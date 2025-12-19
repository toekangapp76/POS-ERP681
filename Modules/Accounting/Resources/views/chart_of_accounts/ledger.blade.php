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
                                        <!-- <th>@lang( 'lang_v1.balance' )</th> -->
                                        <th>@lang('messages.action')</th>
                                    </tr>
                                </thead>



                                <tfoot>
                                    <tr class="bg-gray font-17 footer-total text-center">
                                        <td colspan="5"><strong>@lang('sale.total'):</strong></td>
                                        <td class="footer_total_debit"></td>
                                        <td class="footer_total_credit"></td>
                                        <td></td>
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
                type: 'GET',
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

        // Account Book
        ledger = $('#ledger').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '{{action([\Modules\Accounting\Http\Controllers\CoaController::class, 'ledger'], [$account->id])}}',
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
                //{data: 'balance', name: 'balance', searchable: false},
                { data: 'action', name: 'action', searchable: false }
            ],
            "fnDrawCallback": function (oSettings) {
                __currency_convert_recursively($('#ledger'));
            },
            "footerCallback": function (row, data, start, end, display) {
                var footer_total_debit = 0;
                var footer_total_credit = 0;

                for (var r in data) {
                    footer_total_debit += $(data[r].debit).data('orig-value') ? parseFloat($(data[r].debit).data('orig-value')) : 0;
                    footer_total_credit += $(data[r].credit).data('orig-value') ? parseFloat($(data[r].credit).data('orig-value')) : 0;
                }

                $('.footer_total_debit').html(__currency_trans_from_en(footer_total_debit));
                $('.footer_total_credit').html(__currency_trans_from_en(footer_total_credit));
            }
        });
        $('#transaction_date_range').on('cancel.daterangepicker', function (ev, picker) {
            $('#transaction_date_range').val('');
            ledger.ajax.reload();
        });
    });
</script>
@stop