<div class="staff-table-wrap">
    <table class="staff-table">
        <thead>
            <tr><th>Antrean</th><th>Pasien</th><th>Dokter & poli</th><th>Keluhan awal</th><th>Tindakan</th></tr>
        </thead>
        <tbody>
            @forelse ($appointments as $appointment)
                <tr>
                    <td>
                        <span class="staff-queue-number">{{ str_pad((string) $appointment->queue_number, 3, '0', STR_PAD_LEFT) }}</span>
                        <span class="staff-booking-code">{{ substr($appointment->start_time, 0, 5) }}</span>
                    </td>
                    <td>
                        <strong class="staff-patient-name">{{ $appointment->patient_name }}</strong>
                        <span class="staff-booking-code">{{ $appointment->medical_record_number ?: 'No. RM belum tersedia' }}</span>
                    </td>
                    <td><strong>{{ $appointment->doctor_name }}</strong><span class="staff-booking-code">{{ $appointment->department_name }}</span></td>
                    <td class="staff-complaint-cell">{{ $appointment->initial_complaint ?: 'Belum ada keluhan yang dicatat.' }}</td>
                    <td><a class="staff-inline-action" href="{{ route('staff.nursing.assessment-queue.show', $appointment->id) }}">Periksa kondisi</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="staff-table-empty">Tidak ada pasien check-in yang menunggu pemeriksaan awal perawat pada tanggal ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
