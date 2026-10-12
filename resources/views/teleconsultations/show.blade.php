<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ruang Chat | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-body">
    <div class="staff-shell">
        <aside class="staff-sidebar">
            <a class="staff-brand" href="{{ $isDoctor ? route('staff.dashboard') : route('patient.teleconsultations.index') }}">TechnoMedic<span>.</span></a>
            <p class="staff-sidebar-caption">{{ $isDoctor ? 'PORTAL DOKTER' : 'LAYANAN PASIEN' }}</p>
            <div class="staff-sidebar-user"><span class="staff-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->email, 0, 1)) }}</span><span class="staff-user-copy"><strong>{{ auth()->user()->email }}</strong><span>{{ $isDoctor ? 'Dokter' : 'Pasien' }}</span></span></div>
            <nav class="staff-navigation" aria-label="Navigasi telekonsultasi">
                <span class="staff-navigation-label">TELEKONSULTASI</span>
                @if ($isDoctor)
                    <a class="staff-navigation-link" href="{{ route('staff.dashboard') }}"><span class="staff-navigation-icon">⌂</span>Dashboard staf</a>
                    <a class="staff-navigation-link is-active" href="{{ route('staff.doctor.teleconsultations.index') }}"><span class="staff-navigation-icon">✉</span>Ruang chat</a>
                    <a class="staff-navigation-link" href="{{ route('staff.doctor.appointments.index') }}"><span class="staff-navigation-icon">◷</span>Jadwal & pasien</a>
                @else
                    <a class="staff-navigation-link is-active" href="{{ route('patient.teleconsultations.index') }}"><span class="staff-navigation-icon">✉</span>Ruang chat</a>
                @endif
            </nav>
            <form class="staff-logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="staff-logout-button" type="submit">Keluar</button></form>
        </aside>
        <main class="staff-main">
            <header class="staff-topbar"><div><span class="staff-topbar-kicker">TELEKONSULTASI</span><span class="staff-topbar-role">{{ $record->booking_code }}</span></div><span class="staff-topbar-date">{{ \Illuminate\Support\Carbon::parse($record->appointment_date)->locale('id')->translatedFormat('l, d F Y') }}</span></header>
            <div class="staff-content">
                <section class="staff-page-heading">
                    <div><p class="staff-eyebrow">KONSULTASI JARAK JAUH · {{ $record->department_name }}</p><h1>{{ $isDoctor ? $record->patient_name : $record->doctor_name }}</h1><p>Keluhan awal: {{ $record->initial_complaint ?: 'Belum dicatat oleh resepsionis.' }}</p></div>
                    <a class="staff-secondary-button staff-button-link" href="{{ route($routePrefix.'.index') }}">Kembali ke telekonsultasi</a>
                </section>

                @if (session('success'))
                    <div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <section class="staff-chat-panel" aria-label="Percakapan telekonsultasi">
                    <header class="staff-chat-header">
                        <div><span class="staff-chat-presence {{ $canSendMessages ? 'is-open' : 'is-closed' }}"></span><div><strong>{{ $isDoctor ? $record->patient_name : $record->doctor_name }}</strong><span>{{ $canSendMessages ? 'Ruang chat aktif' : 'Konsultasi telah ditutup · riwayat hanya-baca' }}</span></div></div>
                        <div class="staff-chat-header-actions">
                            <span class="staff-chat-queue">Antrean {{ str_pad((string) $record->queue_number, 3, '0', STR_PAD_LEFT) }}</span>
                            <a class="staff-text-link" href="{{ route($routePrefix.'.show', $record->id) }}">Segarkan chat</a>
                        </div>
                    </header>
                    <div class="staff-chat-messages" aria-live="polite">
                        @if ($messages->isEmpty())
                            <div class="staff-chat-empty"><span>✉</span><strong>Mulai percakapan</strong><p>Kirim pesan untuk memulai konsultasi dengan {{ $isDoctor ? 'pasien' : 'dokter' }}.</p></div>
                        @else
                            @foreach ($messages as $message)
                                @php
                                    $senderName = $message->sender_role === 'Dokter'
                                        ? ($message->doctor_sender_name ?: 'Dokter')
                                        : ($message->patient_sender_name ?: 'Pasien');
                                @endphp
                                <article class="staff-chat-message {{ (int) $message->sender_user_id === (int) auth()->id() ? 'is-mine' : 'is-theirs' }}">
                                    <span class="staff-chat-sender">{{ (int) $message->sender_user_id === (int) auth()->id() ? 'Anda' : $senderName }}</span>
                                    <p>{{ $message->message }}</p>
                                    <time datetime="{{ \Illuminate\Support\Carbon::parse($message->created_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($message->created_at)->locale('id')->translatedFormat('d M Y, H:i') }}</time>
                                </article>
                            @endforeach
                        @endif
                    </div>
                    @if ($canSendMessages)
                        <form class="staff-chat-compose" action="{{ route($routePrefix.'.messages.store', $record->id) }}" method="post">
                            @csrf
                            <label class="staff-sr-only" for="message">Pesan</label>
                            <textarea id="message" name="message" rows="2" maxlength="3000" placeholder="Tulis pesan yang relevan dengan konsultasi ini..." required>{{ old('message') }}</textarea>
                            <button class="staff-action-button" type="submit">Kirim pesan</button>
                        </form>
                    @endif
                </section>
                @if ($isDoctor && $canSendMessages)
                    <form class="staff-complete-consultation" action="{{ route('staff.doctor.teleconsultations.complete', $record->id) }}" method="post">
                        @csrf
                        <span>Tutup ruang chat setelah konsultasi selesai. Riwayat percakapan tetap dapat dibaca.</span>
                        <button class="staff-secondary-button" type="submit">Selesaikan telekonsultasi</button>
                    </form>
                @endif
                <p class="staff-data-note">Hindari mengirim data sensitif yang tidak diperlukan. Dalam kondisi darurat, hubungi layanan gawat darurat rumah sakit secara langsung.</p>
            </div>
        </main>
    </div>
</body>
</html>
