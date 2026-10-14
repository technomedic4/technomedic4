<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekam Medis | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>
            <div class="staff-sidebar-user">
                <span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span>
                <span class="staff-user-copy">
                    <strong>{{ auth()->user()->email }}</strong>
                    <span>{{ auth()->user()->role_id ? \App\Support\HospitalStaffRole::tryFrom(DB::table('roles')->where('id', auth()->user()->role_id)->value('role_name'))?->value ?? 'Staf' : 'Staf' }}</span>
                </span>
            </div>
            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
                <span class="staff-navigation-label staff-navigation-spaced">MODUL OPERASIONAL</span>
                @if (in_array(auth()->user()->role_id ? DB::table('roles')->where('id', auth()->user()->role_id)->value('role_name') : null, ['Resepsionis', 'Admin', 'Dokter'], true))
                    <a class="staff-navigation-link is-active" href="{{ route('staff.medical-records.index') }}" aria-current="page"><span class="staff-navigation-icon">＋</span>Rekam medis & laporan</a>
                @endif
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">
                @csrf
                <button class="staff-logout-button" type="submit">Keluar dari portal</button>
            </form>
        </aside>

        <main class="staff-main">
            <header class="staff-topbar">
                <div><span class="staff-topbar-kicker">CATATAN KLINIS</span><span class="staff-topbar-role">Rekam medis</span></div>
                <span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div>
                        <p class="staff-eyebrow">DATA PASIEN</p>
                        <h1>Rekam medis & laporan</h1>
                        <p>Cari pasien dan lihat riwayat kunjungan serta hasil konsultasi.</p>
                    </div>
                </section>

                @if (session('success'))
                    <div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel">
                    <form class="staff-search-form" action="{{ route('staff.medical-records.index') }}" method="get">
                        <label class="staff-sr-only" for="medical-record-search">Cari rekam medis</label>
                        <input id="medical-record-search" name="search" type="search" value="{{ $search }}" placeholder="Cari nama, nomor rekam medis, NIK, atau telepon">
                        <button class="staff-secondary-button" type="submit">Cari pasien</button>
                        @if ($search !== '')
                            <a class="staff-text-link" href="{{ route('staff.medical-records.index') }}">Hapus pencarian</a>
                        @endif
                    </form>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead>
                                <tr><th>No. rekam medis</th><th>Nama pasien</th><th>Telepon</th><th>Jenis kelamin</th><th>Aksi</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($patients as $patient)
                                    <tr>
                                        <td><span class="staff-mrn">{{ $patient->medical_record_number ?: 'Belum tersedia' }}</span></td>
                                        <td><strong class="staff-patient-name">{{ $patient->full_name }}</strong></td>
                                        <td>{{ $patient->phone_number ?: '—' }}</td>
                                        <td>{{ $patient->gender === 'L' ? 'Laki-laki' : ($patient->gender === 'P' ? 'Perempuan' : '—') }}</td>
                                        <td><a class="staff-inline-action" href="{{ route('staff.medical-records.show', $patient->id) }}">Lihat rekam medis</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="staff-table-empty">Tidak ada pasien yang cocok dengan pencarian Anda.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($patients->hasPages())
                        <div class="staff-pagination">{{ $patients->links() }}</div>
                    @endif
                </section>
                <p class="staff-data-note">Rekam medis menampilkan riwayat kunjungan yang sudah diselesaikan dan data klinis yang tersimpan dalam sistem.</p>
            </div>
        </main>
    </div>
</body>
</html>
