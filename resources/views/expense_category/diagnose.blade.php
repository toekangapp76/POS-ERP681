@extends('layouts.app')
@section('title', __('expense.expense_categories') . ' - Diagnosa P&L Bisnis')

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
            <i class="fas fa-stethoscope"></i> Diagnosa Expense Category → P&L Bisnis
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">Verifikasi integrasi P&L
                Group</small>
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
            <strong>Panduan:</strong> Halaman ini membantu Anda memverifikasi bahwa setiap Expense Category sudah memiliki
            P&L Group yang benar.
            <ul class="tw-mt-2">
                <li><span class="label label-success">✓ Benar</span> = P&L Group sudah di-set, expense akan ter-grouping di
                    P&L Bisnis</li>
                <li><span class="label label-danger">✗ Belum</span> = P&L Group belum di-set, expense akan masuk ke "Other"
                </li>
            </ul>
        </div>

        <!-- Summary Statistics -->
        <div class="row tw-mb-4">
            <div class="col-md-3">
                <div class="info-box bg-aqua">
                    <span class="info-box-icon"><i class="fas fa-folder"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Kategori</span>
                        <span class="info-box-number">{{ $summary['total_categories'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box bg-green">
                    <span class="info-box-icon"><i class="fas fa-check"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Konfigurasi Benar</span>
                        <span class="info-box-number">{{ $summary['configured'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box bg-red">
                    <span class="info-box-icon"><i class="fas fa-times"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Belum Dikonfigurasi</span>
                        <span class="info-box-number">{{ $summary['not_configured'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box bg-purple">
                    <span class="info-box-icon"><i class="fas fa-tags"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">P&L Groups</span>
                        <span class="info-box-number">{{ count($summary['pnl_groups']) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th width="5%">Status</th>
                        <th width="10%">Code</th>
                        <th width="20%">Expense Category</th>
                        <th width="15%">P&L Group</th>
                        <th width="25%">Default Account (Opsional)</th>
                        <th width="25%">Diagnosa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($diagnostics as $diag)
                        <tr class="{{ $diag['status'] == 'success' ? 'success' : 'danger' }}">
                            <td class="text-center">
                                @if($diag['status'] == 'success')
                                    <span class="label label-success"><i class="fas fa-check"></i></span>
                                @else
                                    <span class="label label-danger"><i class="fas fa-times"></i></span>
                                @endif
                            </td>
                            <td><code>{{ $diag['category']->code ?? '-' }}</code></td>
                            <td><strong>{{ $diag['category']->name }}</strong></td>
                            <td>
                                @if($diag['pnl_group'])
                                    <span class="label label-info">{{ $diag['pnl_group'] }}</span>
                                @else
                                    <span class="text-muted">Belum di-set</span>
                                @endif
                            </td>
                            <td>
                                @if($diag['account_info'])
                                    <code>{{ $diag['account_info']['gl_code'] }}</code> - {{ $diag['account_info']['name'] }}
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

        <!-- Available P&L Groups -->
        <div class="box box-info tw-mt-4">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-tags"></i> P&L Groups yang Tersedia</h3>
            </div>
            <div class="box-body">
                <p class="text-muted">Ini adalah daftar P&L Group yang saat ini digunakan di Expense Categories:</p>
                <div class="tw-flex tw-flex-wrap tw-gap-2">
                    @if(count($summary['pnl_groups']) > 0)
                        @foreach($summary['pnl_groups'] as $group)
                            <span class="label label-primary" style="font-size: 14px;">{{ $group }}</span>
                        @endforeach
                    @else
                        <span class="text-muted">Belum ada P&L Group yang dikonfigurasi.</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- How It Works -->
        <div class="box box-success tw-mt-4">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fas fa-lightbulb"></i> Bagaimana Ini Bekerja?</h3>
            </div>
            <div class="box-body">
                <ol>
                    <li><strong>P&L Group</strong> pada Expense Category menentukan di kolom mana expense akan muncul di
                        report P&L Bisnis.</li>
                    <li>Ketika membuat transaksi expense, pilih <strong>Expense Category</strong> yang sesuai.</li>
                    <li>Expense akan otomatis ter-grouping di P&L Bisnis berdasarkan <strong>P&L Group</strong> dari
                        kategori tersebut.</li>
                    <li>Jika P&L Group belum di-set, expense akan masuk ke kategori <strong>"Other"</strong>.</li>
                </ol>
                <div class="alert alert-warning tw-mt-2">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Penting:</strong> Pastikan semua Expense Category yang aktif sudah memiliki P&L Group yang
                    benar!
                </div>
            </div>
        </div>

        @endcomponent

        <div class="modal fade expense_category_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        </div>

    </section>
    <!-- /.content -->

@endsection