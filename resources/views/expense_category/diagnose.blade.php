@extends('layouts.app')
@section('title', __('expense.expense_categories') . ' - Diagnosa')

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
            <i class="fas fa-stethoscope"></i> Diagnosa Expense Category Mapping
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">Untuk integrasi P&L Bisnis</small>
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        @component('components.widget', ['class' => 'box-primary'])
        @slot('tool')
        <div class="box-tools">
            <a href="{{ action([\App\Http\Controllers\ExpenseCategoryController::class, 'index']) }}"
                class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-primary">
                <i class="fas fa-arrow-left"></i> Kembali ke Expense Categories
            </a>
        </div>
        @endslot

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Panduan:</strong> Halaman ini membantu Anda mengidentifikasi masalah konfigurasi expense category untuk
            P&L Bisnis.
            <ul class="tw-mt-2">
                <li><span class="label label-success">✓ Benar</span> = Expense category sudah ter-konfigurasi dengan benar
                </li>
                <li><span class="label label-warning">⚠ Warning</span> = Default Account belum di-set</li>
                <li><span class="label label-danger">✗ Error</span> = Akun tidak memiliki Detail Type</li>
            </ul>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th width="5%">Status</th>
                        <th width="15%">Expense Category</th>
                        <th width="10%">Code</th>
                        <th width="25%">Default Expense Account</th>
                        <th width="15%">Detail Type (P&L Bisnis)</th>
                        <th width="30%">Diagnosa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($diagnostics as $diag)
                        <tr
                            class="{{ $diag['status'] == 'success' ? 'success' : ($diag['status'] == 'warning' ? 'warning' : 'danger') }}">
                            <td class="text-center">
                                @if($diag['status'] == 'success')
                                    <span class="label label-success"><i class="fas fa-check"></i></span>
                                @elseif($diag['status'] == 'warning')
                                    <span class="label label-warning"><i class="fas fa-exclamation-triangle"></i></span>
                                @else
                                    <span class="label label-danger"><i class="fas fa-times"></i></span>
                                @endif
                            </td>
                            <td><strong>{{ $diag['category']->name }}</strong></td>
                            <td>{{ $diag['category']->code ?? '-' }}</td>
                            <td>
                                @if($diag['account_info'])
                                    <code>{{ $diag['account_info']['gl_code'] }}</code> - {{ $diag['account_info']['name'] }}
                                @else
                                    <span class="text-muted">Belum di-set</span>
                                @endif
                            </td>
                            <td>
                                @if($diag['detail_type'])
                                    <span class="label label-info">{{ $diag['detail_type'] }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <small>{{ $diag['message'] }}</small>
                                @if($diag['status'] != 'success')
                                    <br>
                                    <a href="{{ action([\App\Http\Controllers\ExpenseCategoryController::class, 'edit'], [$diag['category']->id]) }}"
                                        class="btn btn-xs btn-primary btn-modal" data-container=".expense_category_modal">
                                        <i class="fas fa-edit"></i> Perbaiki
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">
                                <p class="text-muted">Tidak ada expense category yang ditemukan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Summary -->
        <div class="row tw-mt-4">
            <div class="col-md-4">
                <div class="info-box bg-green">
                    <span class="info-box-icon"><i class="fas fa-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Konfigurasi Benar</span>
                        <span
                            class="info-box-number">{{ collect($diagnostics)->where('status', 'success')->count() }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box bg-yellow">
                    <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Perlu Aksi</span>
                        <span
                            class="info-box-number">{{ collect($diagnostics)->where('status', 'warning')->count() }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box bg-red">
                    <span class="info-box-icon"><i class="fas fa-times"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Error</span>
                        <span class="info-box-number">{{ collect($diagnostics)->where('status', 'danger')->count() }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Detail Types -->
        <div class="box box-info tw-mt-4">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-list"></i> Detail Types yang Tersedia (Kategori P&L Bisnis)</h3>
            </div>
            <div class="box-body">
                <p class="text-muted">Ini adalah daftar Detail Type yang dapat digunakan untuk grouping di P&L Bisnis:</p>
                <div class="tw-flex tw-flex-wrap tw-gap-2">
                    @foreach($detail_types as $id => $name)
                        <span class="label label-default">{{ $name }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        @endcomponent

        <div class="modal fade expense_category_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        </div>

    </section>
    <!-- /.content -->

@endsection