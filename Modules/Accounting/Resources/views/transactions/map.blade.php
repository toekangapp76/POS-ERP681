<div class="modal-dialog no-print" role="document">
{!! Form::open(['url' => action([\Modules\Accounting\Http\Controllers\TransactionController::class, 'saveMap']), 'method' => 'POST', 'id' => 'save_accounting_map' ]) !!}
    
    <input type="hidden" name="type" value="{{$type}}" id="transaction_type">
    @if(in_array($type, ['sell', 'purchase', 'expense', 'gym_subscription']))
        <input type="hidden" name="id" value="{{$transaction->id}}">
    @elseif(in_array($type, ['sell_payment', 'purchase_payment']))
        <input type="hidden" name="id" value="{{$transaction_payment->id}}">
    @endif

<div class="modal-content">
    <div class="modal-header">
    <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    <h4 class="modal-title" id="modalTitle">
        @if($type == 'sell')
            {{$transaction->invoice_no}}
        @elseif(in_array($type, ['sell_payment', 'purchase_payment']))
            {{$transaction_payment->payment_ref_no}}
        @elseif(in_array($type, ['purchase', 'expense', 'gym_subscription']))
            {{$transaction->ref_no}}
        @endif
    </h4>
</div>
<div class="modal-body">
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('payment_account', __('accounting::lang.payment_account') . ':*' ) !!}
                {!! Form::select('payment_account', !is_null($default_payment_account) ? [$default_payment_account->id => $default_payment_account->name] : [], $default_payment_account->id ?? null, ['class' => 'form-control accounts-dropdown','placeholder' => __('accounting::lang.payment_account'), 'required' => 'required']); !!}
                @if($type == 'gym_subscription')
                    <small class="text-muted">@lang('accounting::lang.bank_debit')</small>
                @elseif($type == 'purchase')
                    <small class="text-muted">@lang('accounting::lang.purchase_debit')</small>
                @endif
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('deposit_to', __('accounting::lang.deposit_to') . ':*' ) !!}
                {!! Form::select('deposit_to', !is_null($default_deposit_to) ? 
                    [$default_deposit_to->id => $default_deposit_to->name] : [], $default_deposit_to->id ?? null, ['class' => 'form-control accounts-dropdown','placeholder' => __('accounting::lang.deposit_to'), 'required' => 'required']); !!}
                @if($type == 'gym_subscription')
                    <small class="text-muted">@lang('accounting::lang.revenue_credit')</small>
                @elseif($type == 'purchase')
                    <small class="text-muted">@lang('accounting::lang.payable_credit')</small>
                @endif
            </div>
        </div>
    </div>

    @if($type == 'gym_subscription')
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('ppn_account', __('accounting::lang.ppn_account') . ':' ) !!}
                {!! Form::select('ppn_account', !is_null($default_ppn_account ?? null) ? 
                    [$default_ppn_account->id => $default_ppn_account->name] : [], $default_ppn_account->id ?? null, ['class' => 'form-control accounts-dropdown','placeholder' => __('accounting::lang.ppn_account')]); !!}
                <small class="text-muted">@lang('accounting::lang.tax_credit_10_percent')</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert alert-info" style="margin-top: 25px;">
                <small>
                    <strong>@lang('accounting::lang.calculation_formula'):</strong><br>
                    @lang('accounting::lang.deposit_formula'): <code>@lang('accounting::lang.bank') / 1.1</code><br>
                    @lang('accounting::lang.ppn_formula'): <code>10% × @lang('accounting::lang.deposit')</code>
                </small>
            </div>
        </div>
    </div>
    @endif

    @if($type == 'purchase')
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('ppn_account', __('accounting::lang.ppn_account') . ':' ) !!}
                {!! Form::select('ppn_account', !is_null($default_ppn_account ?? null) ? 
                    [$default_ppn_account->id => $default_ppn_account->name] : [], $default_ppn_account->id ?? null, ['class' => 'form-control accounts-dropdown','placeholder' => __('accounting::lang.ppn_account')]); !!}
                <small class="text-muted">@lang('accounting::lang.tax_debit')</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('discount_account', __('accounting::lang.discount_account') . ':' ) !!}
                {!! Form::select('discount_account', !is_null($default_discount_account ?? null) ? 
                    [$default_discount_account->id => $default_discount_account->name] : [], $default_discount_account->id ?? null, ['class' => 'form-control accounts-dropdown','placeholder' => __('accounting::lang.discount_account')]); !!}
                <small class="text-muted">@lang('accounting::lang.discount_credit')</small>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-info">
                <small>
                    <strong>@lang('accounting::lang.purchase_calculation'):</strong><br>
                    @lang('accounting::lang.purchase_total'): <code>Purchase + Tax - Discount</code><br>
                    @lang('accounting::lang.net_payable'): <code>Accounts Payable (Credit)</code>
                </small>
            </div>
        </div>
    </div>
    @endif

</div>

<div class="modal-footer">
    <button type="submit" class="btn btn-primary">@lang('messages.update')</button>
    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
</div>

{!! Form::close() !!}
	</div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->