@extends('layouts.app')

@section('title', __('gym::lang.deferred_revenue_schedule'))

@section('content')

    @include('accounting::layouts.nav')


    <section class="content-header">
        <h1>@lang('gym::lang.deferred_revenue_schedule')
            <small>{{ $transaction->contact->name ?? 'N/A' }}</small>
        </h1>
    </section>

    <section class="content">
        {{-- Subscription Info --}}
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-info-circle"></i> Subscription Details</h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <strong>@lang('gym::lang.member'):</strong><br>
                        {{ $transaction->contact->name ?? 'N/A' }}
                    </div>
                    <div class="col-md-3">
                        <strong>@lang('gym::lang.package'):</strong><br>
                        {{ $transaction->gymPackage->name ?? 'N/A' }}
                    </div>
                    <div class="col-md-3">
                        <strong>@lang('gym::lang.start_date'):</strong><br>
                        {{ \Carbon\Carbon::parse($transaction->gym_package_start_date)->format('d/m/Y') }}
                    </div>
                    <div class="col-md-3">
                        <strong>@lang('gym::lang.end_date'):</strong><br>
                        {{ $transaction->gym_package_end_date ? \Carbon\Carbon::parse($transaction->gym_package_end_date)->format('d/m/Y') : 'Lifetime' }}
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-3">
                        <strong>Total (incl. Tax):</strong><br>
                        <span class="text-success">{{ number_format($transaction->final_total, 2, ',', '.') }}</span>
                    </div>
                    @php
                        $taxRate = $transaction->gymPackage->tax_rate ?? 11;
                        $divisor = 1 + ($taxRate / 100);
                        $totalExclTax = $transaction->final_total / $divisor;
                        $taxAmount = $transaction->final_total - $totalExclTax;
                    @endphp
                    <div class="col-md-3">
                        <strong>Total (excl. Tax):</strong><br>
                        {{ number_format($totalExclTax, 2, ',', '.') }}
                    </div>
                    <div class="col-md-3">
                        <strong>Tax Amount ({{ $taxRate }}%):</strong><br>
                        {{ number_format($taxAmount, 2, ',', '.') }}
                    </div>
                    <div class="col-md-3">
                        <strong>Reference:</strong><br>
                        {{ $transaction->ref_no }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary --}}
        @php
            $totalPending = $schedules->where('status', 'pending')->sum('recognition_amount');
            $totalRecognized = $schedules->where('status', 'recognized')->sum('recognition_amount');
        @endphp
        <div class="row">
            <div class="col-md-6">
                <div class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fa fa-clock"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">@lang('gym::lang.pending_recognition')</span>
                        <span class="info-box-number">{{ number_format($totalPending, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-box bg-green">
                    <span class="info-box-icon"><i class="fa fa-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">@lang('gym::lang.recognized')</span>
                        <span class="info-box-number">{{ number_format($totalRecognized, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Schedule Table --}}
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-calendar"></i> Recognition Schedule</h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>@lang('gym::lang.recognition_date')</th>
                            <th>@lang('gym::lang.period_start')</th>
                            <th>@lang('gym::lang.period_end')</th>
                            <th class="text-center">Days in Month</th>
                            <th class="text-center">@lang('gym::lang.active_days')</th>
                            <th class="text-right">Monthly Amount</th>
                            <th class="text-right">@lang('gym::lang.recognition_amount')</th>
                            <th class="text-center">Status</th>
                            <th>Recognized At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schedules as $index => $schedule)
                            <tr
                                class="{{ $schedule->status === 'recognized' ? 'success' : ($schedule->status === 'pending' && $schedule->recognition_date <= now() ? 'warning' : '') }}">
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $schedule->recognition_date->format('d/m/Y') }}</td>
                                <td>{{ $schedule->period_start->format('d/m/Y') }}</td>
                                <td>{{ $schedule->period_end->format('d/m/Y') }}</td>
                                <td class="text-center">{{ $schedule->period_days }}</td>
                                <td class="text-center">{{ $schedule->active_days }}</td>
                                <td class="text-right">{{ number_format($schedule->monthly_amount, 2, ',', '.') }}</td>
                                <td class="text-right">
                                    <strong>{{ number_format($schedule->recognition_amount, 2, ',', '.') }}</strong></td>
                                <td class="text-center">
                                    @if($schedule->status === 'pending')
                                        @if($schedule->recognition_date <= now())
                                            <span class="label label-danger">Due</span>
                                        @else
                                            <span class="label label-warning">Pending</span>
                                        @endif
                                    @elseif($schedule->status === 'recognized')
                                        <span class="label label-success">Recognized</span>
                                    @else
                                        <span class="label label-default">{{ ucfirst($schedule->status) }}</span>
                                    @endif
                                </td>
                                <td>{{ $schedule->recognized_at ? $schedule->recognized_at->format('d/m/Y H:i') : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="info">
                            <th colspan="7" class="text-right">Total:</th>
                            <th class="text-right">{{ number_format($schedules->sum('recognition_amount'), 2, ',', '.') }}
                            </th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="box-footer">
            <a href="{{ route('gym.deferred-revenue.index') }}" class="btn btn-default">
                <i class="fa fa-arrow-left"></i> @lang('messages.back')
            </a>
        </div>
    </section>
@endsection