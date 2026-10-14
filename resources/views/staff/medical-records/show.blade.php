<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Rekam Medis | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>
            <div class="staff-sidebar-user">
                <span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span>
                <span class="staff-user-copy">
                    <strong>{{ auth()->user()->email }}</strong>
                    <span>{{ auth()->user()->role_id ? \App\Support\HospitalStaffRole::tryFrom(DB::table('roles')->where('id', auth()->user()->role_id)->value('role_name'))?->value ?? 'Staf' : 'Staf' }}</span>
                </span>
            </div>
            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
                <a class="staff-navigation-link is-active" href="{{ route('staff.medical-records.index') }}" aria-current="page"><span class="staff-navigation-icon">＋</span>Rekam medis & laporan</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>

        <main class="staff-main">
            <header class="staff-topbar">
                <div><span class="staff-topbar-kicker">CATATAN KLINIS</span><span class="staff-topbar-role">Detail rekam medis</span></div>
                <span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div>
                        <p class="staff-eyebrow">IDENTITAS PASIEN</p>
                        <h1>{{ $patient->full_name }}</h1>
                        <p>{{ $patient->medical_record_number ?: 'Nomor rekam medis belum tersedia' }}</p>
                    </div>
                    <a class="staff-secondary-button staff-button-link" href="{{ route('staff.medical-records.index') }}">Kembali ke daftar pasien</a>
                </section>

                <section class="staff-panel staff-encounter-summary">
                    <div class="staff-panel-heading"><div><h2>Profil pasien</h2></div></div>
                    <div class="staff-encounter-grid">
                        <div><span class="staff-stat-label">NO. RM</span><strong>{{ $patient->medical_record_number ?: 'Belum tersedia' }}</strong></div>
                        <div><span class="staff-stat-label">NIK</span><strong>{{ $patient->national_id ?: 'Belum tercatat' }}</strong></div>
                        <div><span class="staff-stat-label">JENIS KELAMIN</span><strong>{{ $patient->gender === 'L' ? 'Laki-laki' : ($patient->gender === 'P' ? 'Perempuan' : 'Belum dicatat') }}</strong></div>
                        <div><span class="staff-stat-label">TANGGAL LAHIR</span><strong>{{ $patient->birth_date ? \Illuminate\Support\Carbon::parse($patient->birth_date)->locale('id')->translatedFormat('d F Y') : 'Belum dicatat' }}</strong></div>
                        <div><span class="staff-stat-label">TELEPON</span><strong>{{ $patient->phone_number ?: 'Belum dicatat' }}</strong></div>
                        <div><span class="staff-stat-label">ALAMAT</span><strong>{{ $patient->address ?: 'Belum dicatat' }}</strong></div>
                    </div>
                </section>

                <section class="staff-panel">
                    <div class="staff-panel-heading"><div><h2>Riwayat kunjungan</h2><p>Catatan hasil pemeriksaan dan terapi yang tersimpan dalam sistem.</p></div></div>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Dokter & unit</th>
                                    <th>Jenis konsultasi</th>
                                    <th>Keluhan</th>
                                    <th>Diagnosis</th>
                                    <th>Rencana terapi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($records as $record)
                                    @php
                                        $consultationDate = $record->appointment_date ? \Illuminate\Support\Carbon::parse($record->appointment_date)->locale('id')->translatedFormat('d F Y') : '—';
                                    @endphp
                                    <tr>
                                        <td>{{ $consultationDate }}</td>
                                        <td><strong class="staff-patient-name">{{ $record->doctor_name }}</strong><span class="staff-booking-code">{{ $record->department_name }}</span></td>
                                        <td>{{ $record->consultation_type ?: 'In-Person' }}</td>
                                        <td>{{ $record->initial_complaint ?: 'Belum ada keluhan' }}</td>
                                        <td>{{ $record->diagnosis ?: 'Belum ada diagnosis' }}</td>
                                        <td>{{ $record->treatment_plan ?: 'Belum ada rencana terapi' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="staff-table-empty">Belum ada riwayat kunjungan untuk pasien ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
