<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Laboratorium | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>
            <div class="staff-sidebar-user"><span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span><span class="staff-user-copy"><strong>{{ auth()->user()->email }}</strong><span>Dokter</span></span></div>
            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
                <span class="staff-navigation-label staff-navigation-spaced">MODUL DOKTER</span>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.appointments.index') }}"><span class="staff-navigation-icon">◷</span>Jadwal & pasien</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.referrals.index') }}"><span class="staff-navigation-icon">↗</span>Pasien rujukan</a>
                <a class="staff-navigation-link is-active" href="{{ route('staff.doctor.laboratory-results.index') }}" aria-current="page"><span class="staff-navigation-icon">▤</span>Hasil lab</a>
                <a class="staff-navigation-link" href="{{ route('staff.medical-records.index') }}"><span class="staff-navigation-icon">＋</span>Rekam medis</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL DOKTER</span><span class="staff-topbar-role">Hasil laboratorium</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading"><div><p class="staff-eyebrow">PENUNJANG PEMERIKSAAN</p><h1>Hasil laboratorium</h1><p>Permintaan pemeriksaan Anda dan hasil yang dikirim kembali oleh laboratorium.</p></div></section>
                <section class="staff-panel">
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Tanggal</th><th>Pasien</th><th>Pemeriksaan</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($labOrders as $order)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($order->created_at)->locale('id')->translatedFormat('d F Y') }}<span class="staff-booking-code">{{ $order->booking_code ?? '' }}</span></td>
                                        <td><strong class="staff-patient-name">{{ $order->patient_name }}</strong><span class="staff-booking-code">{{ $order->medical_record_number ?: 'No. RM belum tersedia' }}</span></td>
                                        <td class="staff-complaint-cell staff-pre-line">{{ $order->requested_tests }}</td>
                                        <td><span class="staff-status {{ $order->status === 'Completed' ? 'is-completed' : 'is-pending' }}">{{ $order->status === 'Completed' ? 'Hasil tersedia' : 'Menunggu hasil' }}</span></td>
                                        <td><a class="staff-inline-action" href="{{ route('staff.doctor.laboratory-results.show', $order->id) }}">{{ $order->status === 'Completed' ? 'Buka surat hasil' : 'Lihat status' }}</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="staff-table-empty">Belum ada rujukan laboratorium dari kunjungan Anda.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($labOrders->hasPages())<div class="staff-pagination">{{ $labOrders->links() }}</div>@endif
                </section>
            </div>
        </main>
    </div>
</body>
</html>
