@if ($careUpdates->isEmpty())
    <div class="staff-empty-state">Belum ada pembaruan kondisi pasien rawat inap.</div>
@else
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr><th>Pasien</th><th>Kamar</th><th>Kondisi</th><th>Tanda vital</th><th>Catatan</th><th>Perawat</th><th>Waktu</th></tr>
            </thead>
            <tbody>
                @foreach ($careUpdates as $update)
                    <tr>
                        <td class="staff-patient-name">{{ $update->patient_name }}</td>
                        <td>{{ $update->room_number ?: '—' }} / {{ $update->bed_number ?: '—' }}</td>
                        <td>{{ $update->update_status }}</td>
                        <td>
                            @if ($update->temperature_celsius !== null)Suhu {{ number_format((float) $update->temperature_celsius, 1) }} °C<br>@endif
                            @if ($update->blood_pressure)TD {{ $update->blood_pressure }} mmHg<br>@endif
                            @if ($update->pulse_rate !== null)Nadi {{ $update->pulse_rate }} x/menit<br>@endif
                            @if ($update->respiratory_rate !== null)Napas {{ $update->respiratory_rate }} x/menit<br>@endif
                            @if ($update->oxygen_saturation !== null)SpO₂ {{ $update->oxygen_saturation }}%@endif
                            @if ($update->temperature_celsius === null && !$update->blood_pressure && $update->pulse_rate === null && $update->respiratory_rate === null && $update->oxygen_saturation === null)—@endif
                        </td>
                        <td>{{ \Illuminate\Support\Str::limit($update->notes ?: '—', 100) }}</td>
                        <td>{{ $update->recorded_by ?: 'Perawat' }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($update->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
