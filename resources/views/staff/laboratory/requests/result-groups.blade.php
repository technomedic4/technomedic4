<div class="staff-lab-details">
    @foreach ($groups as $specimenGroup)
        <div class="staff-form-field staff-field-wide staff-lab-result-group">
            <span>{{ $specimenGroup['label'] }}</span>
            @foreach ($specimenGroup['groups'] as $group)
                <strong>{{ $group['label'] }}</strong>
                <div class="staff-table-wrap">
                    <table class="staff-table">
                        <thead><tr><th>Parameter</th><th>Hasil</th><th>Satuan</th><th>Nilai rujukan</th><th>Keterangan</th></tr></thead>
                        <tbody>
                            @foreach ($group['tests'] as $result)
                                <tr>
                                    <td>{{ $result->parameter_name }}</td>
                                    <td>{{ $result->result_value }}</td>
                                    <td>{{ $result->unit ?: '—' }}</td>
                                    <td>{{ $result->reference_range ?: '—' }}</td>
                                    <td>{{ $result->interpretation === null ? '—' : ($result->interpretation === 'Normal' ? 'Normal' : ($result->interpretation === 'Low' ? 'Rendah' : 'Tinggi')) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
