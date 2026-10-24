<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pasien Rawat Inap | TechnoMedic</title>
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
                <a class="staff-navigation-link" href="{{ route('staff.nursing.assessment-queue.index') }}"><span class="staff-navigation-icon">◷</span>Antrean pemeriksaan awal</a>
                <a class="staff-navigation-link is-active" href="{{ route('staff.nursing.inpatients.index') }}" aria-current="page"><span class="staff-navigation-icon">＋</span>Pasien rawat inap</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>

        <main class="staff-main">
            <header class="staff-topbar">
                <div><span class="staff-topbar-kicker">MODUL PERAWAT</span><span class="staff-topbar-role">Pasien rawat inap</span></div>
                <span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">PEMANTAUAN RAWAT INAP</p><h1>Pasien rawat inap</h1><p>Pantau kondisi pasien dan catat perkembangan serta tanda vital.</p></div>
                    <div><span class="staff-live-indicator"><span></span>Realtime</span><span id="nursing-refresh-status" aria-live="polite">Pembaruan setiap 8 detik</span></div>
                </section>

                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel">
                    <form class="staff-search-form" action="{{ route('staff.nursing.inpatients.index') }}" method="get">
                        <label class="staff-sr-only" for="inpatient-search">Cari pasien rawat inap</label>
                        <input id="inpatient-search" name="search" type="search" value="{{ $search }}" placeholder="Cari nama, nomor rekam medis, kamar, atau tempat tidur">
                        <button class="staff-secondary-button" type="submit">Cari pasien</button>
                        @if ($search !== '')<a class="staff-text-link" href="{{ route('staff.nursing.inpatients.index') }}">Hapus pencarian</a>@endif
                    </form>
                    <div id="nursing-inpatients-list">
                        @include('staff.nursing.partials.inpatients', ['admissions' => $admissions])
                    </div>
                    @if ($admissions->hasPages())<div class="staff-pagination">{{ $admissions->links() }}</div>@endif
                </section>
                <p class="staff-data-note">Kondisi dan tanda vital yang Anda catat diperbarui otomatis pada daftar ini dan dashboard perawat setiap 8 detik. Catatan merupakan dokumentasi klinis dan tidak menggantikan penilaian profesional.</p>
            </div>
        </main>
    </div>
    <script>
        const inpatientsList = document.getElementById('nursing-inpatients-list');
        const refreshStatus = document.getElementById('nursing-refresh-status');

        window.setInterval(async () => {
            try {
                const response = await fetch(@json(route('staff.nursing.inpatients.refresh', ['search' => $search, 'page' => $admissions->currentPage()])), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Daftar pasien tidak dapat diperbarui.');
                }

                inpatientsList.innerHTML = await response.text();
                refreshStatus.textContent = `Diperbarui ${new Date().toLocaleTimeString('id-ID')}`;
            } catch {
                refreshStatus.textContent = 'Pembaruan otomatis gagal. Muat ulang halaman untuk mencoba lagi.';
            }
        }, 8000);
    </script>
</body>
</html>
