<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal & Antrian Dokter - Technomedic</title>
    <!-- Menghubungkan CSS khusus jadwal -->
    <link rel="stylesheet" href="{{ asset('CSS/jadwal.css') }}">
</head>
<body>

  <nav class="navbar">
      <a href="#" class="navbar-logo">TechnoMedic.</a>

      <div class="navbar-nav">
        <a href={{ url('/home-page') }}>Beranda</a>
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

    <!-- Konten Utama -->
    <div class="container">
        <h2 class="page-title">Jadwal Praktik & Status Antrean Dokter</h2>

        <div class="schedule-grid">
            
            <!-- Dokter 1 -->
            <div class="schedule-card">
                <div class="schedule-header">
                    <div class="doctor-avatar">DR</div>
                    <div class="doctor-info">
                        <h4>Dr. Rian Utama, Sp.PD</h4>
                        <p>Penyakit Dalam</p>
                    </div>
                </div>
                <div class="schedule-body">
                    <p><strong>Hari Praktik:</strong> Senin - Rabu</p>
                    <p><strong>Jam:</strong> 08:00 - 12:00 WIB</p>
                    <p><strong>Status:</strong> <span style="color: #28a745; font-weight: bold;">● Buka / Praktik</span></p>
                </div>
                <div class="queue-box">
                    <span>Nomor Antrean Anda</span>
                    <h3>Antrean #14</h3>
                </div>
                <a href="{{ url('/konsultasi') }}" class="btn-action">Masuk ke Ruang Konsultasi</a>
            </div>

            <!-- Dokter 2 -->
            <div class="schedule-card">
                <div class="schedule-header">
                    <div class="doctor-avatar" style="background-color: #d4edda; color: #155724;">SM</div>
                    <div class="doctor-info">
                        <h4>Dr. Siska Maharani</h4>
                        <p>Umum</p>
                    </div>
                </div>
                <div class="schedule-body">
                    <p><strong>Hari Praktik:</strong> Kamis - Jumat</p>
                    <p><strong>Jam:</strong> 13:00 - 17:00 WIB</p>
                    <p><strong>Status:</strong> <span style="color: #dc3545; font-weight: bold;">● Tutup / Libur</span></p>
                </div>
                <div class="queue-box">
                    <span>Nomor Antrean Anda</span>
                    <h3>Belum Mengambil</h3>
                </div>
                <a href="{{ url('/konsultasi') }}" class="btn-action" style="background-color: #6c757d;">Ambil Antrean / Konsultasi</a>
            </div>

        </div>
    </div>

</body>
</html>