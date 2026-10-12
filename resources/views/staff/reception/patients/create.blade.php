<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pasien Baru | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>
            <div class="staff-sidebar-user">
                <span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span>
                <span class="staff-user-copy"><strong>{{ auth()->user()->email }}</strong><span>Resepsionis</span></span>
            </div>
            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
                <span class="staff-navigation-label staff-navigation-spaced">MODUL RESEPSIONIS</span>
                <a class="staff-navigation-link is-active" href="{{ route('staff.reception.patients.index') }}"><span class="staff-navigation-icon">▤</span>Data pasien</a>
                <a class="staff-navigation-link" href="{{ route('staff.reception.registrations.index') }}"><span class="staff-navigation-icon">◷</span>Pendaftaran & antrean</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL RESEPSIONIS</span><span class="staff-topbar-role">Pasien baru</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">REGISTRASI PASIEN</p><h1>Tambah pasien baru</h1><p>Pasien loket tidak harus memiliki akun aplikasi untuk dibuatkan kunjungan.</p></div>
                    <a class="staff-secondary-button staff-button-link" href="{{ route('staff.reception.patients.index') }}">Kembali ke pasien</a>
                </section>
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif
                <section class="staff-form-panel">
                    <form class="staff-data-form" action="{{ route('staff.reception.patients.store') }}" method="post">
                        @csrf
                        @if ($forRegistration)<input type="hidden" name="for_registration" value="1">@endif
                        <div class="staff-form-grid">
                            <div class="staff-form-field staff-field-wide">
                                <label for="full_name">Nama lengkap <span>*</span></label>
                                <input id="full_name" name="full_name" value="{{ old('full_name') }}" maxlength="150" autocomplete="name" required>
                            </div>
                            <div class="staff-form-field">
                                <label for="national_id">NIK (16 digit)</label>
                                <input id="national_id" name="national_id" value="{{ old('national_id') }}" inputmode="numeric" pattern="[0-9]{16}" maxlength="16" autocomplete="off">
                            </div>
                            <div class="staff-form-field">
                                <label for="phone_number">Nomor telepon <span>*</span></label>
                                <input id="phone_number" name="phone_number" type="tel" value="{{ old('phone_number') }}" maxlength="20" autocomplete="tel" required>
                            </div>
                            <div class="staff-form-field">
                                <label for="emergency_contact">Kontak darurat</label>
                                <input id="emergency_contact" name="emergency_contact" type="tel" value="{{ old('emergency_contact') }}" maxlength="20">
                            </div>
                            <div class="staff-form-field">
                                <label for="gender">Jenis kelamin <span>*</span></label>
                                <select id="gender" name="gender" required>
                                    <option value="">Pilih jenis kelamin</option>
                                    <option value="L" @selected(old('gender') === 'L')>Laki-laki</option>
                                    <option value="P" @selected(old('gender') === 'P')>Perempuan</option>
                                </select>
                            </div>
                            <div class="staff-form-field">
                                <label for="birth_date">Tanggal lahir <span>*</span></label>
                                <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date') }}" max="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="staff-form-field staff-field-wide">
                                <label for="address">Alamat</label>
                                <textarea id="address" name="address" rows="3" maxlength="2000">{{ old('address') }}</textarea>
                            </div>
                        </div>
                        <div class="staff-form-actions">
                            <a class="staff-cancel-link" href="{{ route('staff.reception.patients.index') }}">Batal</a>
                            <button class="staff-action-button" type="submit">{{ $forRegistration ? 'Simpan & lanjutkan pendaftaran' : 'Simpan data pasien' }}</button>
                        </div>
                    </form>
                </section>
                <p class="staff-data-note">Nomor rekam medis dibuat otomatis setelah data pasien berhasil disimpan. Hindari membuat duplikat; cari pasien terlebih dahulu.</p>
            </div>
        </main>
    </div>
</body>
</html>
