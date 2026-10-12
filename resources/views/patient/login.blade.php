<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk Pasien | TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-login-body">
    <main class="staff-login-layout">
        <section class="staff-login-intro">
            <a class="staff-brand" href="{{ route('patient.login') }}">TechnoMedic<span>.</span></a>
            <div>
                <p class="staff-eyebrow">LAYANAN PASIEN</p>
                <h1>Terhubung dengan dokter Anda.</h1>
                <p>Buka ruang telekonsultasi yang terhubung dengan jadwal kunjungan Anda.</p>
            </div>
            <p class="staff-login-footnote">Gunakan akun pasien yang sudah terdaftar dan tertaut ke profil pasien.</p>
        </section>
        <section class="staff-login-panel" aria-labelledby="patient-login-title">
            <div class="staff-login-card">
                <p class="staff-eyebrow">SELAMAT DATANG</p>
                <h2 id="patient-login-title">Masuk sebagai pasien</h2>
                <p class="staff-muted">Akun pasien berbeda dengan akun portal staf.</p>

                @if (session('success'))
                    <div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <form action="{{ route('patient.login.attempt') }}" method="post">
                    @csrf
                    <label for="email">Email akun pasien</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                    <label for="password">Kata sandi</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button class="staff-primary-button" type="submit">Masuk ke telekonsultasi</button>
                </form>
                <p class="staff-login-help">Belum memiliki akses akun? Hubungi rumah sakit untuk menautkan akun ke profil pasien.</p>
                <p class="staff-login-help"><a href="{{ route('login') }}">Masuk sebagai staf rumah sakit</a></p>
            </div>
        </section>
    </main>
</body>
</html>
