<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rawat Inap | TechnoMedic</title>
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
                <span class="staff-navigation-label staff-navigation-spaced">MODUL RESEPSIONIS</span>
                <a class="staff-navigation-link" href="{{ route('staff.reception.patients.index') }}"><span class="staff-navigation-icon">▤</span>Data pasien</a>
                <a class="staff-navigation-link" href="{{ route('staff.reception.registrations.index') }}"><span class="staff-navigation-icon">◷</span>Pendaftaran & antrean</a>
                <a class="staff-navigation-link is-active" href="{{ route('staff.inpatient-admissions.index') }}" aria-current="page"><span class="staff-navigation-icon">＋</span>Rawat inap</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL RAWAT INAP</span><span class="staff-topbar-role">Admisi pasien</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">ADMISI & DISCHARGE</p><h1>Pasien rawat inap</h1><p>Catat pasien masuk dan keluar agar daftar perawatan perawat tetap akurat.</p></div>
                    <a class="staff-action-button staff-button-link" href="{{ route('staff.inpatient-admissions.create') }}">+ Admisi pasien</a>
                </section>

                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel">
                    <form class="staff-search-form" action="{{ route('staff.inpatient-admissions.index') }}" method="get">
                        <label class="staff-sr-only" for="inpatient-admission-search">Cari pasien rawat inap</label>
                        <input id="inpatient-admission-search" name="search" type="search" value="{{ $search }}" placeholder="Cari nama, nomor rekam medis, kamar, atau tempat tidur">
                        <button class="staff-secondary-button" type="submit">Cari pasien</button>
                        @if ($search !== '')<a class="staff-text-link" href="{{ route('staff.inpatient-admissions.index') }}">Hapus pencarian</a>@endif
                    </form>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Pasien</th><th>Kamar / tempat tidur</th><th>Alasan masuk</th><th>Waktu masuk</th><th>Admisi oleh</th><th>Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($admissions as $admission)
                                    <tr>
                                        <td><strong class="staff-patient-name">{{ $admission->patient_name }}</strong><span class="staff-booking-code">{{ $admission->medical_record_number ?: 'No. RM belum tersedia' }}</span></td>
                                        <td>{{ $admission->room_number ?: '—' }} / {{ $admission->bed_number ?: '—' }}</td>
                                        <td>{{ \Illuminate\Support\Str::limit($admission->admission_reason, 100) }}</td>
                                        <td>{{ \Illuminate\Support\Carbon::parse($admission->admitted_at)->locale('id')->translatedFormat('d F Y, H:i') }}</td>
                                        <td>{{ $admission->admitted_by }}</td>
                                        <td>
                                            <form action="{{ route('staff.inpatient-admissions.discharge', $admission->id) }}" method="post" onsubmit="return confirm('Catat pasien ini keluar dari rawat inap?')">
                                                @csrf
                                                <button class="staff-small-action is-danger" type="submit">Catat keluar</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="staff-table-empty">Belum ada pasien rawat inap aktif.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($admissions->hasPages())<div class="staff-pagination">{{ $admissions->links() }}</div>@endif
                </section>
                <p class="staff-data-note">Pasien keluar tetap tersimpan sebagai riwayat. Pembaruan kondisi yang sudah dicatat tidak dihapus saat pasien keluar.</p>
            </div>
        </main>
    </div>
</body>
</html>
