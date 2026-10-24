<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal & Pasien | TechnoMedic</title>
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
                <a class="staff-navigation-link is-active" href="{{ route('staff.doctor.appointments.index') }}" aria-current="page"><span class="staff-navigation-icon">◷</span>Jadwal & pasien</a>
                <a class="staff-navigation-link" href="{{ route('staff.medical-records.index') }}"><span class="staff-navigation-icon">＋</span>Rekam medis</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.teleconsultations.index') }}"><span class="staff-navigation-icon">✉</span>Telekonsultasi</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL DOKTER</span><span class="staff-topbar-role">Jadwal & pasien</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">PRAKTIK RAWAT JALAN</p><h1>Jadwal & pasien</h1><p>Daftar pasien hanya mencakup jadwal praktik Anda.</p></div>
                </section>

                @if (session('success'))
                    <div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel">
                    <form class="staff-search-form staff-filter-form" action="{{ route('staff.doctor.appointments.index') }}" method="get">
                        <div class="staff-filter-field"><label for="appointment-date">Tanggal praktik</label><input id="appointment-date" name="date" type="date" value="{{ $date }}"></div>
                        <button class="staff-secondary-button" type="submit">Tampilkan</button>
                        @if ($date !== now()->toDateString())
                            <a class="staff-text-link" href="{{ route('staff.doctor.appointments.index') }}">Kembali ke hari ini</a>
                        @endif
                    </form>
                    <div class="staff-table-wrap">
                        <table class="staff-table staff-doctor-appointments-table">
                            <thead><tr><th>Antrean</th><th>Waktu</th><th>Pasien</th><th>Keluhan awal</th><th>Status</th><th>Tindakan</th></tr></thead>
                            <tbody>
                                @forelse ($appointments as $appointment)
                                    @php
                                        [$statusLabel, $statusClass] = match ($appointment->status) {
                                            'Pending' => ['Menunggu check-in', 'is-pending'],
                                            'Confirmed' => ['Dikonfirmasi', 'is-confirmed'],
                                            'Checked-In' => $appointment->nursing_assessment_id !== null
                                                ? ['Siap diperiksa', 'is-arrived']
                                                : ['Menunggu pemeriksaan perawat', 'is-confirmed'],
                                            'Completed' => ['Selesai', 'is-completed'],
                                            'Cancelled' => ['Dibatalkan', 'is-cancelled'],
                                            default => [$appointment->status, ''],
                                        };
                                    @endphp
                                    <tr>
                                        <td><span class="staff-queue-number">{{ str_pad((string) $appointment->queue_number, 3, '0', STR_PAD_LEFT) }}</span><span class="staff-booking-code">{{ $appointment->booking_code }}</span></td>
                                        <td>{{ substr($appointment->start_time, 0, 5) }}</td>
                                        <td><strong class="staff-patient-name">{{ $appointment->patient_name }}</strong><span class="staff-booking-code">{{ $appointment->medical_record_number ?: 'Nomor rekam medis belum tersedia' }}</span></td>
                                        <td class="staff-complaint-cell">{{ $appointment->initial_complaint ?: 'Belum ada keluhan yang dicatat.' }}</td>
                                        <td><span class="staff-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                        <td>
                                            @if ($appointment->status === 'Completed' || ($appointment->status === 'Checked-In' && $appointment->nursing_assessment_id !== null))
                                                <a class="staff-inline-action" href="{{ route('staff.doctor.appointments.show', $appointment->id) }}">{{ $appointment->status === 'Completed' ? 'Lihat catatan' : 'Mulai pemeriksaan' }}</a>
                                            @elseif ($appointment->status === 'Checked-In')
                                                <span class="staff-booking-code">Menunggu pemeriksaan awal perawat</span>
                                            @else
                                                <span class="staff-booking-code">Menunggu check-in pasien</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="staff-table-empty">Belum ada pasien terdaftar pada tanggal ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                <p class="staff-data-note">Pemeriksaan dokter tersedia setelah pasien check-in dan pemeriksaan awal perawat tersimpan. Menyimpan konsultasi akan menandai kunjungan selesai.</p>
            </div>
        </main>
    </div>
</body>
</html>
