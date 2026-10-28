<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk | Portal Staf TechnoMedic</title>
    <link rel="stylesheet" href="{{ asset('CSS/staff.css') }}">
</head>
<body class="staff-login-body">
    <main class="staff-login-layout">
        <section class="staff-login-intro">
            <a class="staff-brand" href="{{ route('login') }}">TechnoMedic<span>.</span></a>
            <div>
                <p class="staff-eyebrow">SISTEM INFORMASI RUMAH SAKIT</p>
                <h1>Portal operasional staf rumah sakit.</h1>
                <p>Kelola pelayanan pasien melalui satu ruang kerja yang aman dan terpusat.</p>
            </div>
            <p class="staff-login-footnote">Akses khusus untuk petugas yang berwenang.</p>
        </section>

        <section class="staff-login-panel" aria-labelledby="login-title">
            <div class="staff-login-card">
                <p class="staff-eyebrow">SELAMAT DATANG</p>
                <h2 id="login-title">Masuk ke portal staf</h2>
                <p class="staff-muted">Gunakan akun rumah sakit yang telah diberikan administrator.</p>

                @if (session('success'))
                    <div class="staff-alert staff-alert-success" role="status">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="staff-alert staff-alert-error" role="alert">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form action="{{ route('login.attempt') }}" method="post">
                    @csrf
                    <label for="email">Email kerja</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        autocomplete="username"
                        placeholder="nama@rumahsakit.id"
                        required
                        autofocus
                    >

                    <label for="password">Kata sandi</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        placeholder="Masukkan kata sandi"
                        required
                    >

                    <button class="staff-primary-button" type="submit">Masuk dengan aman</button>
                </form>
                <p class="staff-login-help">Bermasalah saat masuk? Hubungi administrator rumah sakit.</p>
            </div>
        </section>
    </main>
</body>
</html>
