<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asesmen Awal Pasien | TechnoMedic</title>
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
                <a class="staff-navigation-link is-active" href="{{ route('staff.nursing.assessment-queue.index') }}" aria-current="page"><span class="staff-navigation-icon">◷</span>Antrean pemeriksaan awal</a>
                <a class="staff-navigation-link" href="{{ route('staff.nursing.inpatients.index') }}"><span class="staff-navigation-icon">＋</span>Pasien rawat inap</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>

        <main class="staff-main">
            <header class="staff-topbar">
                <div><span class="staff-topbar-kicker">MODUL PERAWAT</span><span class="staff-topbar-role">Pemeriksaan awal pasien</span></div>
                <span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">PEMERIKSAAN AWAL PASIEN</p><h1>{{ $appointment->patient_name }}</h1><p>{{ $appointment->medical_record_number ?: 'Nomor rekam medis belum tersedia' }} · {{ $appointment->department_name }} · {{ $appointment->doctor_name }}</p></div>
                    <a class="staff-secondary-button staff-button-link" href="{{ route('staff.nursing.assessment-queue.index', ['date' => $appointment->appointment_date]) }}">Kembali ke antrean</a>
                </section>

                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel staff-encounter-summary">
                    <div class="staff-panel-heading">
                        <div><h2>Ringkasan kunjungan</h2><p>{{ $appointment->booking_code }} · Antrean {{ str_pad((string) $appointment->queue_number, 3, '0', STR_PAD_LEFT) }} · {{ \Illuminate\Support\Carbon::parse($appointment->appointment_date)->locale('id')->translatedFormat('d F Y') }} · {{ substr($appointment->start_time, 0, 5) }}</p></div>
                        <span class="staff-status {{ $assessment ? 'is-confirmed' : 'is-arrived' }}">{{ $assessment ? 'Siap diperiksa dokter' : 'Menunggu pemeriksaan perawat' }}</span>
                    </div>
                    <div class="staff-encounter-grid">
                        <div><span class="staff-stat-label">JENIS KELAMIN</span><strong>{{ $appointment->gender === 'L' ? 'Laki-laki' : ($appointment->gender === 'P' ? 'Perempuan' : 'Belum dicatat') }}</strong></div>
                        <div><span class="staff-stat-label">TANGGAL LAHIR</span><strong>{{ $appointment->birth_date ? \Illuminate\Support\Carbon::parse($appointment->birth_date)->format('d-m-Y') : 'Belum dicatat' }}</strong></div>
                    </div>
                    <div class="staff-encounter-complaint"><span class="staff-stat-label">KELUHAN AWAL DARI LOKET</span><p>{{ $appointment->initial_complaint ?: 'Belum ada keluhan awal yang dicatat.' }}</p></div>
                </section>

                @if ($assessment)
                    <section class="staff-form-panel">
                        <div class="staff-panel-heading"><div><h2>Pemeriksaan awal tersimpan</h2><p>Dicatat oleh {{ $assessment->recorded_by ?: 'perawat' }} pada {{ \Illuminate\Support\Carbon::parse($assessment->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}. Rekam medis sudah tersedia untuk dokter.</p></div></div>
                        <div class="staff-encounter-grid">
                            <div><span class="staff-stat-label">KONDISI</span><strong>{{ $assessment->update_status }}</strong></div>
                            <div><span class="staff-stat-label">SUHU</span><strong>{{ $assessment->temperature_celsius !== null ? number_format((float) $assessment->temperature_celsius, 1).' °C' : '—' }}</strong></div>
                            <div><span class="staff-stat-label">TEKANAN DARAH</span><strong>{{ $assessment->blood_pressure ? $assessment->blood_pressure.' mmHg' : '—' }}</strong></div>
                            <div><span class="staff-stat-label">NADI</span><strong>{{ $assessment->pulse_rate !== null ? $assessment->pulse_rate.' x/menit' : '—' }}</strong></div>
                            <div><span class="staff-stat-label">FREKUENSI NAPAS</span><strong>{{ $assessment->respiratory_rate !== null ? $assessment->respiratory_rate.' x/menit' : '—' }}</strong></div>
                            <div><span class="staff-stat-label">SATURASI OKSIGEN</span><strong>{{ $assessment->oxygen_saturation !== null ? $assessment->oxygen_saturation.'%' : '—' }}</strong></div>
                        </div>
                        <div class="staff-encounter-complaint"><span class="staff-stat-label">CATATAN PERAWAT</span><p>{{ $assessment->notes }}</p></div>
                    </section>
                @else
                    <section class="staff-form-panel">
                        <div class="staff-panel-heading"><div><h2>Catat pemeriksaan awal</h2><p>Isi kondisi pasien dan tanda vital yang berhasil diukur. Dokter akan menentukan tindak lanjut setelah pemeriksaan lanjutan.</p></div></div>
                        <form class="staff-data-form" action="{{ route('staff.nursing.assessment-queue.assessments.store', $appointment->id) }}" method="post">
                            @csrf
                            <div class="staff-form-grid">
                                <div class="staff-form-field staff-field-wide">
                                    <label for="update_status">Kondisi pasien <span>*</span></label>
                                    <select id="update_status" name="update_status" required>
                                        <option value="">Pilih kondisi</option>
                                        @foreach ($updateStatuses as $status)
                                            <option value="{{ $status }}" @selected(old('update_status') === $status)>{{ $status }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="staff-form-field">
                                    <label for="temperature_celsius">Suhu (°C)</label>
                                    <input id="temperature_celsius" name="temperature_celsius" type="number" min="30" max="45" step="0.1" value="{{ old('temperature_celsius') }}">
                                </div>
                                <div class="staff-form-field">
                                    <label for="blood_pressure">Tekanan darah (mmHg)</label>
                                    <input id="blood_pressure" name="blood_pressure" type="text" maxlength="15" pattern="[0-9]{2,3}/[0-9]{2,3}" placeholder="Contoh: 120/80" value="{{ old('blood_pressure') }}">
                                </div>
                                <div class="staff-form-field">
                                    <label for="pulse_rate">Nadi (x/menit)</label>
                                    <input id="pulse_rate" name="pulse_rate" type="number" min="20" max="250" value="{{ old('pulse_rate') }}">
                                </div>
                                <div class="staff-form-field">
                                    <label for="respiratory_rate">Frekuensi napas (x/menit)</label>
                                    <input id="respiratory_rate" name="respiratory_rate" type="number" min="5" max="60" value="{{ old('respiratory_rate') }}">
                                </div>
                                <div class="staff-form-field">
                                    <label for="oxygen_saturation">Saturasi oksigen (%)</label>
                                    <input id="oxygen_saturation" name="oxygen_saturation" type="number" min="50" max="100" value="{{ old('oxygen_saturation') }}">
                                </div>
                                <div class="staff-form-field staff-field-wide">
                                    <label for="notes">Catatan pemeriksaan perawat <span>*</span></label>
                                    <textarea id="notes" name="notes" rows="4" maxlength="3000" required>{{ old('notes') }}</textarea>
                                </div>
                            </div>
                            <div class="staff-form-actions">
                                <a class="staff-cancel-link" href="{{ route('staff.nursing.assessment-queue.index', ['date' => $appointment->appointment_date]) }}">Batal</a>
                                <button class="staff-action-button" type="submit">Simpan & teruskan ke dokter</button>
                            </div>
                        </form>
                    </section>
                @endif
                <p class="staff-data-note">Catatan pemeriksaan awal tersimpan sebagai bagian dari rekam medis kunjungan. Tanda vital dan observasi ini tidak menggantikan penilaian dokter.</p>
            </div>
        </main>
    </div>
</body>
</html>
