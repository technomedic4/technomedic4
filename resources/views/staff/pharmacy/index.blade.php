<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmasi | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>
            <div class="staff-sidebar-user">
                <span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span>
                <span class="staff-user-copy"><strong>{{ auth()->user()->email }}</strong><span>Farmasi</span></span>
            </div>
            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
                <span class="staff-navigation-label staff-navigation-spaced">MODUL FARMASI</span>
                <a class="staff-navigation-link is-active" href="{{ route('staff.pharmacy.index') }}" aria-current="page"><span class="staff-navigation-icon">✚</span>Resep pasien</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">
                @csrf
                <button class="staff-logout-button" type="submit">Keluar dari portal</button>
            </form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL FARMASI</span><span class="staff-topbar-role">Resep pasien</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">PENYERAHAN OBAT</p><h1>Antrean resep pasien</h1><p>Farmasi hanya melihat rincian resep. Obat dapat diserahkan setelah keuangan mengonfirmasi tagihan lunas.</p></div>
                </section>

                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel">
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Pasien / dokter</th><th>Diagnosis</th><th>Resep obat</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($prescriptions as $prescription)
                                    @php
                                        $prescriptionItems = $itemsByPrescriptionId->get($prescription->id, collect());
                                        [$statusLabel, $statusClass] = match (true) {
                                            $prescription->dispensed_at !== null => ['Sudah diserahkan', 'is-completed'],
                                            $prescription->bill_status === 'Paid' => ['Siap diserahkan', 'is-paid'],
                                            $prescription->bill_status === 'Cancelled' => ['Tagihan dibatalkan', 'is-cancelled'],
                                            $prescription->bill_status === 'Partially Paid' => ['Menunggu pelunasan', 'is-partial'],
                                            default => ['Menunggu pembayaran', 'is-unpaid'],
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong class="staff-patient-name">{{ $prescription->patient_name }}</strong>
                                            <span class="staff-booking-code">{{ $prescription->medical_record_number ?: 'No. RM belum tersedia' }}</span>
                                            <span class="staff-booking-code">Dokter: {{ $prescription->doctor_name }}</span>
                                            <span class="staff-booking-code">{{ \Illuminate\Support\Carbon::parse($prescription->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}</span>
                                        </td>
                                        <td>{{ $prescription->diagnosis }}</td>
                                        <td>
                                            @foreach ($prescriptionItems as $item)
                                                <div class="staff-record-field">
                                                    <strong>{{ $item->medicine_name }}{{ $item->strength ? ' '.$item->strength : '' }}{{ $item->dosage_form ? ' · '.$item->dosage_form : '' }}</strong>
                                                    <p>{{ $item->quantity }} {{ $item->unit }} · {{ $item->dosage }} · {{ $item->instructions }}</p>
                                                </div>
                                            @endforeach
                                        </td>
                                        <td>
                                            <span class="staff-status {{ $statusClass }}">{{ $statusLabel }}</span>
                                            @if ($prescription->dispensed_at)
                                                <span class="staff-booking-code">Diserahkan {{ \Illuminate\Support\Carbon::parse($prescription->dispensed_at)->locale('id')->translatedFormat('d F Y, H:i') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($prescription->bill_status === 'Paid' && $prescription->dispensed_at === null)
                                                <form action="{{ route('staff.pharmacy.prescriptions.dispense', $prescription->id) }}" method="post">
                                                    @csrf
                                                    <button class="staff-action-button" type="submit">Konfirmasi obat diserahkan</button>
                                                </form>
                                            @else
                                                <span class="staff-booking-code">Belum dapat diserahkan</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="staff-table-empty">Belum ada resep pasien.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($prescriptions->hasPages())<div class="staff-pagination">{{ $prescriptions->links() }}</div>@endif
                </section>
                <p class="staff-data-note">Status siap diserahkan hanya muncul setelah bagian keuangan mencatat pembayaran lunas pada tagihan pasien.</p>
            </div>
        </main>
    </div>
</body>
</html>
