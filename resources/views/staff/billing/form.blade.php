<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $bill ? 'Edit tagihan' : 'Buat tagihan' }} | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        @include('staff.billing.partials.sidebar', ['activePage' => 'index'])
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL KEUANGAN</span><span class="staff-topbar-role">{{ $bill ? 'Edit tagihan' : 'Tagihan baru' }}</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">BILLING & PEMBAYARAN</p><h1>{{ $bill ? 'Edit tagihan '.$bill->bill_number : 'Buat tagihan' }}</h1><p>Pilih pasien dan masukkan setiap item biaya yang akan ditagihkan.</p></div>
                    <a class="staff-secondary-button staff-button-link" href="{{ $bill ? route('staff.billing.show', $bill->id) : route('staff.billing.index') }}">Kembali</a>
                </section>
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                @if (! $bill)
                    <section class="staff-panel">
                        <form class="staff-search-form" action="{{ route('staff.billing.create') }}" method="get">
                            <label class="staff-sr-only" for="patient-search">Cari pasien</label>
                            <input id="patient-search" name="search" type="search" value="{{ $search }}" placeholder="Cari nama atau nomor rekam medis pasien">
                            <button class="staff-secondary-button" type="submit">Cari pasien</button>
                        </form>
                    </section>
                @endif

                <form class="staff-data-form staff-billing-form" action="{{ $bill ? route('staff.billing.update', $bill->id) : route('staff.billing.store') }}" method="post">
                    @csrf
                    @if ($bill)@method('PUT')@endif
                    <section class="staff-form-panel">
                        <div class="staff-form-grid">
                            <div class="staff-form-field staff-field-wide">
                                <label for="patient_id">Pasien <span>*</span></label>
                                <select id="patient_id" name="patient_id" required>
                                    <option value="">Pilih pasien</option>
                                    @foreach ($patients as $patient)
                                        <option value="{{ $patient->id }}" @selected((string) old('patient_id', $bill?->patient_id) === (string) $patient->id)>{{ $patient->full_name }} · {{ $patient->medical_record_number ?: 'No. RM belum tersedia' }}</option>
                                    @endforeach
                                </select>
                                @if ($patients->isEmpty())<small>Tidak ada pasien yang cocok. Cari pasien lain atau daftarkan pasien terlebih dahulu.</small>@endif
                            </div>
                        </div>
                    </section>

                    <section class="staff-form-panel">
                        @include('staff.billing.partials.items-form', ['formItems' => $formItems])
                        <div class="staff-form-actions">
                            <a class="staff-cancel-link" href="{{ $bill ? route('staff.billing.show', $bill->id) : route('staff.billing.index') }}">Batal</a>
                            <button class="staff-action-button" type="submit">{{ $bill ? 'Simpan perubahan' : 'Buat tagihan' }}</button>
                        </div>
                    </section>
                </form>
                <p class="staff-data-note">Total tagihan dihitung dari jumlah dan harga setiap item. Status pembayaran akan mengikuti jumlah pembayaran yang dicatat.</p>
            </div>
        </main>
    </div>
</body>
</html>
