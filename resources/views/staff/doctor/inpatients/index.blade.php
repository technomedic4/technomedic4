<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Rawat Inap | TechnoMedic</title>
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
                <a class="staff-navigation-link is-active" href="{{ route('staff.doctor.inpatients.index') }}" aria-current="page"><span class="staff-navigation-icon">＋</span>Monitoring rawat inap</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.referrals.index') }}"><span class="staff-navigation-icon">↗</span>Pasien rujukan</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.laboratory-results.index') }}"><span class="staff-navigation-icon">▤</span>Hasil lab</a>
                <a class="staff-navigation-link" href="{{ route('staff.medical-records.index') }}"><span class="staff-navigation-icon">▤</span>Rekam medis</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.teleconsultations.index') }}"><span class="staff-navigation-icon">✉</span>Telekonsultasi</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL DOKTER</span><span class="staff-topbar-role">Monitoring rawat inap</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">EVALUASI PASIEN</p><h1>Monitoring rawat inap</h1><p>Pasien rawat inap yang menjadi tanggung jawab Anda. Tinjau pemantauan perawat dan perbarui rencana perawatan.</p></div>
                </section>

                <section class="staff-panel">
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Pasien</th><th>Kamar / tempat tidur</th><th>Waktu masuk</th><th>Diagnosis</th><th>Status kepulangan</th><th>Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($admissions as $admission)
                                    <tr>
                                        <td><strong class="staff-patient-name">{{ $admission->patient_name }}</strong><span class="staff-booking-code">{{ $admission->medical_record_number ?: $admission->booking_code }}</span></td>
                                        <td>{{ $admission->room_number ?: 'Belum ditentukan' }} / {{ $admission->bed_number ?: '—' }}</td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($admission->admitted_at)->locale('id')->translatedFormat('d F Y, H:i') }}</td>
                                        <td class="staff-complaint-cell">{{ \Illuminate\Support\Str::limit($admission->diagnosis, 120) }}</td>
                                        <td>
                                            @if ($admission->doctor_discharge_approved_at)
                                                <span class="staff-status is-confirmed">Kepulangan divalidasi</span>
                                            @else
                                                <span class="staff-status is-arrived">Masih dalam perawatan</span>
                                            @endif
                                        </td>
                                        <td><a class="staff-inline-action" href="{{ route('staff.doctor.inpatients.show', $admission->id) }}">Evaluasi pasien</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="staff-table-empty">Tidak ada pasien rawat inap aktif yang menjadi tanggung jawab Anda.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($admissions->hasPages())<div class="staff-pagination">{{ $admissions->links() }}</div>@endif
                </section>
                <p class="staff-data-note">Pasien baru dapat menjalani proses checkout administratif setelah dokter memvalidasi kepulangannya.</p>
            </div>
        </main>
    </div>
</body>
</html>
