<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Laboratorium | TechnoMedic</title>
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
                <a class="staff-navigation-link" href="{{ route('staff.management.nurses.index') }}"><span class="staff-navigation-icon">＋</span>Manajemen perawat</a>
                <a class="staff-navigation-link is-active" href="{{ route('staff.management.laboratory.index') }}" aria-current="page"><span class="staff-navigation-icon">▤</span>Manajemen laboratorium</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MANAJEMEN STAF</span><span class="staff-topbar-role">Akun laboratorium</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading"><div><p class="staff-eyebrow">ROLE LABORATORIUM</p><h1>Manajemen laboratorium</h1><p>Buat akun petugas agar dapat menerima rujukan dan mengirim hasil pemeriksaan kepada dokter.</p></div></section>
                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-form-panel">
                    <div class="staff-panel-heading"><div><h2>Buat akun laboratorium</h2><p>Akun baru langsung aktif dan dapat masuk melalui portal staf.</p></div></div>
                    <form class="staff-data-form" action="{{ route('staff.management.laboratory.store') }}" method="post">
                        @csrf
                        <div class="staff-form-grid">
                            <div class="staff-form-field">
                                <label for="email">Email <span>*</span></label>
                                <input id="email" name="email" type="email" maxlength="100" value="{{ old('email') }}" autocomplete="email" required>
                            </div>
                            <div class="staff-form-field">
                                <label for="phone_number">Nomor telepon <span>*</span></label>
                                <input id="phone_number" name="phone_number" type="tel" maxlength="20" value="{{ old('phone_number') }}" autocomplete="tel" required>
                            </div>
                            <div class="staff-form-field">
                                <label for="password">Password awal <span>*</span></label>
                                <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                            </div>
                            <div class="staff-form-field">
                                <label for="password_confirmation">Konfirmasi password <span>*</span></label>
                                <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
                            </div>
                        </div>
                        <div class="staff-form-actions"><button class="staff-action-button" type="submit">Buat akun laboratorium</button></div>
                    </form>
                </section>

                <section class="staff-panel">
                    <div class="staff-panel-heading"><div><h2>Akun terdaftar</h2><p>{{ $staff->count() }} akun laboratorium.</p></div></div>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Email</th><th>Telepon</th><th>Status akun</th></tr></thead>
                            <tbody>
                                @forelse ($staff as $member)
                                    <tr><td><strong class="staff-patient-name">{{ $member->email }}</strong></td><td>{{ $member->phone_number ?: '—' }}</td><td><span class="staff-status {{ $member->is_active ? 'is-completed' : 'is-cancelled' }}">{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}</span></td></tr>
                                @empty
                                    <tr><td colspan="3" class="staff-table-empty">Belum ada akun laboratorium terdaftar.</td></tr>
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
