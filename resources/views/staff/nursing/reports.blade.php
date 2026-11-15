<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Perawat | TechnoMedic</title>
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
                <a class="staff-navigation-link" href="{{ route('staff.nursing.assessment-queue.index') }}"><span class="staff-navigation-icon">◷</span>Kondisi pasien</a>
                <a class="staff-navigation-link" href="{{ route('staff.nursing.inpatients.index') }}"><span class="staff-navigation-icon">＋</span>Pasien rawat inap</a>
                <a class="staff-navigation-link is-active" href="{{ route('staff.nursing.reports.index') }}" aria-current="page"><span class="staff-navigation-icon">▤</span>Laporan perawat</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>

        <main class="staff-main">
            <header class="staff-topbar">
                <div><span class="staff-topbar-kicker">MODUL PERAWAT</span><span class="staff-topbar-role">Laporan perawat</span></div>
                <span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">ARSIP CATATAN KLINIS</p><h1>Laporan perawat</h1><p>Riwayat kondisi pasien rawat jalan, pemantauan rawat inap, dan tindakan perawat, termasuk pasien yang sudah checkout.</p></div>
                </section>

                <section class="staff-panel">
                    <form class="staff-search-form staff-filter-form" action="{{ route('staff.nursing.reports.index') }}" method="get">
                        <div class="staff-filter-field"><label for="report-search">Cari pasien atau catatan</label><input id="report-search" name="search" type="search" value="{{ $search }}" placeholder="Nama, nomor rekam medis, tindakan, atau catatan"></div>
                        <div class="staff-filter-field"><label for="report-from-date">Dari tanggal</label><input id="report-from-date" name="from_date" type="date" value="{{ $fromDate }}"></div>
                        <div class="staff-filter-field"><label for="report-to-date">Sampai tanggal</label><input id="report-to-date" name="to_date" type="date" value="{{ $toDate }}"></div>
                        <button class="staff-secondary-button" type="submit">Tampilkan</button>
                        @if ($search !== '' || $fromDate !== null || $toDate !== null)<a class="staff-text-link" href="{{ route('staff.nursing.reports.index') }}">Reset filter</a>@endif
                    </form>

                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Waktu</th><th>Pasien</th><th>Jenis catatan</th><th>Catatan tindakan / kondisi</th><th>Tanda vital</th><th>Dicatat oleh</th></tr></thead>
                            <tbody>
                                @forelse ($reports as $report)
                                    <tr>
                                        <td>{{ $report->created_at ? \Illuminate\Support\Carbon::parse($report->created_at)->locale('id')->translatedFormat('d F Y, H:i') : '—' }}</td>
                                        <td><strong class="staff-patient-name">{{ $report->patient_name }}</strong><span class="staff-booking-code">{{ $report->medical_record_number ?: 'Nomor rekam medis belum tersedia' }}</span></td>
                                        <td>{{ $report->record_type }}@if ($report->update_status)<br><span class="staff-booking-code">{{ $report->update_status }}</span>@endif</td>
                                        <td>@if ($report->treatment_name)<strong>{{ $report->treatment_name }}</strong><br>@endif{{ $report->notes ?: '—' }}</td>
                                        <td>
                                            @if ($report->temperature_celsius !== null)Suhu {{ number_format((float) $report->temperature_celsius, 1) }} °C<br>@endif
                                            @if ($report->blood_pressure)TD {{ $report->blood_pressure }} mmHg<br>@endif
                                            @if ($report->pulse_rate !== null)Nadi {{ $report->pulse_rate }} x/menit<br>@endif
                                            @if ($report->respiratory_rate !== null)Napas {{ $report->respiratory_rate }} x/menit<br>@endif
                                            @if ($report->oxygen_saturation !== null)SpO₂ {{ $report->oxygen_saturation }}%@endif
                                            @if ($report->temperature_celsius === null && !$report->blood_pressure && $report->pulse_rate === null && $report->respiratory_rate === null && $report->oxygen_saturation === null)—@endif
                                        </td>
                                        <td>{{ $report->recorded_by ?: 'Perawat' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="staff-table-empty">Belum ada laporan perawat yang sesuai dengan filter.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($reports->hasPages())<div class="staff-pagination">{{ $reports->links() }}</div>@endif
                </section>
            </div>
        </main>
    </div>
</body>
</html>
