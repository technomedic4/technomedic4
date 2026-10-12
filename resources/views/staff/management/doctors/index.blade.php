<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Dokter | TechnoMedic</title>
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
                <span class="staff-navigation-label staff-navigation-spaced">MANAJEMEN STAF</span>
                <a class="staff-navigation-link is-active" href="{{ route('staff.management.doctors.index') }}" aria-current="page"><span class="staff-navigation-icon">＋</span>Manajemen dokter</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MANAJEMEN STAF</span><span class="staff-topbar-role">Akun dokter</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">PENGELOLAAN AKUN</p><h1>Manajemen dokter</h1><p>Buat akun portal dan profil dokter yang terhubung dengan poli rumah sakit.</p></div>
                </section>

                @if (session('success'))
                    <div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-form-panel staff-doctor-form-panel">
                    <div class="staff-panel-heading">
                        <div><h2>Buat akun dokter</h2><p>Dokter dapat masuk menggunakan email dan kata sandi awal yang dibuat di sini.</p></div>
                    </div>
                    <form class="staff-data-form" action="{{ route('staff.management.doctors.store') }}" method="post">
                        @csrf
                        <div class="staff-form-grid">
                            <div class="staff-form-field staff-field-wide">
                                <label for="full_name">Nama lengkap dokter <span>*</span></label>
                                <input id="full_name" name="full_name" value="{{ old('full_name') }}" maxlength="150" autocomplete="name" required>
                            </div>
                            <div class="staff-form-field">
                                <label for="email">Email untuk login <span>*</span></label>
                                <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="100" autocomplete="email" required>
                            </div>
                            <div class="staff-form-field">
                                <label for="phone_number">Nomor telepon <span>*</span></label>
                                <input id="phone_number" name="phone_number" type="tel" value="{{ old('phone_number') }}" maxlength="20" autocomplete="tel" required>
                            </div>
                            <div class="staff-form-field">
                                <label for="password">Kata sandi awal <span>*</span></label>
                                <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                                <small>Minimal 12 karakter. Sampaikan kata sandi ini kepada dokter secara aman.</small>
                            </div>
                            <div class="staff-form-field">
                                <label for="password_confirmation">Konfirmasi kata sandi <span>*</span></label>
                                <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
                            </div>
                            <div class="staff-form-field">
                                <label for="department_id">Poli / departemen <span>*</span></label>
                                <select id="department_id" name="department_id" required>
                                    <option value="">Pilih poli</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                                @if ($departments->isEmpty())
                                    <small>Belum ada departemen. Tambahkan data departemen sebelum membuat akun dokter.</small>
                                @endif
                            </div>
                            <div class="staff-form-field">
                                <label for="specialization">Spesialisasi <span>*</span></label>
                                <input id="specialization" name="specialization" value="{{ old('specialization') }}" maxlength="100" required>
                            </div>
                            <div class="staff-form-field staff-field-wide">
                                <label for="str_number">Nomor STR <span>*</span></label>
                                <input id="str_number" name="str_number" value="{{ old('str_number') }}" maxlength="100" required>
                            </div>
                        </div>
                        <div class="staff-form-actions">
                            <button class="staff-action-button" type="submit" @disabled($departments->isEmpty())>Buat akun dokter</button>
                        </div>
                    </form>
                </section>

                <section class="staff-panel staff-doctor-list-panel">
                    <div class="staff-panel-heading"><div><h2>Dokter terdaftar</h2><p>{{ $doctors->count() }} akun dokter terhubung ke departemen.</p></div></div>
                    <div class="staff-table-wrap">
                        <table class="staff-table staff-doctor-table">
                            <thead><tr><th>Nama dokter</th><th>Poli</th><th>Spesialisasi</th><th>Nomor STR</th><th>Email akun</th><th>Telepon</th></tr></thead>
                            <tbody>
                                @forelse ($doctors as $doctor)
                                    <tr>
                                        <td><strong class="staff-patient-name">{{ $doctor->full_name }}</strong></td>
                                        <td>{{ $doctor->department_name }}</td>
                                        <td>{{ $doctor->specialization }}</td>
                                        <td>{{ $doctor->str_number }}</td>
                                        <td>{{ $doctor->email }}</td>
                                        <td>{{ $doctor->phone_number }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="staff-table-empty">Belum ada akun dokter terdaftar.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>
</body>
</html>
