<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>TechnoMedic</title>

    <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;400;500;600;700&display=swap" rel="stylesheet"
    />
    <script src="https://unpkg.com/feather-icons"></script>
     <link rel="stylesheet" href="{{ asset('CSS/style.css') }}" />
</head>
<body class="body-page">
<nav class="navbar">
      <a href="{{ url('/home-page') }}" class="navbar-logo">TechnoMedic.</a>

      <div class="navbar-nav">
        <a href="{{ url('/home-page') }}">Beranda</a>
        <a href="{{ url('/jadwal') }}">Jadwal & Antrean</a>
        <a href="{{ url('/pantau') }}">Pantau Keluarga</a>
        <a href="{{ url('/konsultasi') }}">Telekonsultasi</a>
      </div>
      <div class="nav-kanan">
        <div class="profil-menu">
            <img src="{{ asset('img/profil-img.png') }}" alt="Profil User" class="profil-icon">
        </div>
      </div>
    </nav>

    <main class="home-cont">
        <section class="page" id="beranda">
            <div class="content">
                <h1>Hello {{ $userName ?: 'User' }}..</h1>
                <p>Selamat datang di Technomedic. Akses jadwal dokter, pantau rawat inap keluarga, dan konsultasi dari rumah dengan mudah.</p>
                <a href="{{ url('/jadwal') }}" class="btn">Ambil Antrian Sekarang</a>
            </div>
            <div class="content-img">
                <img src="{{ asset('img/hero.png') }}" alt="content-img-hero">
            </div>

        </section>
    </main>
</body>