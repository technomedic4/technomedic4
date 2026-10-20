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
                    <a class="staff-navigation-link" href="{{ route('staff.inpatient-admissions.index') }}">
                        <span class="staff-navigation-icon">＋</span>
                        Rawat inap
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
                @elseif ($roleLabel === \App\Support\HospitalStaffRole::Nurse->value)
                    <a class="staff-navigation-link" href="{{ route('staff.nursing.inpatients.index') }}">
                        <span class="staff-navigation-icon">＋</span>
                        Pasien rawat inap
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
                @if (in_array($roleLabel, ['Resepsionis', 'Admin'], true))
                    <a class="staff-navigation-link" href="{{ route('staff.management.nurses.index') }}">
                        <span class="staff-navigation-icon">＋</span>
                        Manajemen perawat
                    </a>
                @endif
                @if (in_array($roleLabel, ['Resepsionis', 'Admin', \App\Support\HospitalStaffRole::Doctor->value], true))
                    <a class="staff-navigation-link" href="{{ route('staff.medical-records.index') }}">
                        <span class="staff-navigation-icon">＋</span>
                        Rekam medis & laporan
                    </a>
                @else
                    <span class="staff-navigation-link is-disabled" aria-disabled="true">
                        <span class="staff-navigation-icon">＋</span>
                        Rekam medis & laporan
                        <span class="staff-soon">Tahap berikutnya</span>
                    </span>
                @endif
                @if (in_array($roleLabel, ['Resepsionis', 'Keuangan', 'Admin'], true))
                    <a class="staff-navigation-link" href="{{ route('staff.billing.index') }}">
                        <span class="staff-navigation-icon">▣</span>
                        Tagihan
                    </a>
                @else
                    <span class="staff-navigation-link is-disabled" aria-disabled="true">
                        <span class="staff-navigation-icon">▣</span>
                        Tagihan
                        <span class="staff-soon">Tahap berikutnya</span>
                    </span>
                @endif
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
                    @elseif ($roleLabel === \App\Support\HospitalStaffRole::Nurse->value)
                        <div class="staff-heading-actions">
                            <a class="staff-action-button staff-button-link" href="{{ route('staff.nursing.inpatients.index') }}">Buka pasien rawat inap</a>
                        </div>
                    @elseif (in_array($roleLabel, ['Resepsionis', 'Keuangan', 'Admin'], true))
                        <div class="staff-heading-actions">
                            <a class="staff-action-button staff-button-link" href="{{ route('staff.billing.create') }}">+ Buat tagihan</a>
                        </div>
                    @endif
                </section>

                <section class="staff-stat-grid" aria-label="{{ $roleLabel === 'Keuangan' ? 'Ringkasan tagihan' : 'Ringkasan kunjungan hari ini' }}">
                    @foreach ($stats as $stat)
                        <article class="staff-stat-card">
                            <span class="staff-stat-label">{{ $stat['label'] }}</span>
                            <strong class="staff-stat-value">{{ $stat['label'] === 'Pembayaran hari ini' ? 'Rp '.number_format($stat['value'], 0, ',', '.') : number_format($stat['value']) }}</strong>
                            <span class="staff-stat-description">{{ $stat['description'] }}</span>
                        </article>
                    @endforeach
                </section>

                    @if ($roleLabel === \App\Support\HospitalStaffRole::Finance->value)
                        <section class="staff-panel">
                            <div class="staff-panel-heading">
                                <div>
                                    <h2>Tagihan terbaru</h2>
                                    <p>Ringkasan tagihan pasien yang terakhir dibuat.</p>
                                </div>
                                <a class="staff-text-link" href="{{ route('staff.billing.index') }}">Lihat semua tagihan</a>
                            </div>
                            <div class="staff-table-wrap">
                                <table class="staff-table">
                                    <thead><tr><th>No. tagihan</th><th>Pasien</th><th>Total</th><th>Sisa</th><th>Status</th></tr></thead>
                                    <tbody>
                                        @forelse ($latestBills as $bill)
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
                                                <td>{{ $bill->patient_name }}</td>
                                                <td>Rp {{ number_format($bill->total_amount, 0, ',', '.') }}</td>
                                                <td>Rp {{ number_format($bill->status === 'Cancelled' ? 0 : $bill->total_amount - $bill->amount_paid, 0, ',', '.') }}</td>
                                                <td><span class="staff-status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" class="staff-table-empty">Belum ada tagihan yang dibuat.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @elseif ($roleLabel === \App\Support\HospitalStaffRole::Nurse->value)
                        <section class="staff-panel">
                            <div class="staff-panel-heading">
                                <div>
                                    <h2>Pembaruan pasien rawat inap</h2>
                                    <p>Daftar ini diperbarui otomatis setiap 8 detik.</p>
                                </div>
                                <span class="staff-live-indicator"><span></span>Realtime</span>
                                <span id="nursing-updates-status" aria-live="polite"></span>
                            </div>
                            <div id="nursing-updates-list">
                                @include('staff.nursing.partials.dashboard-updates', ['careUpdates' => $careUpdates])
                            </div>
                        </section>
                        <script>
                            const updatesList = document.getElementById('nursing-updates-list');
                            const updatesStatus = document.getElementById('nursing-updates-status');

                            window.setInterval(async () => {
                                try {
                                    const response = await fetch(@json(route('staff.nursing.updates')), {
                                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                        credentials: 'same-origin',
                                    });

                                    if (!response.ok) {
                                        throw new Error('Pembaruan tidak dapat dimuat.');
                                    }

                                    updatesList.innerHTML = await response.text();
                                    updatesStatus.textContent = `Diperbarui ${new Date().toLocaleTimeString('id-ID')}`;
                                } catch {
                                    updatesStatus.textContent = 'Pembaruan otomatis gagal. Muat ulang halaman untuk mencoba lagi.';
                                }
                            }, 8000);
                        </script>
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
                <p class="staff-data-note">Informasi pasien hanya ditampilkan sesuai hak akses akun staf.</p>
            </div>
        </main>
    </div>
</body>
</html>
