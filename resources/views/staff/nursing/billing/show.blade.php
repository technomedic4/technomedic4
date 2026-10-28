<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi {{ $bill->bill_number }} | TechnoMedic</title>
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
                <div><span class="staff-topbar-kicker">MODUL PERAWAT</span><span class="staff-topbar-role">Rincian transaksi tagihan</span></div>
                <span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </header>
            <div class="staff-content">
                @php
                    [$statusLabel, $statusClass] = match ($bill->status) {
                        'Unpaid' => ['Belum dibayar', 'is-unpaid'],
                        'Partially Paid' => ['Dibayar sebagian', 'is-partial'],
                        'Paid' => ['Lunas', 'is-paid'],
                        'Cancelled' => ['Dibatalkan', 'is-cancelled'],
                        default => [$bill->status, ''],
                    };
                @endphp
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">INFORMASI BACA SAJA · {{ $bill->bill_number }}</p><h1>{{ $bill->patient_name }}</h1><p>{{ $bill->medical_record_number ?: 'Nomor rekam medis belum tersedia' }}</p></div>
                    <a class="staff-secondary-button staff-button-link" href="{{ route('staff.nursing.billing.index') }}">Kembali ke semua tagihan</a>
                </section>

                <section class="staff-panel staff-billing-panel">
                    <div class="staff-panel-heading">
                        <div><h2>Ringkasan tagihan</h2><p>Dibuat {{ \Illuminate\Support\Carbon::parse($bill->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}</p></div>
                        <span class="staff-status {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Item transaksi</th><th>Jumlah</th><th>Harga satuan</th><th>Subtotal</th><th>Bukti tindakan</th></tr></thead>
                            <tbody>
                                @forelse ($items as $item)
                                    <tr>
                                        <td>{{ $item->item_name }}</td>
                                        <td>{{ number_format($item->quantity) }}</td>
                                        <td>Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($item->quantity * $item->unit_price, 0, ',', '.') }}</td>
                                        <td>
                                            @if ($item->treatment_recorded_by)
                                                {{ $item->treatment_recorded_by }}<br>{{ $item->treatment_notes ?: 'Tanpa catatan tambahan' }}
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="staff-table-empty">Belum ada rincian item untuk tagihan ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="staff-billing-totals">
                        <p><span>Total tagihan</span><strong>Rp {{ number_format($bill->total_amount, 0, ',', '.') }}</strong></p>
                        <p><span>Sudah dibayar</span><strong>Rp {{ number_format($bill->amount_paid, 0, ',', '.') }}</strong></p>
                        <p class="staff-billing-balance"><span>Sisa tagihan</span><strong>Rp {{ number_format($bill->balance_due, 0, ',', '.') }}</strong></p>
                    </div>
                </section>

                <section class="staff-panel">
                    <div class="staff-panel-heading"><div><h2>Riwayat pembayaran</h2><p>Transaksi pembayaran yang sudah dicatat petugas.</p></div></div>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Waktu</th><th>Jumlah</th><th>Metode</th><th>Referensi</th><th>Dicatat oleh</th></tr></thead>
                            <tbody>
                                @forelse ($payments as $payment)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($payment->paid_at)->locale('id')->translatedFormat('d F Y, H:i') }}</td>
                                        <td>Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                                        <td>{{ $payment->payment_method }}</td>
                                        <td>{{ $payment->reference ?: '—' }}</td>
                                        <td>{{ $payment->recorded_by ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="staff-table-empty">Belum ada pembayaran tercatat.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="staff-panel">
                    <div class="staff-panel-heading"><div><h2>Pengajuan pembayaran pasien</h2><p>Status bukti pembayaran yang dikirim dari aplikasi pasien.</p></div></div>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Waktu pengajuan</th><th>Pasien</th><th>Jumlah</th><th>Metode</th><th>Referensi</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse ($paymentSubmissions as $submission)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($submission->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}</td>
                                        <td>{{ $submission->submitted_by ?: '—' }}</td>
                                        <td>Rp {{ number_format($submission->amount, 0, ',', '.') }}</td>
                                        <td>{{ $submission->payment_method }}</td>
                                        <td>{{ $submission->reference ?: '—' }}</td>
                                        <td>{{ match ($submission->status) { 'Pending' => 'Menunggu pemeriksaan', 'Approved' => 'Disetujui', 'Rejected' => 'Ditolak', default => $submission->status } }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="staff-table-empty">Belum ada pengajuan pembayaran pasien.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                <p class="staff-data-note">Halaman ini hanya menampilkan data. Perawat tidak dapat mengubah nominal, tagihan, atau status pembayaran.</p>
            </div>
        </main>
    </div>
</body>
</html>
