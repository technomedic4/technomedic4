<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Hasil Laboratorium | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar no-print">
            <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>
            <div class="staff-sidebar-user"><span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span><span class="staff-user-copy"><strong>{{ auth()->user()->email }}</strong><span>Dokter</span></span></div>
            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
                <span class="staff-navigation-label staff-navigation-spaced">MODUL DOKTER</span>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.appointments.index') }}"><span class="staff-navigation-icon">◷</span>Jadwal & pasien</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.laboratory-results.index') }}"><span class="staff-navigation-icon">▤</span>Hasil lab</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar no-print"><div><span class="staff-topbar-kicker">MODUL DOKTER</span><span class="staff-topbar-role">Surat hasil laboratorium</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <div class="staff-heading-actions no-print">
                    <a class="staff-secondary-button staff-button-link" href="{{ route('staff.doctor.laboratory-results.index') }}">Kembali ke hasil laboratorium</a>
                    @if ($report->status === 'Completed')
                        <button class="staff-action-button" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
                    @endif
                </div>

                <article class="staff-lab-letter">
                    <header class="staff-lab-letter-header">
                        <div class="staff-lab-letter-brand">TechnoMedic</div>
                        <p>PELAYANAN KESEHATAN TERPADU</p>
                        <hr>
                        <h1>SURAT HASIL PEMERIKSAAN LABORATORIUM</h1>
                        <span>Nomor: LAB-{{ str_pad((string) $report->id, 6, '0', STR_PAD_LEFT) }}</span>
                    </header>

                    @if ($report->status === 'Completed')
                        <p class="staff-lab-letter-date">Diterbitkan {{ \Illuminate\Support\Carbon::parse($report->resulted_at)->locale('id')->translatedFormat('d F Y') }}</p>
                    @else
                        <div class="staff-alert staff-alert-info no-print" role="status">Hasil pemeriksaan belum dikirim oleh laboratorium.</div>
                    @endif

                    <section class="staff-lab-letter-section">
                        <h2>Identitas pasien</h2>
                        <dl>
                            <div><dt>Nama</dt><dd>{{ $report->patient_name }}</dd></div>
                            <div><dt>Nomor rekam medis</dt><dd>{{ $report->medical_record_number ?: '—' }}</dd></div>
                            <div><dt>Nomor kunjungan</dt><dd>{{ $report->booking_code }}</dd></div>
                            <div><dt>Tanggal lahir / jenis kelamin</dt><dd>{{ $report->birth_date ? \Illuminate\Support\Carbon::parse($report->birth_date)->format('d-m-Y') : '—' }} / {{ $report->gender === 'L' ? 'Laki-laki' : ($report->gender === 'P' ? 'Perempuan' : '—') }}</dd></div>
                            <div><dt>Tanggal kunjungan</dt><dd>{{ \Illuminate\Support\Carbon::parse($report->appointment_date)->locale('id')->translatedFormat('d F Y') }}</dd></div>
                        </dl>
                    </section>

                    <section class="staff-lab-letter-section">
                        <h2>Dokter perujuk</h2>
                        <dl>
                            <div><dt>Nama</dt><dd>{{ $report->referring_doctor_name }}</dd></div>
                            <div><dt>Poli / departemen</dt><dd>{{ $report->referring_department_name }}</dd></div>
                            <div><dt>Diagnosis kerja</dt><dd>{{ $report->diagnosis }}</dd></div>
                        </dl>
                    </section>

                    <section class="staff-lab-letter-section">
                        <h2>Pemeriksaan dan hasil</h2>
                        <div class="staff-lab-letter-field"><strong>Jenis pemeriksaan</strong><p class="staff-pre-line">{{ $report->requested_tests }}</p></div>
                        <div class="staff-lab-letter-field"><strong>Informasi klinis</strong><p class="staff-pre-line">{{ $report->clinical_notes ?: '—' }}</p></div>
                        <div class="staff-lab-letter-field">
                            <strong>Hasil laboratorium</strong>
                            @if ($laboratoryResultGroups !== [])
                                @foreach ($laboratoryResultGroups as $specimen)
                                    <div class="staff-lab-result-group">
                                        <strong>{{ $specimen['label'] }}</strong>
                                        @foreach ($specimen['groups'] as $group)
                                            <h3>{{ $group['label'] }}</h3>
                                            <div class="staff-table-wrap">
                                                <table class="staff-table">
                                                    <thead><tr><th>Parameter</th><th>Hasil</th><th>Satuan</th><th>Nilai rujukan</th><th>Keterangan</th></tr></thead>
                                                    <tbody>
                                                        @foreach ($group['tests'] as $result)
                                                            <tr><td>{{ $result->parameter_name }}</td><td>{{ $result->result_value }}</td><td>{{ $result->unit ?: '—' }}</td><td>{{ $result->reference_range ?: '—' }}</td><td>{{ $result->interpretation === null ? '—' : ($result->interpretation === 'Normal' ? 'Normal' : ($result->interpretation === 'Low' ? 'Rendah' : 'Tinggi')) }}</td></tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            @else
                                <p class="staff-pre-line">{{ $report->results ?: 'Belum tersedia' }}</p>
                            @endif
                        </div>
                        <div class="staff-lab-letter-field"><strong>Kesimpulan / catatan</strong><p class="staff-pre-line">{{ $report->result_notes ?: '—' }}</p></div>
                    </section>

                    <footer class="staff-lab-letter-signatures">
                        <div><p>Dokter perujuk,</p><strong>{{ $report->referring_doctor_name }}</strong></div>
                        <div><p>Petugas laboratorium,</p><strong>{{ $report->status === 'Completed' ? ($report->resulted_by_email ?: 'Petugas laboratorium') : 'Belum diterbitkan' }}</strong><span>{{ $report->resulted_at ? \Illuminate\Support\Carbon::parse($report->resulted_at)->locale('id')->translatedFormat('d F Y, H:i') : '' }}</span></div>
                    </footer>
                    <p class="staff-lab-letter-footnote">Dokumen ini berisi hasil pemeriksaan penunjang untuk ditinjau dokter perujuk.</p>
                </article>
            </div>
        </main>
    </div>
</body>
</html>
