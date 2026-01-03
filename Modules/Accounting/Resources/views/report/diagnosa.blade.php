@extends('layouts.app')
@section('title', 'Diagnosa P&L per Kategori Bisnis')

@section('content')
    <section class="content-header">
        <h1>🔍 Diagnosa P&L per Kategori Bisnis
            <small>Tracking flow data dari Detail Type → Account → Transaction → Report</small>
        </h1>
    </section>

    <section class="content">
        {{-- STEP 1: Detail Types --}}
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <span class="badge bg-blue">STEP 1</span>
                    Detail Types (Kategori Bisnis)
                </h3>
                <p class="text-muted">Menu: <strong>Accounting → Settings → Tab "Detail Type"</strong></p>
            </div>
            <div class="box-body">
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    Detail Type adalah kategori bisnis yang digunakan untuk mengelompokkan akun-akun.
                    Setiap Detail Type dengan nama yang sama akan digabungkan menjadi satu kategori di laporan.
                </div>

                <table class="table table-bordered table-striped" id="detail_types_table">
                    <thead class="bg-primary">
                        <tr>
                            <th>ID</th>
                            <th>Nama Detail Type</th>
                            <th>Parent</th>
                            <th>Slug (Key)</th>
                            <th>Jumlah Akun Terhubung</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $grouped_categories = [];
                            foreach ($detail_types as $dt) {
                                $slug = \Illuminate\Support\Str::slug($dt->name, '_');
                                if (!isset($grouped_categories[$slug])) {
                                    $grouped_categories[$slug] = [
                                        'name' => $dt->name,
                                        'ids' => [],
                                        'accounts_count' => 0
                                    ];
                                }
                                $grouped_categories[$slug]['ids'][] = $dt->id;
                                $grouped_categories[$slug]['accounts_count'] += $dt->accounts_count;
                            }
                        @endphp
                        @foreach($detail_types as $dt)
                            <tr>
                                <td><code>{{ $dt->id }}</code></td>
                                <td><strong>{{ $dt->name }}</strong></td>
                                <td>{{ $dt->parent_name ?? '-' }}</td>
                                <td><code>{{ \Illuminate\Support\Str::slug($dt->name, '_') }}</code></td>
                                <td>
                                    <span class="badge {{ $dt->accounts_count > 0 ? 'bg-green' : 'bg-red' }}">
                                        {{ $dt->accounts_count }} akun
                                    </span>
                                </td>
                                <td>
                                    @if($dt->accounts_count > 0)
                                        <span class="text-success"><i class="fa fa-check"></i> OK</span>
                                    @else
                                        <span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Belum ada akun</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <h4 class="text-info"><i class="fa fa-sitemap"></i> Ringkasan Kategori (Setelah Digabung)</h4>
                <table class="table table-bordered">
                    <thead class="bg-info">
                        <tr>
                            <th>Slug/Key</th>
                            <th>Nama Kategori</th>
                            <th>Total IDs Tergabung</th>
                            <th>Total Akun</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grouped_categories as $slug => $cat)
                            <tr>
                                <td><code>{{ $slug }}</code></td>
                                <td><strong>{{ $cat['name'] }}</strong></td>
                                <td>
                                    <small>{{ implode(', ', $cat['ids']) }}</small>
                                    <span class="badge bg-blue">{{ count($cat['ids']) }} IDs</span>
                                </td>
                                <td>
                                    <span class="badge {{ $cat['accounts_count'] > 0 ? 'bg-green' : 'bg-red' }}">
                                        {{ $cat['accounts_count'] }} akun
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- STEP 2: Accounts with Detail Type --}}
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <span class="badge bg-green">STEP 2</span>
                    Akun yang Terhubung ke Detail Type
                </h3>
                <p class="text-muted">Menu: <strong>Accounting → Chart of Accounts → Edit Akun → Pilih Detail Type</strong>
                </p>
            </div>
            <div class="box-body">
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    Setiap akun P&L (GL Code dimulai 4 ke atas) harus memiliki Detail Type agar muncul di laporan per
                    kategori.
                    Akun tanpa Detail Type akan masuk ke kategori "Other".
                </div>

                @if($accounts_with_detail_type->count() > 0)
                    <h4 class="text-success"><i class="fa fa-check-circle"></i> Akun yang SUDAH terhubung ke Detail Type</h4>
                    <table class="table table-bordered table-striped" id="accounts_linked_table">
                        <thead class="bg-success">
                            <tr>
                                <th>GL Code</th>
                                <th>Nama Akun</th>
                                <th>Tipe</th>
                                <th>Detail Type ID</th>
                                <th>Detail Type Name</th>
                                <th>Kategori (Slug)</th>
                                <th>Total Transaksi</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accounts_with_detail_type as $acc)
                                <tr>
                                    <td><code>{{ $acc->gl_code }}</code></td>
                                    <td>{{ $acc->name }}</td>
                                    <td>
                                        <span
                                            class="label {{ $acc->account_primary_type == 'income' ? 'label-success' : 'label-danger' }}">
                                            {{ ucfirst($acc->account_primary_type) }}
                                        </span>
                                    </td>
                                    <td><code>{{ $acc->detail_type_id }}</code></td>
                                    <td><strong>{{ $acc->detail_type_name }}</strong></td>
                                    <td><code>{{ \Illuminate\Support\Str::slug($acc->detail_type_name, '_') }}</code></td>
                                    <td>
                                        <span class="badge {{ $acc->transactions_count > 0 ? 'bg-green' : 'bg-yellow' }}">
                                            {{ $acc->transactions_count }} transaksi
                                        </span>
                                    </td>
                                    <td>
                                        @if($acc->transactions_count > 0)
                                            <span class="text-success"><i class="fa fa-check"></i> Ada data</span>
                                        @else
                                            <span class="text-warning"><i class="fa fa-exclamation-triangle"></i> Belum ada
                                                transaksi</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if($accounts_without_detail_type->count() > 0)
                    <h4 class="text-danger"><i class="fa fa-exclamation-circle"></i> Akun P&L yang BELUM terhubung ke Detail
                        Type (Masuk "Other")</h4>
                    <div class="alert alert-warning">
                        <i class="fa fa-warning"></i>
                        Akun-akun berikut belum memiliki Detail Type. Data dari akun ini akan masuk ke kategori "Other" di
                        laporan.
                        <br><strong>Solusi:</strong> Edit akun di Chart of Accounts dan pilih Detail Type yang sesuai.
                    </div>
                    <table class="table table-bordered table-striped">
                        <thead class="bg-danger">
                            <tr>
                                <th>GL Code</th>
                                <th>Nama Akun</th>
                                <th>Tipe</th>
                                <th>Total Transaksi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accounts_without_detail_type as $acc)
                                <tr>
                                    <td><code>{{ $acc->gl_code }}</code></td>
                                    <td>{{ $acc->name }}</td>
                                    <td>
                                        <span
                                            class="label {{ $acc->account_primary_type == 'income' ? 'label-success' : 'label-danger' }}">
                                            {{ ucfirst($acc->account_primary_type) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $acc->transactions_count > 0 ? 'bg-red' : 'bg-gray' }}">
                                            {{ $acc->transactions_count }} transaksi
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ action([\Modules\Accounting\Http\Controllers\CoaController::class, 'edit'], [$acc->id]) }}"
                                            class="btn btn-xs btn-warning">
                                            <i class="fa fa-edit"></i> Edit & Assign Detail Type
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- STEP 3: Transactions --}}
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <span class="badge bg-yellow">STEP 3</span>
                    Transaksi per Kategori
                </h3>
                <p class="text-muted">Menu: <strong>Accounting → Journal Entry</strong> (untuk membuat transaksi manual)</p>
            </div>
            <div class="box-body">
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i>
                    Transaksi bisa berasal dari: Journal Entry manual, Penjualan, Pembelian, atau modul lainnya.
                    Period yang dihitung: <strong>{{ $start_date }} s/d {{ $end_date }}</strong>
                </div>

                <h4><i class="fa fa-bar-chart"></i> Ringkasan Transaksi per Kategori</h4>
                <table class="table table-bordered">
                    <thead class="bg-warning">
                        <tr>
                            <th>Kategori</th>
                            <th>Total Income</th>
                            <th>Total Expense</th>
                            <th>Net Profit</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($category_summary as $cat_key => $summary)
                            <tr class="{{ $summary['net_profit'] != 0 ? 'success' : '' }}">
                                <td><strong>{{ $summary['name'] }}</strong></td>
                                <td class="text-success">
                                    {{ number_format($summary['income'], 2) }}
                                </td>
                                <td class="text-danger">
                                    {{ number_format($summary['expense'], 2) }}
                                </td>
                                <td class="{{ $summary['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    <strong>{{ number_format($summary['net_profit'], 2) }}</strong>
                                </td>
                                <td>
                                    @if($summary['net_profit'] != 0)
                                        <span class="text-success"><i class="fa fa-check"></i> Ada nilai</span>
                                    @else
                                        <span class="text-muted"><i class="fa fa-minus"></i> Tidak ada nilai</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray">
                        <tr>
                            <th>TOTAL</th>
                            <th class="text-success">{{ number_format($total_income, 2) }}</th>
                            <th class="text-danger">{{ number_format($total_expense, 2) }}</th>
                            <th class="{{ $net_profit >= 0 ? 'text-success' : 'text-danger' }}">
                                <strong>{{ number_format($net_profit, 2) }}</strong>
                            </th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- STEP 4: Flow Guide --}}
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-map-signs"></i> Panduan Langkah-Langkah
                </h3>
            </div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="panel panel-primary">
                            <div class="panel-heading">
                                <h4><span class="badge">1</span> Buat Detail Type</h4>
                            </div>
                            <div class="panel-body">
                                <ol>
                                    <li>Buka <strong>Accounting → Settings</strong></li>
                                    <li>Klik tab <strong>"Detail Type"</strong></li>
                                    <li>Klik tombol <strong>"Add"</strong></li>
                                    <li>Isi nama kategori (misal: "Gym", "Padel")</li>
                                    <li>Simpan</li>
                                </ol>
                                <a href="{{ action([\Modules\Accounting\Http\Controllers\SettingsController::class, 'index']) }}"
                                    class="btn btn-block btn-primary btn-sm">
                                    <i class="fa fa-cog"></i> Ke Settings
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="panel panel-success">
                            <div class="panel-heading">
                                <h4><span class="badge">2</span> Hubungkan Akun</h4>
                            </div>
                            <div class="panel-body">
                                <ol>
                                    <li>Buka <strong>Accounting → Chart of Accounts</strong></li>
                                    <li>Pilih akun income/expense</li>
                                    <li>Klik <strong>"Edit"</strong></li>
                                    <li>Pilih <strong>Detail Type</strong> yang sesuai</li>
                                    <li>Simpan</li>
                                </ol>
                                <a href="{{ action([\Modules\Accounting\Http\Controllers\CoaController::class, 'index']) }}"
                                    class="btn btn-block btn-success btn-sm">
                                    <i class="fa fa-sitemap"></i> Ke Chart of Accounts
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="panel panel-warning">
                            <div class="panel-heading">
                                <h4><span class="badge">3</span> Buat Transaksi</h4>
                            </div>
                            <div class="panel-body">
                                <ol>
                                    <li>Buka <strong>Accounting → Journal Entry</strong></li>
                                    <li>Klik <strong>"Add"</strong></li>
                                    <li>Pilih akun Debit & Credit</li>
                                    <li>Isi nominal</li>
                                    <li>Simpan</li>
                                </ol>
                                <a href="{{ action([\Modules\Accounting\Http\Controllers\JournalEntryController::class, 'index']) }}"
                                    class="btn btn-block btn-warning btn-sm">
                                    <i class="fa fa-book"></i> Ke Journal Entry
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="panel panel-info">
                            <div class="panel-heading">
                                <h4><span class="badge">4</span> Lihat Laporan</h4>
                            </div>
                            <div class="panel-body">
                                <ol>
                                    <li>Buka <strong>Accounting → Reports</strong></li>
                                    <li>Klik <strong>"P&L per Kategori"</strong></li>
                                    <li>Pilih tanggal yang sesuai</li>
                                    <li>Lihat nilai per kategori</li>
                                    <li>Selesai!</li>
                                </ol>
                                <a href="{{ action([\Modules\Accounting\Http\Controllers\ReportController::class, 'pnlBisnis']) }}"
                                    class="btn btn-block btn-info btn-sm">
                                    <i class="fa fa-pie-chart"></i> Ke P&L Bisnis
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Troubleshooting --}}
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">
                    <i class="fa fa-wrench"></i> Troubleshooting - Kenapa Nilai 0?
                </h3>
            </div>
            <div class="box-body">
                <table class="table table-bordered">
                    <thead class="bg-danger">
                        <tr>
                            <th width="30%">Masalah</th>
                            <th width="40%">Penyebab</th>
                            <th width="30%">Solusi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Semua nilai di "Other"</strong></td>
                            <td>Akun belum dihubungkan ke Detail Type yang benar</td>
                            <td>Edit akun di Chart of Accounts → Pilih Detail Type</td>
                        </tr>
                        <tr>
                            <td><strong>Kategori tidak muncul</strong></td>
                            <td>Detail Type belum dibuat di Settings</td>
                            <td>Buat Detail Type baru di Settings → Detail Type tab</td>
                        </tr>
                        <tr>
                            <td><strong>Nilai 0 untuk semua kategori</strong></td>
                            <td>
                                1. Tidak ada transaksi dalam periode tersebut<br>
                                2. Filter tanggal tidak sesuai
                            </td>
                            <td>
                                1. Buat Journal Entry atau transaksi lain<br>
                                2. Ubah filter tanggal di laporan
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Data ada di Journal Entry tapi tidak di laporan</strong></td>
                            <td>Akun yang digunakan di Journal Entry belum punya Detail Type</td>
                            <td>Edit akun tersebut dan assign Detail Type</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection

@section('javascript')
    <script>
        $(document).ready(function () {
            $('#detail_types_table').DataTable({
                pageLength: 25,
                order: [[1, 'asc']]
            });

            $('#accounts_linked_table').DataTable({
                pageLength: 25,
                order: [[0, 'asc']]
            });
        });
    </script>
@endsection