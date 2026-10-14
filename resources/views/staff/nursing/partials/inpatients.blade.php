<div class="staff-table-wrap">
    <table class="staff-table">
        <thead>
            <tr><th>Pasien</th><th>Kamar / tempat tidur</th><th>Masuk rawat inap</th><th>Kondisi terbaru</th><th>Tanda vital terbaru</th><th>Aksi</th></tr>
        </thead>
        <tbody>
            @forelse ($admissions as $admission)
                <tr>
                    <td>
                        <strong class="staff-patient-name">{{ $admission->patient_name }}</strong>
                        <span class="staff-booking-code">{{ $admission->medical_record_number ?: 'No. RM belum tersedia' }}</span>
                    </td>
                    <td>{{ $admission->room_number ?: '—' }} / {{ $admission->bed_number ?: '—' }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($admission->admitted_at)->locale('id')->translatedFormat('d F Y, H:i') }}</td>
                    <td>
                        @if ($admission->latest_update_status)
                            <strong>{{ $admission->latest_update_status }}</strong>
                            <span class="staff-booking-code">{{ $admission->latest_update_at ? \Illuminate\Support\Carbon::parse($admission->latest_update_at)->locale('id')->translatedFormat('d F Y, H:i') : '' }}</span>
                            <span>{{ \Illuminate\Support\Str::limit($admission->latest_update_notes ?? '', 100) }}</span>
                        @else
                            <span>Belum ada pembaruan kondisi.</span>
                        @endif
                    </td>
                    <td>
                        @if ($admission->latest_update_status)
                            @if ($admission->latest_temperature_celsius !== null)Suhu {{ number_format((float) $admission->latest_temperature_celsius, 1) }} °C<br>@endif
                            @if ($admission->latest_blood_pressure)TD {{ $admission->latest_blood_pressure }} mmHg<br>@endif
                            @if ($admission->latest_pulse_rate !== null)Nadi {{ $admission->latest_pulse_rate }} x/menit<br>@endif
                            @if ($admission->latest_respiratory_rate !== null)Napas {{ $admission->latest_respiratory_rate }} x/menit<br>@endif
                            @if ($admission->latest_oxygen_saturation !== null)SpO₂ {{ $admission->latest_oxygen_saturation }}%@endif
                            @if ($admission->latest_temperature_celsius === null && !$admission->latest_blood_pressure && $admission->latest_pulse_rate === null && $admission->latest_respiratory_rate === null && $admission->latest_oxygen_saturation === null)—@endif
                        @else
                            —
                        @endif
                    </td>
                    <td><a class="staff-inline-action" href="{{ route('staff.nursing.inpatients.show', $admission->id) }}">Catat kondisi</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="staff-table-empty">Tidak ada pasien rawat inap aktif yang cocok dengan pencarian.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
