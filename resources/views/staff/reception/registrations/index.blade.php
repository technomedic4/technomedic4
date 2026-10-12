<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran & Antrean | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>
            <div class="staff-sidebar-user"><span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span><span class="staff-user-copy"><strong>{{ auth()->user()->email }}</strong><span>Resepsionis</span></span></div>
            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
                <span class="staff-navigation-label staff-navigation-spaced">MODUL RESEPSIONIS</span>
                <a class="staff-navigation-link" href="{{ route('staff.reception.patients.index') }}"><span class="staff-navigation-icon">▤</span>Data pasien</a>
                <a class="staff-navigation-link is-active" href="{{ route('staff.reception.registrations.index') }}" aria-current="page"><span class="staff-navigation-icon">◷</span>Pendaftaran & antrean</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL RESEPSIONIS</span><span class="staff-topbar-role">Pendaftaran & antrean</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">LOKET RAWAT JALAN</p><h1>Pendaftaran & antrean</h1><p>Kelola kunjungan pasien berdasarkan tanggal dan tindak lanjuti check-in.</p></div>
                    <a class="staff-action-button" href="{{ route('staff.reception.patients.index') }}">+ Daftarkan pasien</a>
                </section>
                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif
                <section class="staff-panel">
                    <form class="staff-search-form staff-filter-form" action="{{ route('staff.reception.registrations.index') }}" method="get">
                        <div class="staff-filter-field"><label for="queue-date">Tanggal kunjungan</label><input id="queue-date" name="date" type="date" value="{{ $date }}"></div>
                        <div class="staff-filter-field"><label for="queue-status">Status</label><select id="queue-status" name="status"><option value="">Semua status</option><option value="Pending" @selected($status === 'Pending')>Menunggu</option><option value="Confirmed" @selected($status === 'Confirmed')>Dikonfirmasi</option><option value="Checked-In" @selected($status === 'Checked-In')>Check-in</option><option value="Completed" @selected($status === 'Completed')>Selesai</option><option value="Cancelled" @selected($status === 'Cancelled')>Dibatalkan</option></select></div>
                        <button class="staff-secondary-button" type="submit">Tampilkan</button>
                    </form>
                    <div class="staff-table-wrap">
                        <table class="staff-table staff-registration-table">
                            <thead><tr><th>No. antrean</th><th>Pasien</th><th>Poli & dokter</th><th>Jam praktik</th><th>Jenis kunjungan</th><th>Status</th><th>Aksi loket</th></tr></thead>
                            <tbody>
                                @forelse ($appointments as $appointment)
                                    @php
                                        [$statusLabel, $statusClass] = match ($appointment->status) {
                                            'Pending' => ['Menunggu', 'is-pending'],
                                            'Confirmed' => ['Dikonfirmasi', 'is-confirmed'],
                                            'Checked-In' => ['Check-in', 'is-arrived'],
                                            'Completed' => ['Selesai', 'is-completed'],
                                            'Cancelled' => ['Dibatalkan', 'is-cancelled'],
                                            default => [$appointment->status, ''],
                                        };
                                    @endphp
                                    <tr>
                                        <td><span class="staff-queue-number">{{ str_pad((string) $appointment->queue_number, 3, '0', STR_PAD_LEFT) }}</span></td>
                                        <td><strong class="staff-patient-name">{{ $appointment->patient_name }}</strong><span class="staff-booking-code">{{ $appointment->medical_record_number ?: $appointment->booking_code }}</span></td>
                                        <td><strong class="staff-patient-name">{{ $appointment->department_name }}</strong><span class="staff-booking-code">{{ $appointment->doctor_name }}</span></td>
                                        <td>{{ substr($appointment->start_time, 0, 5) }}</td>
                                        <td>{{ $appointment->consultation_type === 'Teleconsultation' ? 'Telekonsultasi' : 'Tatap muka' }}</td>
                                        <td><span class="staff-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                        <td>
                                            @if (in_array($appointment->status, ['Pending', 'Confirmed'], true))
                                                <div class="staff-row-actions">
                                                    <form action="{{ route('staff.reception.registrations.check-in', $appointment->id) }}" method="post">@csrf<button class="staff-small-action" type="submit">Check-in</button></form>
                                                    <form action="{{ route('staff.reception.registrations.cancel', $appointment->id) }}" method="post">@csrf<button class="staff-small-action is-danger" type="submit">Batal</button></form>
                                                </div>
                                            @else
                                                <span class="staff-booking-code">Tidak ada tindakan</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="staff-table-empty">Belum ada pendaftaran pada tanggal ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($appointments->hasPages())<div class="staff-pagination">{{ $appointments->links() }}</div>@endif
                </section>
                <p class="staff-data-note">Nomor antrean dibuat per jadwal dokter dan tanggal kunjungan. Pembatalan dan check-in hanya tersedia untuk pendaftaran yang belum selesai.</p>
            </div>
        </main>
    </div>
</body>
</html>
