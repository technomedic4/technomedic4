<aside class="staff-sidebar">
    <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
    <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>
    <div class="staff-sidebar-user">
        <span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span>
        <span class="staff-user-copy"><strong>{{ auth()->user()->email }}</strong><span>{{ $roleLabel }}</span></span>
    </div>
    <nav class="staff-navigation" aria-label="Navigasi portal staf">
        <span class="staff-navigation-label">MENU UTAMA</span>
        <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
        <span class="staff-navigation-label staff-navigation-spaced">MODUL PENAGIHAN</span>
        <a class="staff-navigation-link {{ $activePage === 'index' ? 'is-active' : '' }}" href="{{ route('staff.billing.index') }}" @if ($activePage === 'index') aria-current="page" @endif><span class="staff-navigation-icon">▣</span>Tagihan</a>
    </nav>
    <form class="staff-logout-form" action="{{ route('logout') }}" method="post">
        @csrf
        <button class="staff-logout-button" type="submit">Keluar dari portal</button>
    </form>
</aside>
