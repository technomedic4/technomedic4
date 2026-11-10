<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pemeriksaan Pasien | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ route('staff.dashboard') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">PORTAL RUMAH SAKIT</p>
            <div class="staff-sidebar-user"><span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span><span class="staff-user-copy"><strong>{{ auth()->user()->email }}</strong><span>Dokter</span></span></div>
            <nav class="staff-navigation" aria-label="Navigasi portal staf">
                <span class="staff-navigation-label">MENU UTAMA</span>
                <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard</a>
                <span class="staff-navigation-label staff-navigation-spaced">MODUL DOKTER</span>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.appointments.index') }}"><span class="staff-navigation-icon">◷</span>Jadwal & pasien</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.inpatients.index') }}"><span class="staff-navigation-icon">＋</span>Monitoring rawat inap</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.referrals.index') }}"><span class="staff-navigation-icon">↗</span>Pasien rujukan</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.laboratory-results.index') }}"><span class="staff-navigation-icon">▤</span>Hasil lab</a>
                <a class="staff-navigation-link is-active" href="{{ route('staff.medical-records.index') }}" aria-current="page"><span class="staff-navigation-icon">＋</span>Rekam medis</a>
                <a class="staff-navigation-link" href="{{ route('staff.doctor.teleconsultations.index') }}"><span class="staff-navigation-icon">✉</span>Telekonsultasi</a>
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar dari portal</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL DOKTER</span><span class="staff-topbar-role">Pemeriksaan pasien</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">CATATAN KONSULTASI</p><h1>Pemeriksaan pasien</h1><p>Catat hasil pemeriksaan dan rencana tindak lanjut kunjungan.</p></div>
                    <a class="staff-secondary-button staff-button-link" href="{{ route('staff.doctor.appointments.index', ['date' => $appointment->appointment_date]) }}">Kembali ke daftar pasien</a>
                </section>

                @if (session('success'))
                    <div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel staff-encounter-summary">
                    <div class="staff-panel-heading"><div><h2>{{ $appointment->patient_name }}</h2><p>{{ $appointment->medical_record_number ?: 'Nomor rekam medis belum tersedia' }} · {{ $appointment->department_name }} · {{ $appointment->doctor_name }}</p></div><span class="staff-status {{ $appointment->status === 'Completed' ? 'is-completed' : (in_array($appointment->status, ['Pending', 'Confirmed'], true) ? 'is-pending' : 'is-arrived') }}">{{ match ($appointment->status) { 'Completed' => 'Selesai', 'Pending' => 'Menunggu check-in', 'Confirmed' => 'Dikonfirmasi', default => 'Sudah check-in' } }}</span></div>
                    <div class="staff-encounter-grid">
                        <div><span class="staff-stat-label">NOMOR ANTREAN</span><strong>{{ str_pad((string) $appointment->queue_number, 3, '0', STR_PAD_LEFT) }}</strong></div>
                        <div><span class="staff-stat-label">TANGGAL & JAM</span><strong>{{ \Illuminate\Support\Carbon::parse($appointment->appointment_date)->locale('id')->translatedFormat('d F Y') }} · {{ substr($appointment->start_time, 0, 5) }}</strong></div>
                        <div><span class="staff-stat-label">JENIS KELAMIN</span><strong>{{ $appointment->gender === 'L' ? 'Laki-laki' : ($appointment->gender === 'P' ? 'Perempuan' : 'Belum dicatat') }}</strong></div>
                        <div><span class="staff-stat-label">TANGGAL LAHIR</span><strong>{{ $appointment->birth_date ? \Illuminate\Support\Carbon::parse($appointment->birth_date)->format('d-m-Y') : 'Belum dicatat' }}</strong></div>
                        <div><span class="staff-stat-label">TELEPON</span><strong>{{ $appointment->phone_number ?: 'Belum dicatat' }}</strong></div>
                    </div>
                    <div class="staff-encounter-complaint"><span class="staff-stat-label">KELUHAN AWAL DARI LOKET</span><p>{{ $appointment->initial_complaint ?: 'Belum ada keluhan awal yang dicatat.' }}</p></div>
                </section>

                @if ($referral)
                    <section class="staff-panel staff-encounter-summary">
                        <div class="staff-panel-heading"><div><h2>Informasi rujukan</h2><p>Dirujuk oleh {{ $referral->referring_doctor_name }} · {{ $referral->referring_department_name }}</p></div><span class="staff-status is-confirmed">Rujukan dokter</span></div>
                        <div class="staff-record-field"><span>Alasan rujukan</span><p>{{ $referral->referral_reason }}</p></div>
                        <div class="staff-record-field"><span>Hasil pemeriksaan dokter pengirim</span><p>{{ $referral->examination_findings }}</p></div>
                        <div class="staff-record-field"><span>Diagnosis dokter pengirim</span><p>{{ $referral->diagnosis }}</p></div>
                        <div class="staff-record-field"><span>Rencana tindakan / terapi sebelumnya</span><p>{{ $referral->treatment_plan }}</p></div>
                        @if ($referral->notes)
                            <div class="staff-record-field"><span>Catatan tambahan dokter pengirim</span><p>{{ $referral->notes }}</p></div>
                        @endif
                        @if ($referralNursingAssessment)
                            <div class="staff-record-field"><span>Tanda vital kunjungan sebelumnya</span><p>Suhu: {{ $referralNursingAssessment->temperature_celsius ?? '—' }} °C · Tekanan darah: {{ $referralNursingAssessment->blood_pressure ?: '—' }} · Nadi: {{ $referralNursingAssessment->pulse_rate ?: '—' }} · Napas: {{ $referralNursingAssessment->respiratory_rate ?: '—' }} · Saturasi: {{ $referralNursingAssessment->oxygen_saturation !== null ? $referralNursingAssessment->oxygen_saturation.'%' : '—' }}</p></div>
                            @if ($referralNursingAssessment->notes)
                                <div class="staff-record-field"><span>Catatan kondisi kunjungan sebelumnya</span><p>{{ $referralNursingAssessment->notes }}</p></div>
                            @endif
                        @endif
                    </section>
                @endif

                @if ($patientCondition)
                    <section class="staff-panel staff-encounter-summary">
                        <div class="staff-panel-heading">
                            <div><h2>Kondisi pasien & tanda vital</h2><p>Terakhir dicatat oleh {{ $patientCondition->recorded_by ?: 'petugas' }} pada {{ \Illuminate\Support\Carbon::parse($patientCondition->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}.</p></div>
                            <span class="staff-status is-confirmed">{{ $patientCondition->update_status }}</span>
                        </div>
                        <div class="staff-encounter-grid">
                            <div><span class="staff-stat-label">SUHU</span><strong>{{ $patientCondition->temperature_celsius !== null ? number_format((float) $patientCondition->temperature_celsius, 1).' °C' : '—' }}</strong></div>
                            <div><span class="staff-stat-label">TEKANAN DARAH</span><strong>{{ $patientCondition->blood_pressure ? $patientCondition->blood_pressure.' mmHg' : '—' }}</strong></div>
                            <div><span class="staff-stat-label">NADI</span><strong>{{ $patientCondition->pulse_rate !== null ? $patientCondition->pulse_rate.' x/menit' : '—' }}</strong></div>
                            <div><span class="staff-stat-label">FREKUENSI NAPAS</span><strong>{{ $patientCondition->respiratory_rate !== null ? $patientCondition->respiratory_rate.' x/menit' : '—' }}</strong></div>
                            <div><span class="staff-stat-label">SATURASI OKSIGEN</span><strong>{{ $patientCondition->oxygen_saturation !== null ? $patientCondition->oxygen_saturation.'%' : '—' }}</strong></div>
                        </div>
                        <div class="staff-encounter-complaint"><span class="staff-stat-label">CATATAN KONDISI PASIEN</span><p>{{ $patientCondition->notes }}</p></div>
                    </section>
                @elseif ($appointment->status === 'Completed')
                    <section class="staff-panel staff-encounter-summary">
                        <div class="staff-panel-heading"><div><h2>Kondisi pasien & tanda vital</h2><p>Data kondisi pasien tidak tersedia untuk kunjungan ini.</p></div></div>
                    </section>
                @endif

                @if ($appointment->status === 'Checked-In')
                    <section class="staff-form-panel">
                        <div class="staff-panel-heading"><div><h2>Validasi kondisi pasien</h2><p>Dokter menilai kondisi klinis berdasarkan tanda vital dan catatan awal yang dicatat perawat.</p></div></div>
                        @if ($patientCondition)
                            <form class="staff-data-form" action="{{ route('staff.doctor.appointments.patient-condition.store', $appointment->id) }}" method="post">
                                @csrf
                                <div class="staff-form-grid">
                                    <div class="staff-form-field staff-field-wide">
                                        <span class="staff-stat-label">TANDA VITAL DARI PERAWAT</span>
                                        <p>Suhu: {{ $patientCondition->temperature_celsius ?? '—' }} °C · Tekanan darah: {{ $patientCondition->blood_pressure ?: '—' }} · Nadi: {{ $patientCondition->pulse_rate ?: '—' }} · Napas: {{ $patientCondition->respiratory_rate ?: '—' }} · Saturasi: {{ $patientCondition->oxygen_saturation !== null ? $patientCondition->oxygen_saturation.'%' : '—' }}</p>
                                        <p>Catatan perawat: {{ $patientCondition->notes }}</p>
                                    </div>
                                    <div class="staff-form-field staff-field-wide">
                                        <label for="update_status">Kondisi pasien <span>*</span></label>
                                        <select id="update_status" name="update_status" required>
                                            <option value="">Pilih kondisi</option>
                                            @foreach ($patientConditionStatuses as $status)
                                                <option value="{{ $status }}" @selected(old('update_status', $patientCondition->update_status ?? '') === $status)>{{ $status }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="staff-form-field staff-field-wide">
                                        <label for="notes">Catatan kondisi pasien <span>*</span></label>
                                        <textarea id="notes" name="notes" rows="4" maxlength="3000" required>{{ old('notes', $patientCondition->recorded_by_user_id === $appointment->doctor_user_id ? $patientCondition->notes : '') }}</textarea>
                                    </div>
                                </div>
                                <div class="staff-form-actions"><button class="staff-action-button" type="submit">Simpan validasi kondisi</button></div>
                            </form>
                        @else
                            <p class="staff-data-note">Menunggu perawat mencatat tanda vital sebelum dokter dapat memvalidasi kondisi pasien.</p>
                        @endif
                    </section>
                @endif

                @if ($consultation)
                    <section class="staff-form-panel staff-consultation-record">
                        <div class="staff-panel-heading"><div><h2>Catatan konsultasi tersimpan</h2><p>Disimpan {{ \Illuminate\Support\Carbon::parse($consultation->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}</p></div></div>
                        <div class="staff-record-field"><span>Diagnosis</span><p>{{ $consultation->diagnosis }}</p></div>
                        <div class="staff-record-field"><span>Rencana tindakan / terapi</span><p>{{ $consultation->treatment_plan }}</p></div>
                        <div class="staff-record-field"><span>Keputusan dokter</span><p>{{ $consultation->disposition }}</p></div>
                        <div class="staff-record-field"><span>Rujukan ke dokter</span><p>{{ $consultation->referral_doctor_name ? $consultation->referral_department_name.' · '.$consultation->referral_doctor_name : 'Tidak perlu dirujuk' }}</p></div>
                        @if ($laboratoryOrder)
                            <div class="staff-record-field">
                                <span>Rujukan pemeriksaan laboratorium</span>
                                <p>{{ $laboratoryOrder->status === 'Completed' ? 'Hasil sudah dikirim' : 'Menunggu hasil laboratorium' }}</p>
                                @forelse ($laboratoryRequestedGroups as $specimen)
                                    <div class="staff-lab-result-group">
                                        <strong>{{ $specimen['label'] }}</strong>
                                        @foreach ($specimen['groups'] as $group)
                                            <p>{{ $group['label'] }}: {{ implode(', ', $group['tests']) }}</p>
                                        @endforeach
                                    </div>
                                @empty
                                    <p class="staff-pre-line">{{ $laboratoryOrder->requested_tests }}</p>
                                @endforelse
                                @if ($laboratoryOrder->status === 'Completed' && $laboratoryResultGroups !== [])
                                    <div class="staff-lab-result-groups">
                                        <strong>Hasil pemeriksaan</strong>
                                        @foreach ($laboratoryResultGroups as $specimen)
                                            <div class="staff-lab-result-group">
                                                <strong>{{ $specimen['label'] }}</strong>
                                                @foreach ($specimen['groups'] as $group)
                                                    <h3>{{ $group['label'] }}</h3>
                                                    <div class="staff-table-wrap">
                                                        <table class="staff-table">
                                                            <thead><tr><th>Parameter</th><th>Hasil</th><th>Satuan</th><th>Nilai rujukan</th><th>Keterangan</th></tr></thead>
                                                            <tbody>
                                                                @foreach ($group['tests'] as $result)
                                                                    <tr><td>{{ $result->parameter_name }}</td><td>{{ $result->result_value }}</td><td>{{ $result->unit ?: '—' }}</td><td>{{ $result->reference_range ?: '—' }}</td><td>{{ $result->interpretation === null ? '—' : ($result->interpretation === 'Normal' ? 'Normal' : ($result->interpretation === 'Low' ? 'Rendah' : 'Tinggi')) }}</td></tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif ($laboratoryOrder->status === 'Completed' && $laboratoryOrder->results)
                                    <p class="staff-pre-line">{{ $laboratoryOrder->results }}</p>
                                @endif
                                @if ($laboratoryOrder->status === 'Completed')
                                    <a class="staff-inline-action" href="{{ route('staff.doctor.laboratory-results.show', $laboratoryOrder->id) }}">Buka surat hasil laboratorium</a>
                                @endif
                            </div>
                        @endif
                        @if ($consultation->referral_doctor_id)
                            <div class="staff-record-field"><span>Alasan rujukan</span><p>{{ $consultation->referral_reason }}</p></div>
                            <div class="staff-record-field"><span>Hasil pemeriksaan untuk rujukan</span><p>{{ $consultation->examination_findings }}</p></div>
                        @endif
                        <div class="staff-record-field"><span>Catatan tambahan</span><p>{{ $consultation->notes ?: 'Tidak ada catatan tambahan.' }}</p></div>
                    </section>
                @elseif ($appointment->status === 'Checked-In')
                    <section class="staff-form-panel">
                        <div class="staff-panel-heading"><div><h2>Hasil pemeriksaan</h2><p>Lengkapi diagnosis dan rencana terapi sebelum menyelesaikan kunjungan.</p></div></div>
                        <form class="staff-data-form" action="{{ route('staff.doctor.appointments.consultation.store', $appointment->id) }}" method="post">
                            @csrf
                            <div class="staff-form-grid">
                                <div class="staff-form-field staff-field-wide">
                                    <label for="diagnosis">Diagnosis <span>*</span></label>
                                    <textarea id="diagnosis" name="diagnosis" rows="4" maxlength="5000" required>{{ old('diagnosis') }}</textarea>
                                </div>
                                <div class="staff-form-field staff-field-wide">
                                    <label for="treatment_plan">Rencana tindakan / terapi <span>*</span></label>
                                    <textarea id="treatment_plan" name="treatment_plan" rows="4" maxlength="5000" required>{{ old('treatment_plan') }}</textarea>
                                </div>
                                <div class="staff-form-field staff-field-wide">
                                    <label for="disposition">Keputusan tindak lanjut <span>*</span></label>
                                    <select id="disposition" name="disposition" required>
                                        <option value="">Pilih rawat jalan atau rawat inap</option>
                                        <option value="Rawat Jalan" @selected(old('disposition') === 'Rawat Jalan')>Rawat jalan</option>
                                        <option value="Rawat Inap" @selected(old('disposition') === 'Rawat Inap')>Rawat inap</option>
                                    </select>
                                    <small>Memilih rawat inap akan otomatis membuat admisi. Resepsionis dapat melengkapi kamar dan tempat tidur.</small>
                                </div>
                                <div class="staff-form-field staff-field-wide">
                                    <label for="referral_slot">Rujukan ke dokter lain</label>
                                    <select id="referral_slot" name="referral_slot" required>
                                        <option value="none" @selected(old('referral_slot', 'none') === 'none')>Tidak perlu dirujuk</option>
                                        @foreach ($referralSlots as $slot)
                                            <option value="{{ $slot->value }}" @selected(old('referral_slot') === $slot->value)>{{ $slot->label }}</option>
                                        @endforeach
                                    </select>
                                    @if ($referralSlots->isEmpty())
                                        <small>Belum ada jadwal dokter spesialis lain yang tersedia untuk 60 hari ke depan.</small>
                                    @else
                                        <small>Pilih dokter dan jadwal tujuan. Janji baru akan masuk ke antrean dokter tersebut setelah catatan disimpan.</small>
                                    @endif
                                </div>
                                <div class="staff-form-field staff-field-wide">
                                    <label for="referral_reason">Alasan rujukan</label>
                                    <textarea id="referral_reason" name="referral_reason" rows="3" maxlength="5000">{{ old('referral_reason') }}</textarea>
                                </div>
                                <div class="staff-form-field staff-field-wide">
                                    <label for="examination_findings">Hasil pemeriksaan yang perlu diketahui dokter tujuan</label>
                                    <textarea id="examination_findings" name="examination_findings" rows="4" maxlength="5000">{{ old('examination_findings') }}</textarea>
                                </div>
                                <div class="staff-form-field staff-field-wide">
                                    <label class="staff-lab-request-toggle" for="laboratory_requested">
                                        <input id="laboratory_requested" name="laboratory_requested" type="checkbox" value="1" @checked(old('laboratory_requested'))>
                                        <span>Rujuk pasien untuk pemeriksaan laboratorium</span>
                                    </label>
                                    <small>Aktifkan bila hasil laboratorium diperlukan sebagai penunjang pemeriksaan.</small>
                                </div>
                                <fieldset class="staff-form-field staff-field-wide staff-lab-test-selection">
                                    <legend>Pilih parameter pemeriksaan</legend>
                                    @foreach ($laboratoryTestGroups as $specimen)
                                        <section class="staff-lab-test-group">
                                            <strong>{{ $specimen['label'] }}</strong>
                                            @foreach ($specimen['groups'] as $group)
                                                <div class="staff-lab-test-subgroup">
                                                    <span>{{ $group['label'] }}</span>
                                                    <div class="staff-lab-test-options">
                                                        @foreach ($group['tests'] as $code => $label)
                                                            <label><input type="checkbox" name="requested_test_codes[]" value="{{ $code }}" @checked(in_array($code, (array) old('requested_test_codes', []), true))><span>{{ $label }}</span></label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </section>
                                    @endforeach
                                </fieldset>
                                <div class="staff-form-field staff-field-wide">
                                    <label for="laboratory_notes">Informasi klinis untuk laboratorium <span class="staff-optional-label">Opsional</span></label>
                                    <textarea id="laboratory_notes" name="laboratory_notes" rows="3" maxlength="3000">{{ old('laboratory_notes') }}</textarea>
                                </div>
                                <div class="staff-form-field staff-field-wide">
                                    <label for="notes">Catatan tambahan <span class="staff-optional-label">Opsional</span></label>
                                    <textarea id="notes" name="notes" rows="3" maxlength="5000">{{ old('notes') }}</textarea>
                                </div>
                            </div>
                            <div class="staff-form-actions">
                                <a class="staff-cancel-link" href="{{ route('staff.doctor.appointments.index', ['date' => $appointment->appointment_date]) }}">Batal</a>
                                <button class="staff-action-button" type="submit">Simpan pemeriksaan & keputusan</button>
                            </div>
                        </form>
                    </section>
                @elseif (in_array($appointment->status, ['Pending', 'Confirmed'], true) && $referral)
                    <section class="staff-alert staff-alert-info" role="status">
                        <strong>Menunggu check-in pasien</strong>
                        <p>Informasi rujukan dapat ditinjau sebelum pasien check-in di loket.</p>
                    </section>
                @else
                    <section class="staff-alert staff-alert-info" role="status">
                        <strong>Kunjungan sudah ditutup</strong>
                        <p>Catatan konsultasi untuk kunjungan ini tidak tersedia. Kunjungan yang sudah selesai tidak dapat diubah dari portal dokter.</p>
                    </section>
                @endif
                <p class="staff-data-note">Pastikan diagnosis, rencana tindak lanjut, dan keputusan rawat sudah benar. Setelah disimpan, catatan tidak dapat diubah dari halaman ini.</p>
            </div>
        </main>
    </div>
</body>
</html>
