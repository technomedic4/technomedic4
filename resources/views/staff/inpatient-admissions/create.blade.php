<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admisi Rawat Inap | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>
            <div class="staff-sidebar-user"><span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span><span class="staff-user-copy"><strong>{{ auth()->user()->email }}</strong><span>Administrasi</span></span></div>
            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
                <a class="staff-navigation-link is-active" href="{{ route('staff.inpatient-admissions.index') }}"><span class="staff-navigation-icon">＋</span>Rawat inap</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL RAWAT INAP</span><span class="staff-topbar-role">Admisi pasien</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">ADMISI PASIEN</p><h1>Catat pasien masuk</h1><p>Daftarkan pasien yang akan menjalani perawatan rawat inap.</p></div>
                    <a class="staff-secondary-button staff-button-link" href="{{ route('staff.inpatient-admissions.index') }}">Kembali</a>
                </section>
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel">
                    <form class="staff-search-form" action="{{ route('staff.inpatient-admissions.create') }}" method="get">
                        <label class="staff-sr-only" for="patient-search">Cari pasien</label>
                        <input id="patient-search" name="search" type="search" value="{{ $search }}" placeholder="Cari nama atau nomor rekam medis pasien">
                        <button class="staff-secondary-button" type="submit">Cari pasien</button>
                    </form>
                    <form class="staff-data-form" action="{{ route('staff.inpatient-admissions.store') }}" method="post">
                        @csrf
                        <div class="staff-form-grid">
                            <div class="staff-form-field staff-field-wide">
                                <label for="patient_id">Pasien <span>*</span></label>
                                <select id="patient_id" name="patient_id" required>
                                    <option value="">Pilih pasien</option>
                                    @foreach ($patients as $patient)
                                        <option value="{{ $patient->id }}" @selected((string) old('patient_id', $selectedPatientId) === (string) $patient->id)>{{ $patient->full_name }} · {{ $patient->medical_record_number ?: 'No. RM belum tersedia' }}</option>
                                    @endforeach
                                </select>
                                @if ($patients->isEmpty())<small>Tidak ada pasien yang cocok. Cari pasien lain atau daftarkan pasien terlebih dahulu.</small>@endif
                            </div>
                            <div class="staff-form-field">
                                <label for="room_number">Nomor kamar</label>
                                <input id="room_number" name="room_number" type="text" maxlength="30" value="{{ old('room_number') }}">
                            </div>
                            <div class="staff-form-field">
                                <label for="bed_number">Nomor tempat tidur</label>
                                <input id="bed_number" name="bed_number" type="text" maxlength="30" value="{{ old('bed_number') }}">
                            </div>
                            <div class="staff-form-field staff-field-wide">
                                <label for="admission_reason">Alasan / diagnosis masuk <span>*</span></label>
                                <textarea id="admission_reason" name="admission_reason" rows="4" maxlength="2000" required>{{ old('admission_reason') }}</textarea>
                            </div>
                        </div>
                        <div class="staff-form-actions">
                            <a class="staff-cancel-link" href="{{ route('staff.inpatient-admissions.index') }}">Batal</a>
                            <button class="staff-action-button" type="submit">Simpan admisi rawat inap</button>
                        </div>
                    </form>
                </section>
                <p class="staff-data-note">Pasien yang masih aktif dirawat tidak dapat didaftarkan kembali sampai proses keluar dicatat.</p>
            </div>
        </main>
    </div>
</body>
</html>
