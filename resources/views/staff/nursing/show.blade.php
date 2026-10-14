<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catat Kondisi Pasien | TechnoMedic</title>
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
                <a class="staff-navigation-link is-active" href="{{ route('staff.nursing.inpatients.index') }}"><span class="staff-navigation-icon">＋</span>Pasien rawat inap</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>

        <main class="staff-main">
            <header class="staff-topbar">
                <div><span class="staff-topbar-kicker">MODUL PERAWAT</span><span class="staff-topbar-role">Catat kondisi pasien</span></div>
                <span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span>
            </header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">PEMANTAUAN RAWAT INAP</p><h1>{{ $admission->patient_name }}</h1><p>{{ $admission->medical_record_number ?: 'Nomor rekam medis belum tersedia' }} · Kamar {{ $admission->room_number ?: '—' }} · Tempat tidur {{ $admission->bed_number ?: '—' }}</p></div>
                    <a class="staff-secondary-button staff-button-link" href="{{ route('staff.nursing.inpatients.index') }}">Kembali ke pasien rawat inap</a>
                </section>

                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel staff-encounter-summary">
                    <div class="staff-panel-heading"><div><h2>Ringkasan rawat inap</h2><p>Masuk {{ \Illuminate\Support\Carbon::parse($admission->admitted_at)->locale('id')->translatedFormat('d F Y, H:i') }}</p></div><span class="staff-status is-arrived">Sedang dirawat</span></div>
                    <div class="staff-encounter-grid">
                        <div><span class="staff-stat-label">JENIS KELAMIN</span><strong>{{ $admission->gender === 'L' ? 'Laki-laki' : ($admission->gender === 'P' ? 'Perempuan' : 'Belum dicatat') }}</strong></div>
                        <div><span class="staff-stat-label">TANGGAL LAHIR</span><strong>{{ $admission->birth_date ? \Illuminate\Support\Carbon::parse($admission->birth_date)->locale('id')->translatedFormat('d F Y') : 'Belum dicatat' }}</strong></div>
                    </div>
                    <div class="staff-encounter-complaint"><span class="staff-stat-label">ALASAN RAWAT INAP</span><p>{{ $admission->admission_reason }}</p></div>
                </section>

                <section class="staff-form-panel">
                    <div class="staff-panel-heading"><div><h2>Catat kondisi terkini</h2><p>Isi tanda vital yang diukur dan catatan observasi sesuai pemeriksaan.</p></div></div>
                    <form class="staff-data-form" action="{{ route('staff.nursing.inpatients.updates.store', $admission->id) }}" method="post">
                        @csrf
                        <div class="staff-form-grid">
                            <div class="staff-form-field staff-field-wide">
                                <label for="update_status">Kondisi pasien <span>*</span></label>
                                <select id="update_status" name="update_status" required>
                                    <option value="">Pilih kondisi</option>
                                    @foreach ($updateStatuses as $status)
                                        <option value="{{ $status }}" @selected(old('update_status') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="staff-form-field">
                                <label for="temperature_celsius">Suhu (°C)</label>
                                <input id="temperature_celsius" name="temperature_celsius" type="number" min="30" max="45" step="0.1" value="{{ old('temperature_celsius') }}">
                            </div>
                            <div class="staff-form-field">
                                <label for="blood_pressure">Tekanan darah (mmHg)</label>
                                <input id="blood_pressure" name="blood_pressure" type="text" maxlength="15" pattern="[0-9]{2,3}/[0-9]{2,3}" placeholder="Contoh: 120/80" value="{{ old('blood_pressure') }}">
                            </div>
                            <div class="staff-form-field">
                                <label for="pulse_rate">Nadi (x/menit)</label>
                                <input id="pulse_rate" name="pulse_rate" type="number" min="20" max="250" value="{{ old('pulse_rate') }}">
                            </div>
                            <div class="staff-form-field">
                                <label for="respiratory_rate">Frekuensi napas (x/menit)</label>
                                <input id="respiratory_rate" name="respiratory_rate" type="number" min="5" max="60" value="{{ old('respiratory_rate') }}">
                            </div>
                            <div class="staff-form-field">
                                <label for="oxygen_saturation">Saturasi oksigen (%)</label>
                                <input id="oxygen_saturation" name="oxygen_saturation" type="number" min="50" max="100" value="{{ old('oxygen_saturation') }}">
                            </div>
                            <div class="staff-form-field staff-field-wide">
                                <label for="notes">Catatan observasi / tindakan <span>*</span></label>
                                <textarea id="notes" name="notes" rows="4" maxlength="3000" required>{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        <div class="staff-form-actions">
                            <a class="staff-cancel-link" href="{{ route('staff.nursing.inpatients.index') }}">Batal</a>
                            <button class="staff-action-button" type="submit">Simpan pembaruan kondisi</button>
                        </div>
                    </form>
                </section>

                <section class="staff-panel">
                    <div class="staff-panel-heading">
                        <div><h2>Riwayat kondisi pasien</h2><p>Riwayat diperbarui otomatis setiap 8 detik.</p></div>
                        <div><span class="staff-live-indicator"><span></span>Realtime</span><span id="care-updates-status" aria-live="polite">Pembaruan setiap 8 detik</span></div>
                    </div>
                    <div id="care-updates-list">
                        @include('staff.nursing.partials.care-updates', ['updates' => $updates])
                    </div>
                </section>
                <p class="staff-data-note">Catatan kondisi tersimpan sebagai riwayat dan tidak dapat diubah dari layar ini. Jika ada perubahan, simpan catatan baru agar riwayat tetap utuh.</p>
            </div>
        </main>
    </div>
    <script>
        const careUpdatesList = document.getElementById('care-updates-list');
        const careUpdatesStatus = document.getElementById('care-updates-status');

        window.setInterval(async () => {
            try {
                const response = await fetch(@json(route('staff.nursing.inpatients.updates', $admission->id)), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    throw new Error('Riwayat kondisi tidak dapat diperbarui.');
                }

                careUpdatesList.innerHTML = await response.text();
                careUpdatesStatus.textContent = `Diperbarui ${new Date().toLocaleTimeString('id-ID')}`;
            } catch {
                careUpdatesStatus.textContent = 'Pembaruan otomatis gagal. Muat ulang halaman untuk mencoba lagi.';
            }
        }, 8000);
    </script>
</body>
</html>
