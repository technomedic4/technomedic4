<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail {{ $bill->bill_number }} | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        @include('staff.billing.partials.sidebar', ['activePage' => 'index'])
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">MODUL KEUANGAN</span><span class="staff-topbar-role">Detail tagihan</span></div><span class="staff-topbar-date">{{ now()->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                @php
                    [$statusLabel, $statusClass] = match ($bill->status) {
                        'Unpaid' => ['Belum dibayar', 'is-unpaid'],
                        'Partially Paid' => ['Dibayar sebagian', 'is-partial'],
                        'Paid' => ['Lunas', 'is-paid'],
                        'Cancelled' => ['Dibatalkan', 'is-cancelled'],
                        default => [$bill->status, ''],
                    };
                @endphp
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">TAGIHAN {{ $bill->bill_number }}</p><h1>Detail tagihan</h1><p>{{ $bill->patient_name }} · {{ $bill->medical_record_number ?: 'No. RM belum tersedia' }}</p></div>
                    <div class="staff-heading-actions">
                        <a class="staff-secondary-button staff-button-link" href="{{ route('staff.billing.index') }}">Kembali ke daftar</a>
                        @if (! in_array($bill->status, ['Paid', 'Cancelled'], true))
                            <a class="staff-action-button staff-button-link" href="{{ route('staff.billing.edit', $bill->id) }}">Edit tagihan</a>
                        @endif
                    </div>
                </section>

                @if (session('success'))<div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-panel staff-billing-panel">
                    <div class="staff-panel-heading"><div><h2>Rincian tagihan</h2><p>Dibuat {{ \Illuminate\Support\Carbon::parse($bill->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}</p></div><span class="staff-status {{ $statusClass }}">{{ $statusLabel }}</span></div>
                    <div class="staff-table-wrap">
                        <table class="staff-table">
                            <thead><tr><th>Item</th><th>Jumlah</th><th>Harga satuan</th><th>Subtotal</th><th>Bukti tindakan</th></tr></thead>
                            <tbody>
                                @foreach ($bill->items as $item)
                                    <tr>
                                        <td>{{ $item->item_name }}</td>
                                        <td>{{ number_format($item->quantity) }}</td>
                                        <td>Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                        <td>Rp {{ number_format($item->quantity * $item->unit_price, 0, ',', '.') }}</td>
                                        <td>
                                            @if ($item->nursing_treatment_id)
                                                <strong>Catatan tindakan perawat</strong><br>
                                                {{ $item->nursing_treatment_notes ?: 'Tanpa catatan tambahan' }}<br>
                                                <small>{{ $item->nursing_treatment_recorded_by }}</small>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="staff-billing-totals">
                        <p><span>Total tagihan</span><strong>Rp {{ number_format($bill->total_amount, 0, ',', '.') }}</strong></p>
                        <p><span>Sudah dibayar</span><strong>Rp {{ number_format($bill->amount_paid, 0, ',', '.') }}</strong></p>
                        <p class="staff-billing-balance"><span>Sisa tagihan</span><strong>Rp {{ number_format($bill->balance_due, 0, ',', '.') }}</strong></p>
                    </div>
                </section>

                @if ($paymentSubmissions->isNotEmpty())
                    <section class="staff-panel staff-billing-panel">
                        <div class="staff-panel-heading">
                            <div><h2>Pengajuan pembayaran dari mobile</h2><p>Periksa bukti sebelum menyetujui pembayaran.</p></div>
                        </div>
                        <div class="staff-table-wrap">
                            <table class="staff-table">
                                <thead><tr><th>Diajukan</th><th>Jumlah</th><th>Metode</th><th>Status</th><th>Bukti</th><th>Keputusan</th></tr></thead>
                                <tbody>
                                    @foreach ($paymentSubmissions as $submission)
                                        <tr>
                                            <td>{{ \Illuminate\Support\Carbon::parse($submission->created_at)->locale('id')->translatedFormat('d F Y, H:i') }}<br>{{ $submission->submitted_by }}</td>
                                            <td>Rp {{ number_format($submission->amount, 0, ',', '.') }}</td>
                                            <td>{{ $submission->payment_method }}<br>{{ $submission->reference ?: '—' }}</td>
                                            <td>{{ $submission->status }}{{ $submission->review_note ? ': '.$submission->review_note : '' }}</td>
                                            <td><a href="{{ route('staff.billing.payment-submissions.proof', [$bill->id, $submission->id]) }}">Unduh bukti</a></td>
                                            <td>
                                                @if ($submission->status === 'Pending')
                                                    <form action="{{ route('staff.billing.payment-submissions.review', [$bill->id, $submission->id]) }}" method="post">
                                                        @csrf
                                                        <input type="text" name="review_note" maxlength="1000" placeholder="Catatan petugas">
                                                        <button class="staff-action-button" type="submit" name="decision" value="Approved">Setujui</button>
                                                        <button class="staff-secondary-button" type="submit" name="decision" value="Rejected">Tolak</button>
                                                    </form>
                                                @else
                                                    Ditinjau {{ $submission->reviewed_at ? \Illuminate\Support\Carbon::parse($submission->reviewed_at)->locale('id')->translatedFormat('d F Y, H:i') : '' }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                @if ($bill->payments->isNotEmpty())
                    <section class="staff-panel staff-billing-panel">
                        <div class="staff-panel-heading"><div><h2>Riwayat pembayaran</h2><p>Transaksi pembayaran yang telah dicatat oleh petugas.</p></div></div>
                        <div class="staff-table-wrap">
                            <table class="staff-table">
                                <thead><tr><th>Waktu</th><th>Jumlah</th><th>Metode</th><th>Referensi</th><th>Dicatat oleh</th></tr></thead>
                                <tbody>
                                    @foreach ($bill->payments as $payment)
                                        <tr><td>{{ \Illuminate\Support\Carbon::parse($payment->paid_at)->locale('id')->translatedFormat('d F Y, H:i') }}</td><td>Rp {{ number_format($payment->amount, 0, ',', '.') }}</td><td>{{ $payment->payment_method }}</td><td>{{ $payment->reference ?: '—' }}</td><td>{{ $payment->recorded_by }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                <section class="staff-form-panel staff-billing-actions-panel">
                    @if (in_array($bill->status, ['Unpaid', 'Partially Paid'], true))
                        <form class="staff-data-form" action="{{ route('staff.billing.payments.store', $bill->id) }}" method="post">
                            @csrf
                            <div class="staff-panel-heading"><div><h2>Catat pembayaran</h2><p>Sisa tagihan saat ini Rp {{ number_format($bill->balance_due, 0, ',', '.') }}.</p></div></div>
                            <div class="staff-form-grid">
                                <div class="staff-form-field">
                                    <label for="payment-amount">Jumlah pembayaran (Rp) <span>*</span></label>
                                    <input id="payment-amount" name="amount" type="number" min="1" max="{{ $bill->balance_due }}" required>
                                </div>
                                <div class="staff-form-field">
                                    <label for="payment-method">Metode pembayaran <span>*</span></label>
                                    <select id="payment-method" name="payment_method" required>
                                        <option value="">Pilih metode</option>
                                        @foreach ($paymentMethods as $paymentMethod)
                                            <option value="{{ $paymentMethod }}" @selected(old('payment_method') === $paymentMethod)>{{ $paymentMethod }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="staff-form-field staff-field-wide">
                                    <label for="payment-reference">Nomor referensi</label>
                                    <input id="payment-reference" name="reference" type="text" maxlength="100" value="{{ old('reference') }}">
                                </div>
                            </div>
                            <div class="staff-form-actions"><button class="staff-action-button" type="submit">Simpan pembayaran</button></div>
                        </form>
                    @endif

                    <div class="staff-billing-actions">
                        @if ($bill->status === 'Unpaid')
                            <form action="{{ route('staff.billing.cancel', $bill->id) }}" method="post" onsubmit="return confirm('Batalkan tagihan ini?')">
                                @csrf
                                <button class="staff-secondary-button" type="submit">Batalkan tagihan</button>
                            </form>
                        @endif
                        @if ((int) $bill->amount_paid === 0)
                            <form action="{{ route('staff.billing.destroy', $bill->id) }}" method="post" onsubmit="return confirm('Hapus tagihan ini secara permanen?')">
                                @csrf
                                @method('DELETE')
                                <button class="staff-small-action is-danger" type="submit">Hapus tagihan</button>
                            </form>
                        @endif
                    </div>
                </section>
                <p class="staff-data-note">Riwayat pembayaran bersifat permanen. Tagihan dengan pembayaran tercatat tidak dapat dihapus.</p>
            </div>
        </main>
    </div>
</body>
</html>
