<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telekonsultasi | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ $roleName === 'Dokter' ? route('staff.dashboard') : route('patient.teleconsultations.index') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">{{ $roleName === 'Dokter' ? 'PORTAL DOKTER' : 'LAYANAN PASIEN' }}</p>
            <div class="staff-sidebar-user">
                <span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span>
                <span class="staff-user-copy"><strong>{{ auth()->user()->email }}</strong><span>{{ $roleName }}</span></span>
            </div>
            <nav class="staff-navigation" aria-label="Navigasi telekonsultasi">
                <span class="staff-navigation-label">TELEKONSULTASI</span>
                @if ($roleName === 'Dokter')
                    <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard staf</a>
                    <a class="staff-navigation-link is-active" href="{{ route('staff.doctor.teleconsultations.index') }}" aria-current="page"><span class="staff-navigation-icon">✉</span>Ruang chat</a>
                    <a class="staff-navigation-link" href="{{ route('staff.doctor.appointments.index') }}"><span class="staff-navigation-icon">◷</span>Jadwal & pasien</a>
                @else
                    <a class="staff-navigation-link is-active" href="{{ route('patient.teleconsultations.index') }}" aria-current="page"><span class="staff-navigation-icon">✉</span>Ruang chat</a>
                @endif
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">LAYANAN KESEHATAN</span><span class="staff-topbar-role">Telekonsultasi</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">KONSULTASI JARAK JAUH</p><h1>{{ $roleName === 'Dokter' ? 'Ruang telekonsultasi' : 'Chat dengan dokter' }}</h1><p>{{ $roleName === 'Dokter' ? 'Percakapan hanya menampilkan pasien telekonsultasi pada jadwal Anda.' : 'Pilih kunjungan telekonsultasi untuk menghubungi dokter Anda.' }}</p></div>
                </section>
                <section class="staff-panel">
                    <div class="staff-panel-heading"><div><h2>{{ $roleName === 'Dokter' ? 'Daftar percakapan pasien' : 'Kunjungan telekonsultasi Anda' }}</h2><p>Riwayat pesan tersimpan pada kunjungan terkait.</p></div></div>
                    <div class="staff-table-wrap">
                        <table class="staff-table staff-teleconsult-table">
                            <thead><tr><th>Tanggal / antrean</th><th>{{ $roleName === 'Dokter' ? 'Pasien' : 'Dokter' }}</th><th>Poli</th><th>Pesan terakhir</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($appointments as $appointment)
                                    @php
                                        [$statusLabel, $statusClass] = match ($appointment->status) {
                                            'Pending' => ['Menunggu', 'is-pending'],
                                            'Confirmed' => ['Dikonfirmasi', 'is-confirmed'],
                                            'Checked-In' => ['Berlangsung', 'is-arrived'],
                                            'Completed' => ['Selesai', 'is-completed'],
                                            'Cancelled' => ['Dibatalkan', 'is-cancelled'],
                                            default => [$appointment->status, ''],
                                        };
                                        $chatRoute = $routePrefix.'.show';
                                    @endphp
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($appointment->appointment_date)->locale('id')->translatedFormat('d M Y') }}<span class="staff-booking-code">Antrean {{ str_pad((string) $appointment->queue_number, 3, '0', STR_PAD_LEFT) }}</span></td>
                                        <td><strong class="staff-patient-name">{{ $roleName === 'Dokter' ? $appointment->patient_name : $appointment->doctor_name }}</strong><span class="staff-booking-code">{{ $appointment->booking_code }}</span></td>
                                        <td>{{ $appointment->department_name }}</td>
                                        <td class="staff-complaint-cell">{{ $appointment->latest_message ?: 'Belum ada pesan' }}</td>
                                        <td><span class="staff-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                        <td><a class="staff-inline-action" href="{{ route($chatRoute, $appointment->id) }}">Buka chat</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="staff-table-empty">{{ $roleName === 'Dokter' ? 'Belum ada kunjungan telekonsultasi pada jadwal Anda.' : 'Belum ada kunjungan telekonsultasi yang tertaut ke akun Anda.' }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                @if ($roleName === 'Pasien')
                    <p class="staff-data-note">Jika jadwal Anda tidak tampil, hubungi resepsionis untuk memastikan kunjungan ditandai telekonsultasi dan akun pasien terhubung ke profil Anda.</p>
                @else
                    <p class="staff-data-note">Pesan bersifat teks dan tersimpan sebagai bagian dari riwayat telekonsultasi. Jangan membagikan kredensial akun.</p>
                @endif
            </div>
        </main>
    </div>
</body>
</html>
