<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pasien | TechnoMedic</title>
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
                    <span>Resepsionis</span>
                </span>
            </div>
            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
                <span class="staff-navigation-label staff-navigation-spaced">MODUL RESEPSIONIS</span>
                <a class="staff-navigation-link is-active" href="{{ route('staff.reception.patients.index') }}" aria-current="page"><span class="staff-navigation-icon">▤</span>Data pasien</a>
                <a class="staff-navigation-link" href="{{ route('staff.reception.registrations.index') }}"><span class="staff-navigation-icon">◷</span>Pendaftaran & antrean</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">
                @csrf
                <button class="staff-logout-button" type="submit">Keluar dari portal</button>
            </form>
        </aside>

        <main class="staff-main">
            <header class="staff-topbar">
                <div><span class="staff-topbar-kicker">MODUL RESEPSIONIS</span><span class="staff-topbar-role">Data pasien</span></div>
                <span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div>
                        <p class="staff-eyebrow">REGISTRASI PASIEN</p>
                        <h1>Data pasien</h1>
                        <p>Cari rekam pasien atau daftarkan pasien baru sebelum membuat kunjungan.</p>
                    </div>
                    <a class="staff-action-button" href="{{ route('staff.reception.patients.create') }}">+ Pasien baru</a>
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
                    <form class="staff-search-form" action="{{ route('staff.reception.patients.index') }}" method="get">
                        <label class="staff-sr-only" for="patient-search">Cari pasien</label>
                        <input id="patient-search" name="search" type="search" value="{{ $search }}" placeholder="Cari nama, nomor rekam medis, NIK, atau telepon">
                        <button class="staff-secondary-button" type="submit">Cari pasien</button>
                        @if ($search !== '')
                            <a class="staff-text-link" href="{{ route('staff.reception.patients.index') }}">Hapus pencarian</a>
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
                                        <td><a class="staff-inline-action" href="{{ route('staff.reception.registrations.create', ['patient_id' => $patient->id]) }}">Daftarkan kunjungan</a></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="staff-table-empty">Tidak ada pasien yang cocok. Periksa kata kunci atau tambahkan pasien baru.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($patients->hasPages())
                        <div class="staff-pagination">{{ $patients->links() }}</div>
                    @endif
                </section>
                <p class="staff-data-note">Data identitas hanya dapat diakses petugas berwenang. Pastikan identitas pasien diverifikasi sebelum pendaftaran.</p>
            </div>
        </main>
    </div>
</body>
</html>
