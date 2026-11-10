<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permintaan Laboratorium | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>
            <div class="staff-sidebar-user"><span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span><span class="staff-user-copy"><strong>{{ auth()->user()->email }}</strong><span>Laboratorium</span></span></div>
            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
                <span class="staff-navigation-label staff-navigation-spaced">MODUL LABORATORIUM</span>
                <a class="staff-navigation-link {{ $specimen === null ? 'is-active' : '' }}" href="{{ route('staff.laboratory.requests.index') }}" @if ($specimen === null) aria-current="page" @endif><span class="staff-navigation-icon">▤</span>Semua permintaan</a>
                <a class="staff-navigation-link {{ $specimen === 'blood' ? 'is-active' : '' }}" href="{{ route('staff.laboratory.requests.blood') }}" @if ($specimen === 'blood') aria-current="page" @endif><span class="staff-navigation-icon">◉</span>Pemeriksaan lab darah</a>
                <a class="staff-navigation-link {{ $specimen === 'urine' ? 'is-active' : '' }}" href="{{ route('staff.laboratory.requests.urine') }}" @if ($specimen === 'urine') aria-current="page" @endif><span class="staff-navigation-icon">◉</span>Pemeriksaan lab urine</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL LABORATORIUM</span><span class="staff-topbar-role">Permintaan pemeriksaan</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">RUJUKAN DOKTER</p><h1>{{ $specimen === 'blood' ? 'Antrean pemeriksaan lab darah' : ($specimen === 'urine' ? 'Antrean pemeriksaan lab urine' : 'Semua permintaan laboratorium') }}</h1><p>{{ $specimen === null ? 'Pantau semua rujukan dan pilih antrean pemeriksaan darah atau urine.' : 'Kelola antrean pemeriksaan ini dan kirim hasil kembali kepada dokter perujuk.' }}</p></div>
                </section>
                <section class="staff-stat-grid" aria-label="Ringkasan permintaan laboratorium">
                    <article class="staff-stat-card"><span class="staff-stat-label">Total permintaan</span><strong class="staff-stat-value">{{ $statistics->total }}</strong><span class="staff-stat-description">Seluruh rujukan laboratorium</span></article>
                    <article class="staff-stat-card"><span class="staff-stat-label">Menunggu</span><strong class="staff-stat-value">{{ $statistics->pending }}</strong><span class="staff-stat-description">Belum mulai diperiksa</span></article>
                    <article class="staff-stat-card"><span class="staff-stat-label">Diproses</span><strong class="staff-stat-value">{{ $statistics->in_progress }}</strong><span class="staff-stat-description">Sedang dikerjakan</span></article>
                    <article class="staff-stat-card"><span class="staff-stat-label">Selesai</span><strong class="staff-stat-value">{{ $statistics->completed }}</strong><span class="staff-stat-description">Hasil telah dikirim ke dokter</span></article>
                </section>
                @if ($specimen === null)
                    <section class="staff-lab-feature-grid" aria-label="Antrean pemeriksaan berdasarkan sampel">
                        <a class="staff-lab-feature-card" href="{{ route('staff.laboratory.requests.blood') }}">
                            <span class="staff-lab-feature-icon">＋</span>
                            <span><strong>Pemeriksaan lab darah</strong><small>{{ $specimenCounts['blood'] }} permintaan · Hematologi dan kimia darah</small></span>
                            <span class="staff-lab-feature-arrow">Lihat antrean →</span>
                        </a>
                        <a class="staff-lab-feature-card" href="{{ route('staff.laboratory.requests.urine') }}">
                            <span class="staff-lab-feature-icon">◉</span>
                            <span><strong>Pemeriksaan lab urine</strong><small>{{ $specimenCounts['urine'] }} permintaan · Fisik, kimia, dan mikroskopis</small></span>
                            <span class="staff-lab-feature-arrow">Lihat antrean →</span>
                        </a>
                    </section>
                @endif
                <section class="staff-panel">
                    <form class="staff-search-form" action="{{ $specimen === 'blood' ? route('staff.laboratory.requests.blood') : ($specimen === 'urine' ? route('staff.laboratory.requests.urine') : route('staff.laboratory.requests.index')) }}" method="get">
                        <label class="staff-sr-only" for="search">Cari pasien, nomor rekam medis, atau pemeriksaan</label>
                        <input id="search" name="search" type="search" value="{{ $search }}" placeholder="Cari nama pasien, No. RM, atau pemeriksaan">
                        <label class="staff-sr-only" for="status">Filter status</label>
                        <select id="status" name="status">
                            <option value="">Semua status</option>
                            <option value="Pending" @selected($status === 'Pending')>Menunggu</option>
                            <option value="In Progress" @selected($status === 'In Progress')>Diproses</option>
                            <option value="Completed" @selected($status === 'Completed')>Selesai</option>
                        </select>
                        <button class="staff-secondary-button" type="submit">Cari / filter</button>
                    </form>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Permintaan</th><th>Pasien</th><th>Dokter perujuk</th><th>Pemeriksaan</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($labOrders as $order)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($order->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}<span class="staff-booking-code">{{ $order->booking_code }}</span></td>
                                        <td><strong class="staff-patient-name">{{ $order->patient_name }}</strong><span class="staff-booking-code">{{ $order->medical_record_number ?: 'No. RM belum tersedia' }}</span></td>
                                        <td>{{ $order->referring_doctor_name }}<span class="staff-booking-code">{{ $order->referring_department_name }}</span></td>
                                        <td class="staff-complaint-cell staff-pre-line">{{ $order->requested_tests }}</td>
                                        <td><span class="staff-status {{ $order->status === 'Completed' ? 'is-completed' : ($order->status === 'In Progress' ? 'is-arrived' : 'is-pending') }}">{{ match ($order->status) { 'Completed' => 'Selesai', 'In Progress' => 'Diproses', default => 'Menunggu' } }}</span></td>
                                        <td>
                                            @php($orderTypes = $specimen === null ? $orderSpecimens[$order->id] : [$specimen])
                                            @foreach ($orderTypes as $orderType)
                                                <a class="staff-inline-action staff-lab-queue-action" href="{{ route('staff.laboratory.requests.show', ['labOrder' => $order->id, 'specimen' => $orderType]) }}">{{ $orderType === 'blood' ? 'Darah' : 'Urine' }} · {{ $order->status === 'Completed' ? 'Lihat hasil' : ($order->status === 'In Progress' ? 'Isi hasil' : 'Mulai') }}</a>
                                            @endforeach
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="staff-table-empty">Belum ada permintaan pemeriksaan laboratorium.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($labOrders->hasPages())<div class="staff-pagination">{{ $labOrders->links() }}</div>@endif
                </section>
            </div>
        </main>
    </div>
</body>
</html>
