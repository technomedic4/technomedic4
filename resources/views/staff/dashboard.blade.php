<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>

            <div class="staff-sidebar-user">
                <span class="staff-user-avatar">{{ mb_strtoupper(mb_substr($userName, 0, 1)) }}</span>
                <span class="staff-user-copy">
                    <strong>{{ $userName }}</strong>
                    <span>{{ $roleLabel }}</span>
                </span>
            </div>

            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link is-active" href="{{ route('staff.dashboard') }}" aria-current="page">
                    <span class="staff-navigation-icon">⌂</span>
                    Dashboard
                </a>
                <span class="staff-navigation-label staff-navigation-spaced">MODUL OPERASIONAL</span>
                @if (in_array($roleLabel, ['Resepsionis', 'Admin'], true))
                    <a class="staff-navigation-link" href="{{ route('staff.reception.patients.index') }}">
                        <span class="staff-navigation-icon">▤</span>
                        Data pasien
                    </a>
                    <a class="staff-navigation-link" href="{{ route('staff.reception.registrations.index') }}">
                        <span class="staff-navigation-icon">◷</span>
                        Pendaftaran & antrean
                    </a>
                @elseif ($roleLabel === \App\Support\HospitalStaffRole::Doctor->value)
                    <a class="staff-navigation-link" href="{{ route('staff.doctor.appointments.index') }}">
                        <span class="staff-navigation-icon">◷</span>
                        Jadwal & pasien
                    </a>
                    <a class="staff-navigation-link" href="{{ route('staff.doctor.teleconsultations.index') }}">
                        <span class="staff-navigation-icon">✉</span>
                        Telekonsultasi
                    </a>
                @else
                    <span class="staff-navigation-link is-disabled" aria-disabled="true">
                    <span class="staff-navigation-icon">▤</span>
                    Pasien & pendaftaran
                    <span class="staff-soon">Tahap berikutnya</span>
                </span>
                    <span class="staff-navigation-link is-disabled" aria-disabled="true">
                        <span class="staff-navigation-icon">◷</span>
                        Antrean
                        <span class="staff-soon">Tahap berikutnya</span>
                    </span>
                @endif
                @if ($roleLabel === 'Resepsionis')
                    <a class="staff-navigation-link" href="{{ route('staff.management.doctors.index') }}">
                        <span class="staff-navigation-icon">＋</span>
                        Manajemen dokter
                    </a>
                @endif
                <span class="staff-navigation-link is-disabled" aria-disabled="true">
                <span class="staff-navigation-link is-disabled" aria-disabled="true">
                    <span class="staff-navigation-icon">＋</span>
                    Rekam medis & laporan
                    <span class="staff-soon">Tahap berikutnya</span>
                </span>
                <span class="staff-navigation-link is-disabled" aria-disabled="true">
                    <span class="staff-navigation-icon">▣</span>
                    Tagihan
                    <span class="staff-soon">Tahap berikutnya</span>
                </span>
            </nav>

            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">
                @csrf
                <button class="staff-logout-button" type="submit">Keluar dari portal</button>
            </form>
        </aside>

        <main class="staff-main">
            <header class="staff-topbar">
                <div>
                    <span class="staff-topbar-kicker">PORTAL STAF</span>
                    <span class="staff-topbar-role">{{ $roleLabel }}</span>
                </div>
                <span class="staff-topbar-date">{{ $dateLabel }}</span>
            </header>

            <div class="staff-content">
                <section class="staff-page-heading">
                    <div>
                        <p class="staff-eyebrow">RINGKASAN OPERASIONAL</p>
                        <h1>Selamat datang, {{ $userName }}</h1>
                        <p>Pantau aktivitas rumah sakit hari ini dari satu tempat.</p>
                    </div>
                    @if ($roleLabel === \App\Support\HospitalStaffRole::Doctor->value)
                        <div class="staff-heading-actions">
                            <a class="staff-secondary-button staff-button-link" href="{{ route('staff.doctor.appointments.index') }}">Buka jadwal pasien</a>
                            <a class="staff-action-button staff-button-link" href="{{ route('staff.doctor.teleconsultations.index') }}">Telekonsultasi</a>
                        </div>
                    @endif
                </section>

                @if ($unavailableModule)
                    <section class="staff-alert staff-alert-info" role="status">
                        <strong>Informasi modul</strong>
                        <p>{{ $unavailableModule }}</p>
                        <p>Dashboard keuangan akan diaktifkan setelah skema tagihan disiapkan.</p>
                    </section>
                @else
                    <section class="staff-stat-grid" aria-label="Ringkasan kunjungan hari ini">
                        @foreach ($stats as $stat)
                            <article class="staff-stat-card">
                                <span class="staff-stat-label">{{ $stat['label'] }}</span>
                                <strong class="staff-stat-value">{{ number_format($stat['value']) }}</strong>
                                <span class="staff-stat-description">{{ $stat['description'] }}</span>
                            </article>
                        @endforeach
                    </section>

                    @if ($roleLabel === \App\Support\HospitalStaffRole::Nurse->value)
                        <section class="staff-panel">
                            <div class="staff-panel-heading">
                                <div>
                                    <h2>Pembaruan perawatan terbaru</h2>
                                    <p>Catatan yang tersimpan pada sistem rumah sakit.</p>
                                </div>
                            </div>
                            @if ($careUpdates->isEmpty())
                                <div class="staff-empty-state">Belum ada pembaruan perawatan.</div>
                            @else
                                <div class="staff-table-wrap">
                                    <table class="staff-table">
                                        <thead>
                                            <tr>
                                                <th>Pasien</th>
                                                <th>Status</th>
                                                <th>Catatan</th>
                                                <th>Waktu</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($careUpdates as $update)
                                                <tr>
                                                    <td class="staff-patient-name">{{ $update->patient_name }}</td>
                                                    <td>{{ $update->update_status }}</td>
                                                    <td>{{ $update->notes ?: '—' }}</td>
                                                    <td>{{ $update->created_at }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </section>
                    @else
                        <section class="staff-panel">
                            <div class="staff-panel-heading">
                                <div>
                                    <h2>Pendaftaran hari ini</h2>
                                    <p>Data aktual berdasarkan jadwal dan status pendaftaran.</p>
                                </div>
                                <span class="staff-live-indicator"><span></span> Data database</span>
                            </div>
                            @if ($appointments->isEmpty())
                                <div class="staff-empty-state">Belum ada pendaftaran untuk jadwal hari ini.</div>
                            @else
                                <div class="staff-table-wrap">
                                    <table class="staff-table">
                                        <thead>
                                            <tr>
                                                <th>No. antrean</th>
                                                <th>Pasien</th>
                                                <th>Poli</th>
                                                @if ($roleLabel !== \App\Support\HospitalStaffRole::Doctor->value)
                                                    <th>Dokter</th>
                                                @endif
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($appointments as $appointment)
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
                                                    <td><span class="staff-queue-number">{{ $appointment->queue_number }}</span></td>
                                                    <td>
                                                        <strong class="staff-patient-name">{{ $appointment->patient_name }}</strong>
                                                        <span class="staff-booking-code">{{ $appointment->booking_code }}</span>
                                                    </td>
                                                    <td>{{ $appointment->department_name }}</td>
                                                    @if ($roleLabel !== \App\Support\HospitalStaffRole::Doctor->value)
                                                        <td>{{ $appointment->doctor_name }}</td>
                                                    @endif
                                                    <td><span class="staff-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </section>
                    @endif
                @endif
                <p class="staff-data-note">Informasi pasien hanya ditampilkan sesuai hak akses akun staf.</p>
            </div>
        </main>
    </div>
</body>
</html>
