<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrean Pemeriksaan Awal | TechnoMedic</title>
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
                <div><span class="staff-topbar-kicker">MODUL PERAWAT</span><span class="staff-topbar-role">Antrean pemeriksaan awal</span></div>
                <span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">PEMERIKSAAN AWAL PASIEN</p><h1>Antrean pasien check-in</h1><p>Semua pasien rawat jalan maupun calon rawat inap diperiksa perawat sebelum rekam medis diteruskan ke dokter.</p></div>
                    <div><span class="staff-live-indicator"><span></span>Realtime</span><span id="outpatient-refresh-status" aria-live="polite">Pembaruan setiap 8 detik</span></div>
                </section>

                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel">
                    <form class="staff-search-form staff-filter-form" action="{{ route('staff.nursing.assessment-queue.index') }}" method="get">
                        <div class="staff-filter-field"><label for="outpatient-date">Tanggal kunjungan</label><input id="outpatient-date" name="date" type="date" value="{{ $date }}"></div>
                        <div class="staff-filter-field"><label for="outpatient-search">Cari pasien</label><input id="outpatient-search" name="search" type="search" value="{{ $search }}" placeholder="Nama, nomor rekam medis, atau kode booking"></div>
                        <button class="staff-secondary-button" type="submit">Tampilkan</button>
                    </form>
                    <div id="nursing-outpatients-list">
                        @include('staff.nursing.partials.assessment-queue', ['appointments' => $appointments])
                    </div>
                    @if ($appointments->hasPages())<div class="staff-pagination">{{ $appointments->links() }}</div>@endif
                </section>
                <p class="staff-data-note">Setelah pemeriksaan lanjutan, dokter menentukan rawat jalan atau rawat inap. Pilihan rawat inap akan otomatis tercatat untuk dilengkapi resepsionis.</p>
            </div>
        </main>
    </div>
    <script>
        const assessmentQueueList = document.getElementById('nursing-outpatients-list');
        const refreshStatus = document.getElementById('outpatient-refresh-status');

        window.setInterval(async () => {
            try {
                const response = await fetch(@json($refreshUrl), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Antrean pasien tidak dapat diperbarui.');
                }

                assessmentQueueList.innerHTML = await response.text();
                refreshStatus.textContent = `Diperbarui ${new Date().toLocaleTimeString('id-ID')}`;
            } catch {
                refreshStatus.textContent = 'Pembaruan otomatis gagal. Muat ulang halaman untuk mencoba lagi.';
            }
        }, 8000);
    </script>
</body>
</html>
