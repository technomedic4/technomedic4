<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pasien Rujukan | TechnoMedic</title>
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
                <a class="staff-navigation-link is-active" href="{{ route('staff.doctor.referrals.index') }}" aria-current="page"><span class="staff-navigation-icon">↗</span>Pasien rujukan</a>
                <a class="staff-navigation-link" href="{{ route('staff.medical-records.index') }}"><span class="staff-navigation-icon">＋</span>Rekam medis</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.teleconsultations.index') }}"><span class="staff-navigation-icon">✉</span>Telekonsultasi</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL DOKTER</span><span class="staff-topbar-role">Pasien rujukan</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">RUJUKAN MASUK</p><h1>Pasien rujukan</h1><p>Daftar pasien yang dirujuk ke jadwal praktik Anda beserta informasi klinisnya.</p></div>
                </section>
                <section class="staff-panel">
                    <form class="staff-search-form staff-filter-form" action="{{ route('staff.doctor.referrals.index') }}" method="get">
                        <div class="staff-filter-field"><label for="referral-search">Nama pasien</label><input id="referral-search" name="search" type="search" value="{{ $search }}" maxlength="100" placeholder="Cari nama pasien"></div>
                        <button class="staff-secondary-button" type="submit">Cari</button>
                    </form>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Tanggal & antrean</th><th>Pasien</th><th>Dokter pengirim</th><th>Alasan rujukan</th><th>Hasil pemeriksaan</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($appointments as $appointment)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($appointment->appointment_date)->locale('id')->translatedFormat('d F Y') }}<span class="staff-booking-code">Antrean {{ str_pad((string) $appointment->queue_number, 3, '0', STR_PAD_LEFT) }} · {{ substr($appointment->start_time, 0, 5) }}</span></td>
                                        <td><strong class="staff-patient-name">{{ $appointment->patient_name }}</strong><span class="staff-booking-code">{{ $appointment->medical_record_number ?: $appointment->booking_code }}</span></td>
                                        <td>{{ $appointment->referring_doctor_name }}<span class="staff-booking-code">{{ $appointment->referring_department_name }}</span></td>
                                        <td class="staff-complaint-cell">{{ $appointment->referral_reason }}</td>
                                        <td class="staff-complaint-cell"><strong>Diagnosis:</strong> {{ $appointment->diagnosis }}<br><strong>Temuan:</strong> {{ $appointment->examination_findings }}</td>
                                        <td><span class="staff-status {{ in_array($appointment->status, ['Pending', 'Confirmed'], true) ? 'is-pending' : ($appointment->status === 'Completed' ? 'is-completed' : 'is-arrived') }}">{{ $appointment->status }}</span></td>
                                        <td><a class="staff-inline-action" href="{{ route('staff.doctor.appointments.show', $appointment->id) }}">Lihat rujukan</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="staff-table-empty">Belum ada pasien yang dirujuk ke jadwal Anda.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($appointments->hasPages())<div class="staff-pagination">{{ $appointments->links() }}</div>@endif
                </section>
            </div>
        </main>
    </div>
</body>
</html>
