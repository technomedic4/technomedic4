<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Permintaan Laboratorium | TechnoMedic</title>
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
                <a class="staff-navigation-link" href="{{ route('staff.laboratory.requests.index') }}"><span class="staff-navigation-icon">▤</span>Semua permintaan</a>
                <a class="staff-navigation-link {{ $specimen === 'blood' ? 'is-active' : '' }}" href="{{ route('staff.laboratory.requests.blood') }}"><span class="staff-navigation-icon">◉</span>Pemeriksaan lab darah</a>
                <a class="staff-navigation-link {{ $specimen === 'urine' ? 'is-active' : '' }}" href="{{ route('staff.laboratory.requests.urine') }}"><span class="staff-navigation-icon">◉</span>Pemeriksaan lab urine</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL LABORATORIUM</span><span class="staff-topbar-role">{{ $specimen === 'blood' ? 'Pemeriksaan lab darah' : 'Pemeriksaan lab urine' }}</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">RUJUKAN LABORATORIUM</p><h1>{{ $order->patient_name }}</h1><p>{{ $order->medical_record_number ?: 'Nomor rekam medis belum tersedia' }} · {{ $order->referring_doctor_name }} · {{ $order->referring_department_name }}</p></div>
                    <a class="staff-secondary-button staff-button-link" href="{{ route($specimen === 'blood' ? 'staff.laboratory.requests.blood' : 'staff.laboratory.requests.urine') }}">Kembali ke antrean</a>
                </section>

                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel staff-lab-request">
                    <div class="staff-panel-heading"><div><h2>Informasi pemeriksaan</h2><p>Permintaan dibuat {{ \Illuminate\Support\Carbon::parse($order->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}.</p></div><span class="staff-status {{ $order->status === 'Completed' ? 'is-completed' : ($order->status === 'In Progress' ? 'is-arrived' : 'is-pending') }}">{{ match ($order->status) { 'Completed' => 'Selesai', 'In Progress' => 'Diproses', default => 'Menunggu' } }}</span></div>
                    <div class="staff-lab-details">
                        <div class="staff-record-field">
                            <span>Jenis pemeriksaan yang diminta</span>
                            @forelse ($requestedTestGroups as $requestedSpecimen)
                                <div class="staff-lab-result-group">
                                    <strong>{{ $requestedSpecimen['label'] }}</strong>
                                    @foreach ($requestedSpecimen['groups'] as $group)
                                        <p>{{ $group['label'] }}: {{ implode(', ', $group['tests']) }}</p>
                                    @endforeach
                                </div>
                            @empty
                                <p class="staff-pre-line">{{ $order->requested_tests }}</p>
                            @endforelse
                        </div>
                        <div class="staff-record-field"><span>Informasi klinis</span><p>{{ $order->clinical_notes ?: 'Tidak ada informasi klinis tambahan.' }}</p></div>
                        <div class="staff-record-field"><span>Jenis kelamin / tanggal lahir</span><p>{{ $order->gender === 'L' ? 'Laki-laki' : ($order->gender === 'P' ? 'Perempuan' : '—') }} / {{ $order->birth_date ? \Illuminate\Support\Carbon::parse($order->birth_date)->format('d-m-Y') : '—' }}</p></div>
                        <div class="staff-record-field"><span>Dokter pengirim</span><p>{{ $order->referring_doctor_name }} · {{ $order->referring_department_name }}</p></div>
                    </div>
                </section>

                @if ($order->status === 'Pending')
                    <section class="staff-form-panel">
                        <div class="staff-panel-heading"><div><h2>Mulai pemeriksaan {{ $specimen === 'blood' ? 'darah' : 'urine' }}</h2><p>Ubah status permintaan menjadi diproses saat pengerjaan dimulai.</p></div></div>
                        <form class="staff-form-actions" action="{{ route('staff.laboratory.requests.start', $order->id) }}" method="post">
                            @csrf
                            <input type="hidden" name="specimen" value="{{ $specimen }}">
                            <button class="staff-action-button" type="submit">Mulai pemeriksaan</button>
                        </form>
                    </section>
                @elseif ($order->status === 'In Progress' && ! $specimenComplete)
                    <section class="staff-form-panel">
                        <div class="staff-panel-heading"><div><h2>Form pemeriksaan {{ $specimen === 'blood' ? 'darah' : 'urine' }}</h2><p>Form hanya menampilkan parameter {{ $specimen === 'blood' ? 'darah' : 'urine' }} yang diminta dokter. Parameter sampel lain diisi pada antrean terpisah.</p></div></div>
                        <form class="staff-data-form" action="{{ route('staff.laboratory.requests.result.store', $order->id) }}" method="post">
                            @csrf
                            <input type="hidden" name="specimen" value="{{ $specimen }}">
                            <div class="staff-form-grid">
                                @if ($specimenTestGroups !== [])
                                    @foreach ($specimenTestGroups as $specimenGroup)
                                        <div class="staff-form-field staff-field-wide">
                                            <span>{{ $specimenGroup['label'] }} — hasil parameter yang diminta <span>*</span></span>
                                            @foreach ($specimenGroup['groups'] as $group)
                                                <strong>{{ $group['label'] }}</strong>
                                                <div class="staff-table-wrap">
                                                    <table class="staff-table">
                                                        <thead><tr><th>Parameter</th><th>Hasil</th><th>Satuan</th><th>Nilai rujukan</th>@if ($specimen === 'blood')<th>Keterangan</th>@endif</tr></thead>
                                                        <tbody>
                                                            @foreach ($group['tests'] as $code => $label)
                                                                <tr>
                                                                    <td><strong>{{ $label }}</strong></td>
                                                                    <td><input name="parameter_results[{{ $code }}][value]" value="{{ old("parameter_results.{$code}.value") }}" maxlength="100" required aria-label="Hasil {{ $label }}"></td>
                                                                    <td><input name="parameter_results[{{ $code }}][unit]" value="{{ old("parameter_results.{$code}.unit") }}" maxlength="40" aria-label="Unit {{ $label }}"></td>
                                                                    <td><input name="parameter_results[{{ $code }}][reference_range]" value="{{ old("parameter_results.{$code}.reference_range") }}" maxlength="100" aria-label="Nilai rujukan {{ $label }}"></td>
                                                                    @if ($specimen === 'blood')
                                                                        <td><select name="parameter_results[{{ $code }}][interpretation]" required aria-label="Keterangan {{ $label }}">
                                                                            <option value="">Pilih</option>
                                                                            @foreach (['Low', 'Normal', 'High'] as $interpretation)
                                                                                <option value="{{ $interpretation }}" @selected(old("parameter_results.{$code}.interpretation") === $interpretation)>{{ $interpretation === 'Low' ? 'Rendah' : ($interpretation === 'High' ? 'Tinggi' : 'Normal') }}</option>
                                                                            @endforeach
                                                                        </select></td>
                                                                    @endif
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                @else
                                    <div class="staff-form-field staff-field-wide">
                                        <label for="results">Hasil pemeriksaan {{ $specimen === 'blood' ? 'darah' : 'urine' }} <span>*</span></label>
                                        <textarea id="results" name="results" rows="8" maxlength="10000" required>{{ old('results') }}</textarea>
                                    </div>
                                @endif
                                <div class="staff-form-field staff-field-wide">
                                    <label for="result_notes">Kesimpulan / catatan laboratorium <span class="staff-optional-label">Opsional</span></label>
                                    <textarea id="result_notes" name="result_notes" rows="4" maxlength="3000">{{ old('result_notes') }}</textarea>
                                </div>
                            </div>
                            <div class="staff-form-actions"><button class="staff-action-button" type="submit">Simpan & kirim hasil ke dokter</button></div>
                        </form>
                    </section>
                @elseif ($order->status === 'In Progress')
                    <section class="staff-panel staff-lab-request">
                        <div class="staff-panel-heading"><div><h2>Hasil pemeriksaan {{ $specimen === 'blood' ? 'darah' : 'urine' }} tersimpan</h2><p>Jenis sampel lain masih perlu diperiksa sebelum hasil lengkap dikirim ke dokter.</p></div></div>
                        @include('staff.laboratory.requests.result-groups', ['groups' => $specimenResultGroups])
                        <div class="staff-form-actions">
                            @foreach ($remainingSpecimens as $remainingSpecimen)
                                <a class="staff-action-button staff-button-link" href="{{ route('staff.laboratory.requests.show', ['labOrder' => $order->id, 'specimen' => $remainingSpecimen]) }}">Lanjutkan pemeriksaan {{ $remainingSpecimen === 'blood' ? 'darah' : 'urine' }}</a>
                            @endforeach
                        </div>
                    </section>
                @else
                    <section class="staff-panel staff-lab-request">
                        <div class="staff-panel-heading"><div><h2>Hasil pemeriksaan {{ $specimen === 'blood' ? 'darah' : 'urine' }}</h2><p>Dikirim {{ \Illuminate\Support\Carbon::parse($order->resulted_at)->locale('id')->translatedFormat('d F Y, H:i') }} oleh {{ $order->resulted_by_email ?: 'petugas laboratorium' }}.</p></div></div>
                        <div class="staff-lab-details">
                            @if ($specimenResultGroups !== [])
                                @include('staff.laboratory.requests.result-groups', ['groups' => $specimenResultGroups])
                            @else
                                <div class="staff-record-field"><span>Hasil</span><p class="staff-pre-line">{{ $order->results }}</p></div>
                            @endif
                            <div class="staff-record-field"><span>Kesimpulan / catatan laboratorium</span><p class="staff-pre-line">{{ $order->result_notes ?: 'Tidak ada catatan tambahan.' }}</p></div>
                        </div>
                    </section>
                @endif
            </div>
        </main>
    </div>
</body>
</html>
