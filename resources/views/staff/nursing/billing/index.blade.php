<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Tagihan | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>
            <div class="staff-sidebar-user">
                <span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span>
                <span class="staff-user-copy"><strong>{{ auth()->user()->email }}</strong><span>Perawat</span></span>
            </div>
            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
                <span class="staff-navigation-label staff-navigation-spaced">MODUL PERAWAT</span>
                <a class="staff-navigation-link" href="{{ route('staff.nursing.inpatients.index') }}"><span class="staff-navigation-icon">＋</span>Pasien rawat inap</a>
                <a class="staff-navigation-link" href="{{ route('staff.nursing.assessment-queue.index') }}"><span class="staff-navigation-icon">◷</span>Antrean pemeriksaan awal</a>
                <a class="staff-navigation-link is-active" href="{{ route('staff.nursing.billing.index') }}" aria-current="page"><span class="staff-navigation-icon">▣</span>Tagihan</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>

        <main class="staff-main">
            <header class="staff-topbar">
                <div><span class="staff-topbar-kicker">MODUL PERAWAT</span><span class="staff-topbar-role">Transaksi tagihan pasien</span></div>
                <span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">INFORMASI BACA SAJA</p><h1>Transaksi tagihan pasien</h1><p>Lihat seluruh tagihan pasien, rincian biaya, dan riwayat transaksi. Perawat tidak dapat mengubah tagihan atau memproses pembayaran.</p></div>
                </section>

                <section class="staff-panel">
                    <form class="staff-search-form staff-billing-search" action="{{ route('staff.nursing.billing.index') }}" method="get">
                        <label class="staff-sr-only" for="billing-search">Cari tagihan atau pasien</label>
                        <input id="billing-search" name="search" type="search" value="{{ $search }}" placeholder="Cari nomor tagihan, nama, atau nomor rekam medis">
                        <label class="staff-sr-only" for="billing-status">Status pembayaran</label>
                        <select id="billing-status" name="status">
                            <option value="">Semua status</option>
                            <option value="Unpaid" @selected($status === 'Unpaid')>Belum dibayar</option>
                            <option value="Partially Paid" @selected($status === 'Partially Paid')>Dibayar sebagian</option>
                            <option value="Paid" @selected($status === 'Paid')>Lunas</option>
                            <option value="Cancelled" @selected($status === 'Cancelled')>Dibatalkan</option>
                        </select>
                        <button class="staff-secondary-button" type="submit">Terapkan</button>
                        @if ($search !== '' || $status !== '')<a class="staff-text-link" href="{{ route('staff.nursing.billing.index') }}">Reset</a>@endif
                    </form>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>No. tagihan</th><th>Pasien</th><th>Tanggal dibuat</th><th>Total</th><th>Terbayar</th><th>Sisa</th><th>Status</th><th>Rincian</th></tr></thead>
                            <tbody>
                                @forelse ($bills as $bill)
                                    @php
                                        [$statusLabel, $statusClass] = match ($bill->status) {
                                            'Unpaid' => ['Belum dibayar', 'is-unpaid'],
                                            'Partially Paid' => ['Dibayar sebagian', 'is-partial'],
                                            'Paid' => ['Lunas', 'is-paid'],
                                            'Cancelled' => ['Dibatalkan', 'is-cancelled'],
                                            default => [$bill->status, ''],
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $bill->bill_number }}</td>
                                        <td><strong class="staff-patient-name">{{ $bill->patient_name }}</strong><span class="staff-booking-code">{{ $bill->medical_record_number ?: 'No. RM belum tersedia' }}</span></td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($bill->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}</td>
                                        <td>Rp {{ number_format($bill->total_amount, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($bill->amount_paid, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($bill->status === 'Cancelled' ? 0 : $bill->total_amount - $bill->amount_paid, 0, ',', '.') }}</td>
                                        <td><span class="staff-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                        <td><a class="staff-small-action staff-button-link" href="{{ route('staff.nursing.billing.show', $bill->id) }}">Lihat transaksi</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="staff-table-empty">Belum ada transaksi tagihan yang sesuai.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($bills->hasPages())<div class="staff-pagination">{{ $bills->links() }}</div>@endif
                </section>
                <p class="staff-data-note">Halaman ini hanya untuk melihat informasi transaksi. Pengelolaan nominal dan pembayaran dilakukan petugas yang berwenang.</p>
            </div>
        </main>
    </div>
</body>
</html>
