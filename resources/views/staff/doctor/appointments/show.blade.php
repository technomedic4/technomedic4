<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemeriksaan Pasien | TechnoMedic</title>
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
                <a class="staff-navigation-link is-active" href="{{ route('staff.medical-records.index') }}" aria-current="page"><span class="staff-navigation-icon">＋</span>Rekam medis</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.teleconsultations.index') }}"><span class="staff-navigation-icon">✉</span>Telekonsultasi</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL DOKTER</span><span class="staff-topbar-role">Pemeriksaan pasien</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">CATATAN KONSULTASI</p><h1>Pemeriksaan pasien</h1><p>Catat hasil pemeriksaan dan rencana tindak lanjut kunjungan.</p></div>
                    <a class="staff-secondary-button staff-button-link" href="{{ route('staff.doctor.appointments.index', ['date' => $appointment->appointment_date]) }}">Kembali ke daftar pasien</a>
                </section>

                @if (session('success'))
                    <div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel staff-encounter-summary">
                    <div class="staff-panel-heading"><div><h2>{{ $appointment->patient_name }}</h2><p>{{ $appointment->medical_record_number ?: 'Nomor rekam medis belum tersedia' }} · {{ $appointment->department_name }} · {{ $appointment->doctor_name }}</p></div><span class="staff-status {{ $appointment->status === 'Completed' ? 'is-completed' : 'is-arrived' }}">{{ $appointment->status === 'Completed' ? 'Selesai' : 'Sudah check-in' }}</span></div>
                    <div class="staff-encounter-grid">
                        <div><span class="staff-stat-label">NOMOR ANTREAN</span><strong>{{ str_pad((string) $appointment->queue_number, 3, '0', STR_PAD_LEFT) }}</strong></div>
                        <div><span class="staff-stat-label">TANGGAL & JAM</span><strong>{{ \Illuminate\Support\Carbon::parse($appointment->appointment_date)->locale('id')->translatedFormat('d F Y') }} · {{ substr($appointment->start_time, 0, 5) }}</strong></div>
                        <div><span class="staff-stat-label">JENIS KELAMIN</span><strong>{{ $appointment->gender === 'L' ? 'Laki-laki' : ($appointment->gender === 'P' ? 'Perempuan' : 'Belum dicatat') }}</strong></div>
                        <div><span class="staff-stat-label">TANGGAL LAHIR</span><strong>{{ $appointment->birth_date ? \Illuminate\Support\Carbon::parse($appointment->birth_date)->format('d-m-Y') : 'Belum dicatat' }}</strong></div>
                        <div><span class="staff-stat-label">TELEPON</span><strong>{{ $appointment->phone_number ?: 'Belum dicatat' }}</strong></div>
                    </div>
                    <div class="staff-encounter-complaint"><span class="staff-stat-label">KELUHAN AWAL DARI LOKET</span><p>{{ $appointment->initial_complaint ?: 'Belum ada keluhan awal yang dicatat.' }}</p></div>
                </section>

                @if ($consultation)
                    <section class="staff-form-panel staff-consultation-record">
                        <div class="staff-panel-heading"><div><h2>Catatan konsultasi tersimpan</h2><p>Disimpan {{ \Illuminate\Support\Carbon::parse($consultation->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}</p></div></div>
                        <div class="staff-record-field"><span>Diagnosis</span><p>{{ $consultation->diagnosis }}</p></div>
                        <div class="staff-record-field"><span>Rencana tindakan / terapi</span><p>{{ $consultation->treatment_plan }}</p></div>
                        <div class="staff-record-field"><span>Catatan tambahan</span><p>{{ $consultation->notes ?: 'Tidak ada catatan tambahan.' }}</p></div>
                    </section>
                @elseif ($appointment->status === 'Checked-In')
                    <section class="staff-form-panel">
                        <div class="staff-panel-heading"><div><h2>Hasil pemeriksaan</h2><p>Lengkapi diagnosis dan rencana terapi sebelum menyelesaikan kunjungan.</p></div></div>
                        <form class="staff-data-form" action="{{ route('staff.doctor.appointments.consultation.store', $appointment->id) }}" method="post">
                            @csrf
                            <div class="staff-form-grid">
                                <div class="staff-form-field staff-field-wide">
                                    <label for="diagnosis">Diagnosis <span>*</span></label>
                                    <textarea id="diagnosis" name="diagnosis" rows="4" maxlength="5000" required>{{ old('diagnosis') }}</textarea>
                                </div>
                                <div class="staff-form-field staff-field-wide">
                                    <label for="treatment_plan">Rencana tindakan / terapi <span>*</span></label>
                                    <textarea id="treatment_plan" name="treatment_plan" rows="4" maxlength="5000" required>{{ old('treatment_plan') }}</textarea>
                                </div>
                                <div class="staff-form-field staff-field-wide">
                                    <label for="notes">Catatan tambahan <span class="staff-optional-label">Opsional</span></label>
                                    <textarea id="notes" name="notes" rows="3" maxlength="5000">{{ old('notes') }}</textarea>
                                </div>
                            </div>
                            <div class="staff-form-actions">
                                <a class="staff-cancel-link" href="{{ route('staff.doctor.appointments.index', ['date' => $appointment->appointment_date]) }}">Batal</a>
                                <button class="staff-action-button" type="submit">Simpan catatan & selesaikan kunjungan</button>
                            </div>
                        </form>
                    </section>
                @else
                    <section class="staff-alert staff-alert-info" role="status">
                        <strong>Kunjungan sudah ditutup</strong>
                        <p>Catatan konsultasi untuk kunjungan ini tidak tersedia. Kunjungan yang sudah selesai tidak dapat diubah dari portal dokter.</p>
                    </section>
                @endif
                <p class="staff-data-note">Pastikan diagnosis dan rencana tindak lanjut sudah benar. Setelah disimpan, catatan tidak dapat diubah dari halaman ini.</p>
            </div>
        </main>
    </div>
</body>
</html>
