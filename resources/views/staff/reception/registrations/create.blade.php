<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Kunjungan | TechnoMedic</title>
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
                <a class="staff-navigation-link is-active" href="{{ route('staff.reception.registrations.index') }}"><span class="staff-navigation-icon">◷</span>Pendaftaran & antrean</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL RESEPSIONIS</span><span class="staff-topbar-role">Pendaftaran</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">KUNJUNGAN RAWAT JALAN</p><h1>Daftarkan kunjungan</h1><p>Pilih jadwal poli, dokter, dan tanggal kunjungan pasien.</p></div>
                    <a class="staff-secondary-button staff-button-link" href="{{ route('staff.reception.registrations.index', ['date' => $date]) }}">Lihat antrean</a>
                </section>
                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel staff-selected-patient">
                    <span class="staff-selected-patient-icon">✓</span>
                    <div><span class="staff-stat-label">PASIEN TERPILIH</span><strong>{{ $patient->full_name }}</strong><span>{{ $patient->medical_record_number ?: 'Nomor rekam medis belum tersedia' }} · {{ $patient->phone_number ?: 'Telepon belum tercatat' }}</span></div>
                    <a class="staff-inline-action" href="{{ route('staff.reception.patients.index') }}">Ganti pasien</a>
                </section>

                <section class="staff-form-panel">
                    <form class="staff-data-form" action="{{ route('staff.reception.registrations.store') }}" method="post">
                        @csrf
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        <div class="staff-form-grid">
                            <div class="staff-form-field">
                                <label for="appointment_date">Tanggal kunjungan <span>*</span></label>
                                <input id="appointment_date" name="appointment_date" type="date" value="{{ old('appointment_date', $date) }}" min="{{ now()->toDateString() }}" required>
                                <small>Pilih tanggal sesuai hari praktik dokter.</small>
                            </div>
                            <div class="staff-form-field">
                                <label for="doctor_schedule_id">Poli, dokter & jam praktik <span>*</span></label>
                                <select id="doctor_schedule_id" name="doctor_schedule_id" required>
                                    <option value="">Pilih jadwal dokter</option>
                                    @foreach ($schedules as $schedule)
                                        <option
                                            value="{{ $schedule->id }}"
                                            data-day="{{ $schedule->day_of_week }}"
                                            @selected((string) old('doctor_schedule_id') === (string) $schedule->id)
                                        >
                                            {{ $schedule->department_name }} — {{ $schedule->doctor_name }} · {{ $schedule->day_of_week }}, {{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }} · kuota {{ $schedule->quota_limit }}
                                        </option>
                                    @endforeach
                                </select>
                                <small id="schedule-hint" aria-live="polite">Jadwal yang tampil mengikuti hari kunjungan.</small>
                            </div>
                            <div class="staff-form-field staff-field-wide">
                                <label for="consultation_type">Jenis kunjungan <span>*</span></label>
                                <select id="consultation_type" name="consultation_type" required>
                                    <option value="In-Person" @selected(old('consultation_type', 'In-Person') === 'In-Person')>Tatap muka di rumah sakit</option>
                                    <option value="Teleconsultation" @selected(old('consultation_type') === 'Teleconsultation')>Telekonsultasi melalui chat portal</option>
                                </select>
                                <small>Chat telekonsultasi tersedia untuk pasien yang memiliki akun portal tertaut ke profil pasien.</small>
                            </div>
                            <div class="staff-form-field staff-field-wide">
                                <label for="initial_complaint">Keluhan awal <span class="staff-optional-label">Opsional</span></label>
                                <textarea id="initial_complaint" name="initial_complaint" rows="3" maxlength="2000" placeholder="Catat ringkas keluhan yang disampaikan pasien">{{ old('initial_complaint') }}</textarea>
                                <small>Informasi ini menjadi catatan awal pendaftaran, bukan diagnosis medis.</small>
                            </div>
                        </div>
                        <div class="staff-form-actions">
                            <a class="staff-cancel-link" href="{{ route('staff.reception.patients.index') }}">Batal</a>
                            <button class="staff-action-button" type="submit">Simpan & buat nomor antrean</button>
                        </div>
                    </form>
                </section>
            </div>
        </main>
    </div>
    <script>
        const appointmentDate = document.getElementById('appointment_date');
        const scheduleSelect = document.getElementById('doctor_schedule_id');
        const scheduleHint = document.getElementById('schedule-hint');
        const weekdayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        function filterSchedulesByDay() {
            const selectedDay = weekdayNames[new Date(`${appointmentDate.value}T12:00:00`).getDay()];
            let availableSchedules = 0;

            for (const option of scheduleSelect.options) {
                if (!option.value) continue;

                const matchesDay = option.dataset.day === selectedDay;
                option.hidden = !matchesDay;
                option.disabled = !matchesDay;

                if (matchesDay) availableSchedules++;
            }

            if (scheduleSelect.selectedOptions[0]?.disabled) {
                scheduleSelect.value = '';
            }

            scheduleHint.textContent = availableSchedules
                ? `Jadwal yang tersedia untuk hari ${selectedDay}. Kuota diverifikasi kembali saat pendaftaran disimpan.`
                : `Belum ada jadwal terdaftar untuk hari ${selectedDay}.`;
        }

        appointmentDate.addEventListener('change', filterSchedulesByDay);
        filterSchedulesByDay();
    </script>
</body>
</html>
