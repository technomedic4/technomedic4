<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagihan | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        @include('staff.billing.partials.sidebar', ['activePage' => 'index'])
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL KEUANGAN</span><span class="staff-topbar-role">Tagihan pasien</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">BILLING & PEMBAYARAN</p><h1>Tagihan pasien</h1><p>Kelola rincian biaya dan catat pembayaran pasien.</p></div>
                    <a class="staff-action-button staff-button-link" href="{{ route('staff.billing.create') }}">+ Buat tagihan</a>
                </section>

                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel">
                    <form class="staff-search-form staff-billing-search" action="{{ route('staff.billing.index') }}" method="get">
                        <label class="staff-sr-only" for="billing-search">Cari tagihan atau pasien</label>
                        <input id="billing-search" name="search" type="search" value="{{ $search }}" placeholder="Cari nomor tagihan, nama, atau nomor rekam medis">
                        <label class="staff-sr-only" for="billing-status">Status pembayaran</label>
                        <select id="billing-status" name="status">
                            <option value="">Semua status</option>
                            @foreach ($statuses as $paymentStatus)
                                <option value="{{ $paymentStatus }}" @selected($status === $paymentStatus)>{{ match ($paymentStatus) { 'Unpaid' => 'Belum dibayar', 'Partially Paid' => 'Dibayar sebagian', 'Paid' => 'Lunas', 'Cancelled' => 'Dibatalkan' } }}</option>
                            @endforeach
                        </select>
                        <button class="staff-secondary-button" type="submit">Terapkan</button>
                        @if ($search !== '' || $status !== '')<a class="staff-text-link" href="{{ route('staff.billing.index') }}">Reset</a>@endif
                    </form>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>No. tagihan</th><th>Pasien</th><th>Tanggal dibuat</th><th>Total</th><th>Sisa tagihan</th><th>Status</th><th>Aksi</th></tr></thead>
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
                                        <td><a class="staff-inline-action" href="{{ route('staff.billing.show', $bill->id) }}">{{ $bill->bill_number }}</a></td>
                                        <td><strong class="staff-patient-name">{{ $bill->patient_name }}</strong><span class="staff-booking-code">{{ $bill->medical_record_number ?: 'No. RM belum tersedia' }}</span></td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($bill->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}</td>
                                        <td>Rp {{ number_format($bill->total_amount, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($bill->status === 'Cancelled' ? 0 : $bill->total_amount - $bill->amount_paid, 0, ',', '.') }}</td>
                                        <td><span class="staff-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                        <td><a class="staff-small-action staff-button-link" href="{{ route('staff.billing.show', $bill->id) }}">Detail</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="staff-table-empty">Belum ada tagihan yang sesuai.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($bills->hasPages())<div class="staff-pagination">{{ $bills->links() }}</div>@endif
                </section>
                <p class="staff-data-note">Tagihan yang telah memiliki pembayaran tidak dapat dihapus agar riwayat transaksi tetap terjaga.</p>
            </div>
        </main>
    </div>
</body>
</html>
