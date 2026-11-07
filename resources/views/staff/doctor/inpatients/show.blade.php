<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluasi Rawat Inap | TechnoMedic</title>
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
                <a class="staff-navigation-link" href="{{ route('staff.medical-records.index') }}"><span class="staff-navigation-icon">▤</span>Rekam medis</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.teleconsultations.index') }}"><span class="staff-navigation-icon">✉</span>Telekonsultasi</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL DOKTER</span><span class="staff-topbar-role">Evaluasi rawat inap</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">PEMANTAUAN RAWAT INAP</p><h1>{{ $admission->patient_name }}</h1><p>{{ $admission->medical_record_number ?: 'Nomor rekam medis belum tersedia' }} · Kamar {{ $admission->room_number ?: '—' }} · Tempat tidur {{ $admission->bed_number ?: '—' }}</p></div>
                    <a class="staff-secondary-button staff-button-link" href="{{ route('staff.doctor.inpatients.index') }}">Kembali ke pasien rawat inap</a>
                </section>

                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel staff-encounter-summary">
                    <div class="staff-panel-heading">
                        <div><h2>Ringkasan rawat inap</h2><p>Masuk {{ \Illuminate\Support\Carbon::parse($admission->admitted_at)->locale('id')->translatedFormat('d F Y, H:i') }}</p></div>
                        @if ($admission->doctor_discharge_approved_at)
                            <span class="staff-status is-confirmed">Kepulangan divalidasi</span>
                        @else
                            <span class="staff-status is-arrived">Masih dalam perawatan</span>
                        @endif
                    </div>
                    <div class="staff-encounter-grid">
                        <div><span class="staff-stat-label">JENIS KELAMIN</span><strong>{{ $admission->gender === 'L' ? 'Laki-laki' : ($admission->gender === 'P' ? 'Perempuan' : 'Belum dicatat') }}</strong></div>
                        <div><span class="staff-stat-label">TANGGAL LAHIR</span><strong>{{ $admission->birth_date ? \Illuminate\Support\Carbon::parse($admission->birth_date)->locale('id')->translatedFormat('d F Y') : 'Belum dicatat' }}</strong></div>
                    </div>
                    <div class="staff-record-field"><span>Alasan masuk</span><p>{{ $admission->admission_reason }}</p></div>
                    <div class="staff-record-field"><span>Diagnosis terkini</span><p>{{ $admission->diagnosis }}</p></div>
                    <div class="staff-record-field"><span>Instruksi pengobatan terkini</span><p>{{ $admission->treatment_plan }}</p></div>
                    @if ($admission->doctor_discharge_approved_at)
                        <p class="staff-data-note">Validasi kepulangan diberikan {{ \Illuminate\Support\Carbon::parse($admission->doctor_discharge_approved_at)->locale('id')->translatedFormat('d F Y, H:i') }}. Resepsionis dapat melanjutkan proses checkout.</p>
                    @endif
                </section>

                <section class="staff-panel">
                    <div class="staff-panel-heading"><div><h2>Riwayat pemantauan perawat</h2><p>Riwayat kondisi dan tanda vital yang dicatat perawat selama rawat inap.</p></div></div>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Waktu</th><th>Kondisi</th><th>Tanda vital</th><th>Catatan</th><th>Dicatat oleh</th></tr></thead>
                            <tbody>
                                @forelse ($updates as $update)
                                    <tr>
                                        <td>{{ $update->created_at ? \Illuminate\Support\Carbon::parse($update->created_at)->locale('id')->translatedFormat('d F Y, H:i') : '—' }}</td>
                                        <td>{{ $update->update_status }}</td>
                                        <td>
                                            @if ($update->temperature_celsius !== null)Suhu {{ number_format((float) $update->temperature_celsius, 1) }} °C<br>@endif
                                            @if ($update->blood_pressure)TD {{ $update->blood_pressure }} mmHg<br>@endif
                                            @if ($update->pulse_rate !== null)Nadi {{ $update->pulse_rate }} x/menit<br>@endif
                                            @if ($update->respiratory_rate !== null)Napas {{ $update->respiratory_rate }} x/menit<br>@endif
                                            @if ($update->oxygen_saturation !== null)SpO₂ {{ $update->oxygen_saturation }}%@endif
                                            @if ($update->temperature_celsius === null && !$update->blood_pressure && $update->pulse_rate === null && $update->respiratory_rate === null && $update->oxygen_saturation === null)—@endif
                                        </td>
                                        <td>{{ $update->notes ?: '—' }}</td>
                                        <td>{{ $update->recorded_by ?: 'Petugas' }}<span class="staff-booking-code">{{ $update->recorded_by_role ?: 'Staf' }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="staff-table-empty">Belum ada pemantauan yang tercatat untuk pasien ini.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($updates->hasPages())<div class="staff-pagination">{{ $updates->links() }}</div>@endif
                </section>

                <section class="staff-form-panel">
                    <div class="staff-panel-heading"><div><h2>Diagnosis dan rencana pengobatan</h2><p>Perbarui diagnosis dan instruksi pengobatan. Pemeriksaan kondisi dan tanda vital dicatat oleh perawat.</p></div></div>
                    <form class="staff-data-form" action="{{ route('staff.doctor.inpatients.evaluations.store', $admission->id) }}" method="post">
                        @csrf
                        <div class="staff-form-grid">
                            <div class="staff-form-field staff-field-wide">
                                <label for="diagnosis">Diagnosis terkini <span>*</span></label>
                                <textarea id="diagnosis" name="diagnosis" rows="4" maxlength="5000" required>{{ old('diagnosis', $admission->diagnosis) }}</textarea>
                            </div>
                            <div class="staff-form-field staff-field-wide">
                                <label for="treatment_plan">Instruksi pengobatan <span>*</span></label>
                                <textarea id="treatment_plan" name="treatment_plan" rows="4" maxlength="5000" required>{{ old('treatment_plan', $admission->treatment_plan) }}</textarea>
                            </div>
                            <div class="staff-form-field staff-field-wide">
                                <label for="discharge_decision">Keputusan rawat inap <span>*</span></label>
                                <select id="discharge_decision" name="discharge_decision" required>
                                    <option value="Continue" @selected(old('discharge_decision', $admission->doctor_discharge_approved_at ? 'Approve' : 'Continue') === 'Continue')>Pasien masih perlu dirawat</option>
                                    <option value="Approve" @selected(old('discharge_decision', $admission->doctor_discharge_approved_at ? 'Approve' : 'Continue') === 'Approve')>Validasi kepulangan untuk proses administrasi</option>
                                </select>
                            </div>
                        </div>
                        <div class="staff-form-actions"><button class="staff-action-button" type="submit">Simpan pemeriksaan lanjutan</button></div>
                    </form>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
