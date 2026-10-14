@if ($updates->isEmpty())
    <div class="staff-empty-state">Belum ada catatan kondisi untuk pasien ini.</div>
@else
    <div class="staff-table-wrap">
        <table class="staff-table">
            <thead>
                <tr><th>Waktu</th><th>Kondisi</th><th>Tanda vital</th><th>Catatan</th><th>Dicatat oleh</th></tr>
            </thead>
            <tbody>
                @foreach ($updates as $update)
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse($update->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}</td>
                        <td>{{ $update->update_status }}</td>
                        <td>
                            @if ($update->temperature_celsius !== null)Suhu {{ number_format((float) $update->temperature_celsius, 1) }} °C<br>@endif
                            @if ($update->blood_pressure)TD {{ $update->blood_pressure }} mmHg<br>@endif
                            @if ($update->pulse_rate !== null)Nadi {{ $update->pulse_rate }} x/menit<br>@endif
                            @if ($update->respiratory_rate !== null)Napas {{ $update->respiratory_rate }} x/menit<br>@endif
                            @if ($update->oxygen_saturation !== null)SpO₂ {{ $update->oxygen_saturation }}%@endif
                            @if ($update->temperature_celsius === null && !$update->blood_pressure && $update->pulse_rate === null && $update->respiratory_rate === null && $update->oxygen_saturation === null)—@endif
                        </td>
                        <td>{{ $update->notes }}</td>
                        <td>{{ $update->recorded_by ?: 'Perawat' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
