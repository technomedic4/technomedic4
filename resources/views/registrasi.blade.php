<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>technnomedic4</title>

    <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;400;500;600;700&display=swap" rel="stylesheet"
    />
     <link rel="stylesheet" href="{{ asset('CSS/style.css') }}" />
</head>
<body class="login">
    <div class="split">
    <div class="img-box">
      <img src="{{ asset('img/img-medis.jpg') }}" alt="img-medis">
    </div>
    <div class="regis-cont">
        <div class="regis-card">
            <h2>Registrasi Akun</h2>

            <form action="{{ url('/registrasi') }}" method="post">
                @csrf

                <div class="input">
                    <label for="nama">Nama lengkap</label>
                    <input type="text" id="nama" name="name" placeholder="MasukanNama Lengkap" required>
                </div>
                <div class="input">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Masukan Email" required>
                </div>
                <div class="input">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Masukan Password" required>
                </div>

                <input type="hidden" name="role" value="Pasien">
                <button type="submit" class="btn-login">Daftarkan</button>
                <div class="auto-link">
                    <p>Sudah punya akun? <a href="{{ url('/login') }}">Login</a></p>
                </div>
            </form>
        </div>
    </div>
    </div>
  </body>