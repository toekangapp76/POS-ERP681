<div class="modal-dialog modal-xl no-print" role="document">
  <div class="modal-content">
    <div class="modal-header">
      <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 class="modal-title" id="modalTitle"> @lang('gym::lang.subscription_details') (<b>@lang('sale.invoice_no'):</b> {{ $sell->invoice_no }})</h4>
    </div>
    <div class="modal-body">
      <div class="row">
        <div class="col-xs-12">
          <p class="pull-right"><b>@lang('messages.date'):</b> {{ @format_date($sell->transaction_date) }}</p>
        </div>
      </div>
      <div class="row">
        <div class="col-sm-4">
          <b>{{ __('sale.invoice_no') }}:</b> #{{ $sell->invoice_no }}<br>
          <b>{{ __('sale.status') }}:</b> 
          @if($sell->status == 'final')
            <span class="label label-success">{{ __('sale.final') }}</span>
          @elseif($sell->status == 'draft')
            <span class="label label-warning">{{ __('sale.draft') }}</span>
          @else
            {{ ucfirst($sell->status) }}
          @endif
          <br>
          <b>{{ __('sale.payment_status') }}:</b> 
          @if(!empty($sell->payment_status))
            @if($sell->payment_status == 'paid')
              <span class="label label-success">{{ __('lang_v1.paid') }}</span>
            @elseif($sell->payment_status == 'partial')
              <span class="label label-warning">{{ __('lang_v1.partial') }}</span>
            @else
              <span class="label label-danger">{{ __('lang_v1.due') }}</span>
            @endif
          @endif
          <br>
          <b>{{ __('lang_v1.type') }}:</b> 
          <span class="label label-info">{{ __('gym::lang.gym_subscription') }}</span>
        </div>
        <div class="col-sm-4">
          @if(!empty($sell->contact))
            <b>{{ __('gym::lang.member') }}:</b> {{ $sell->contact->name }}<br>
            @if($sell->contact->mobile)
              <b>{{ __('contact.mobile') }}:</b> {{ $sell->contact->mobile }}<br>
            @endif
            @if($sell->contact->email)
              <b>{{ __('business.email') }}:</b> {{ $sell->contact->email }}<br>
            @endif
          @endif
        </div>
        <div class="col-sm-4">
          @if(!empty($sell->location))
            <b>{{ __('business.business_location') }}:</b> {{ $sell->location->name }}<br>
          @endif
          @if(!empty($sell->created_by_user))
            <b>{{ __('lang_v1.added_by') }}:</b> {{ $sell->created_by_user->user_full_name }}<br>
          @endif
        </div>
      </div>

      <br>
      
      {{-- Gym Package Details --}}
      <div class="row">
        <div class="col-sm-12">
          <h4>@lang('gym::lang.package_details'):</h4>
        </div>
        <div class="col-sm-12">
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead>
                <tr class="bg-light-blue">
                  <th>@lang('gym::lang.package')</th>
                  <th>@lang('gym::lang.category')</th>
                  <th>@lang('gym::lang.duration')</th>
                  <th>@lang('gym::lang.start_date')</th>
                  <th>@lang('gym::lang.end_date')</th>
                  <th class="text-right">@lang('sale.total_amount')</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>{{ $sell->gym_package->name ?? '--' }}</td>
                  <td>{{ $sell->gym_package->gymCategory->name ?? '--' }}</td>
                  <td>
                    @if(!empty($sell->gym_package))
                      {{ $sell->gym_package->duration_value }} {{ __('gym::lang.' . ($sell->gym_package->duration_type ?? 'day')) }}
                    @else
                      --
                    @endif
                  </td>
                  <td>{{ @format_date($sell->subscription_start_date ?? $sell->transaction_date) }}</td>
                  <td>{{ @format_date($sell->subscription_end_date ?? null) }}</td>
                  <td class="text-right">
                    <span class="display_currency" data-currency_symbol="true">{{ $sell->final_total }}</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Payment Info --}}
      <div class="row">
        @php
          $total_paid = 0;
        @endphp
        <div class="col-sm-12">
          <h4>{{ __('sale.payment_info') }}:</h4>
        </div>
        <div class="col-md-6 col-sm-12">
          <div class="table-responsive">
            <table class="table bg-gray">
              <tr class="bg-green">
                <th>#</th>
                <th>{{ __('messages.date') }}</th>
                <th>{{ __('purchase.ref_no') }}</th>
                <th>{{ __('sale.amount') }}</th>
                <th>{{ __('sale.payment_mode') }}</th>
                <th>{{ __('sale.payment_note') }}</th>
              </tr>
              @forelse($sell->payment_lines as $payment_line)
                @php
                  if($payment_line->is_return == 1){
                    $total_paid -= $payment_line->amount;
                  } else {
                    $total_paid += $payment_line->amount;
                  }
                @endphp
                <tr>
                  <td>{{ $loop->iteration }}</td>
                  <td>{{ @format_date($payment_line->paid_on) }}</td>
                  <td>{{ $payment_line->payment_ref_no }}</td>
                  <td><span class="display_currency" data-currency_symbol="true">{{ $payment_line->amount }}</span></td>
                  <td>
                    {{ $payment_types[$payment_line->method] ?? $payment_line->method }}
                    @if($payment_line->is_return == 1)
                      <br/>
                      ( {{ __('lang_v1.change_return') }} )
                    @endif
                  </td>
                  <td>{{ $payment_line->note ?? '--' }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-center">@lang('lang_v1.no_payments')</td>
                </tr>
              @endforelse
            </table>
          </div>
        </div>
        <div class="col-md-6 col-sm-12">
          <div class="table-responsive">
            <table class="table bg-gray">
              <tr>
                <th>{{ __('sale.total') }}: </th>
                <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->total_before_tax }}</span></td>
              </tr>
              @if($sell->discount_amount > 0)
              <tr>
                <th>{{ __('sale.discount') }} <b>(-)</b>:</th>
                <td>
                  <div class="pull-right">
                    <span class="display_currency" @if($sell->discount_type == 'fixed') data-currency_symbol="true" @endif>{{ $sell->discount_amount }}</span>
                    @if($sell->discount_type == 'percentage') {{ '%' }} @endif
                  </div>
                </td>
              </tr>
              @endif
              @if($sell->tax_amount > 0)
              <tr>
                <th>{{ __('sale.tax') }} <b>(+)</b>:</th>
                <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->tax_amount }}</span></td>
              </tr>
              @endif
              <tr>
                <th>{{ __('sale.total_payable') }}: </th>
                <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->final_total }}</span></td>
              </tr>
              <tr>
                <th>{{ __('sale.total_paid') }}:</th>
                <td><span class="display_currency pull-right" data-currency_symbol="true">{{ $total_paid }}</span></td>
              </tr>
              <tr>
                <th>{{ __('sale.total_remaining') }}:</th>
                <td>
                  @php
                    $total_paid = (string) $total_paid;
                  @endphp
                  <span class="display_currency pull-right" data-currency_symbol="true">{{ $sell->final_total - $total_paid }}</span>
                </td>
              </tr>
            </table>
          </div>
        </div>
      </div>

      {{-- Notes --}}
      <div class="row">
        <div class="col-sm-6">
          <strong>{{ __('sale.sell_note') }}:</strong><br>
          <p class="well well-sm no-shadow bg-gray">
            @if($sell->additional_notes)
              {!! nl2br($sell->additional_notes) !!}
            @else
              --
            @endif
          </p>
        </div>
        <div class="col-sm-6">
          <strong>{{ __('sale.staff_note') }}:</strong><br>
          <p class="well well-sm no-shadow bg-gray">
            @if($sell->staff_note)
              {!! nl2br($sell->staff_note) !!}
            @else
              --
            @endif
          </p>
        </div>
      </div>

      {{-- Activities --}}
      <div class="row">
        <div class="col-md-12">
          <strong>{{ __('lang_v1.activities') }}:</strong><br>
          @includeIf('activity_log.activities', ['activity_type' => 'sell'])
        </div>
      </div>
    </div>
    <div class="modal-footer">
      @can('print_invoice')
        <a href="#" class="print-invoice tw-dw-btn tw-dw-btn-primary tw-text-white" data-href="{{route('sell.printInvoice', [$sell->id])}}"><i class="fa fa-print" aria-hidden="true"></i> @lang("lang_v1.print_invoice")</a>
      @endcan
      <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white no-print" data-dismiss="modal">@lang('messages.close')</button>
    </div>
  </div>
</div>

<script type="text/javascript">
  $(document).ready(function(){
    var element = $('div.modal-xl');
    __currency_convert_recursively(element);
  });
</script>
