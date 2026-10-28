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
                            <label class="staff-sr-only" for="treatment-patient">Pilih pasien untuk memuat tindakan</label>
                            <select id="treatment-patient" name="patient_id">
                                <option value="">Pilih pasien untuk melihat tindakan</option>
                                @foreach ($patients as $patient)
                                    <option value="{{ $patient->id }}" @selected((string) $selectedPatientId === (string) $patient->id)>{{ $patient->full_name }} · {{ $patient->medical_record_number ?: 'No. RM belum tersedia' }}</option>
                                @endforeach
                            </select>
                            <button class="staff-secondary-button" type="submit">Muat tindakan pasien</button>
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
                                        <option value="{{ $patient->id }}" @selected((string) old('patient_id', $bill?->patient_id ?? $selectedPatientId) === (string) $patient->id)>{{ $patient->full_name }} · {{ $patient->medical_record_number ?: 'No. RM belum tersedia' }}</option>
                                    @endforeach
                                </select>
                                @if ($patients->isEmpty())<small>Tidak ada pasien yang cocok. Cari pasien lain atau daftarkan pasien terlebih dahulu.</small>@endif
                            </div>
                        </div>
                    </section>

                    @php
                        $selectedNursingTreatmentIds = collect(old('items', $formItems))
                            ->pluck('nursing_treatment_id')
                            ->filter()
                            ->map(static fn ($id): string => (string) $id)
                            ->all();
                    @endphp
                    <section class="staff-panel">
                        <div class="staff-panel-heading">
                            <div>
                                    <h2>Bukti tindakan dari perawat</h2>
                                    <p>Tindakan dipilih sebagai dasar tagihan. Nominal diisi petugas billing yang berwenang, bukan perawat.</p>
                            </div>
                        </div>
                        @if ($nursingTreatments->isEmpty())
                            <p class="staff-table-empty">{{ $selectedPatientId ? 'Tidak ada tindakan rawat inap yang menunggu tagihan.' : 'Pilih pasien di atas untuk melihat tindakan yang menunggu tagihan.' }}</p>
                        @else
                            <div class="staff-table-wrap">
                                    <table class="staff-table">
                                        <thead><tr><th>Pilih</th><th>Tanggal</th><th>Tindakan</th><th>Catatan</th><th>Kamar</th><th>Dicatat oleh</th></tr></thead>
                                        <tbody>
                                            @foreach ($nursingTreatments as $treatment)
                                                <tr>
                                                    <td><input type="checkbox" value="{{ $treatment->id }}" data-nursing-treatment data-treatment-name="{{ $treatment->treatment_name }}" @checked(in_array((string) $treatment->id, $selectedNursingTreatmentIds, true))></td>
                                                    <td>{{ \Illuminate\Support\Carbon::parse($treatment->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}</td>
                                                    <td>{{ $treatment->treatment_name }}</td>
                                                    <td>{{ $treatment->notes ?: '—' }}</td>
                                                    <td>{{ $treatment->room_number ?: '—' }} / {{ $treatment->bed_number ?: '—' }}</td>
                                                    <td>{{ $treatment->recorded_by ?: '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                            </div>
                            <div class="staff-form-actions">
                                    <button class="staff-secondary-button" type="button" data-add-nursing-treatments>Tambahkan tindakan terpilih</button>
                            </div>
                        @endif
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
    <script>
        (() => {
            const list = document.querySelector('[data-bill-items]');
            const template = document.querySelector('[data-bill-item-template]');
            const addButton = document.querySelector('[data-add-nursing-treatments]');

            if (!list || !template || !addButton) {
                return;
            }

            list.querySelectorAll('[data-nursing-treatment-id]').forEach((input) => {
                if (input.value !== '') {
                    input.closest('.staff-billing-item-row').querySelector('[name$="[item_name]"]').readOnly = true;
                    const checkbox = document.querySelector(`[data-nursing-treatment][value="${input.value}"]`);
                    if (checkbox) {
                        checkbox.checked = false;
                        checkbox.disabled = true;
                    }
                }
            });

            addButton.addEventListener('click', () => {
                const checkedTreatments = document.querySelectorAll('[data-nursing-treatment]:checked');
                if (checkedTreatments.length === 0) {
                    return;
                }

                Array.from(list.children)
                    .filter((row) => row.querySelector('[name$="[item_name]"]').value.trim() === ''
                        && row.querySelector('[data-nursing-treatment-id]').value === '')
                    .forEach((row) => row.remove());
                let nextIndex = Array.from(list.children).reduce(
                    (next, row) => Math.max(next, Number(row.dataset.itemIndex) + 1),
                    0,
                );

                checkedTreatments.forEach((checkbox) => {
                    const itemId = checkbox.value;
                    if (list.querySelector(`[data-nursing-treatment-id][value="${itemId}"]`)) {
                        return;
                    }

                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
                    const row = wrapper.firstElementChild;
                    row.querySelector('[name$="[item_name]"]').value = checkbox.dataset.treatmentName;
                    row.querySelector('[name$="[item_name]"]').readOnly = true;
                    row.querySelector('[data-nursing-treatment-id]').value = itemId;
                    list.appendChild(row);
                    checkbox.checked = false;
                    checkbox.disabled = true;
                });
            });

            list.addEventListener('click', (event) => {
                const removeButton = event.target.closest('[data-remove-bill-item]');
                if (!removeButton) {
                    return;
                }

                const row = removeButton.closest('.staff-billing-item-row');
                const treatmentId = row.querySelector('[data-nursing-treatment-id]')?.value;
                if (treatmentId) {
                    const checkbox = document.querySelector(`[data-nursing-treatment][value="${treatmentId}"]`);
                    if (checkbox) {
                        checkbox.disabled = false;
                    }
                }
            });
        })();
    </script>
</body>
</html>
