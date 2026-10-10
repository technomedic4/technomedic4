<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>technnomedic4</title>

    <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;400;500;600;700&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="{{ asset('CSS/style.css') }}" />
  </head>

  <body class="login">

    <div class="split">
    <div class="img-box">
      <img src="{{ asset('img/img-medis.jpg') }}" alt="img-medis">
    </div>
<div class="login-cont">
      <div class="login-card">
        <h2>Login Akun</h2>
      
    <form action="{{ url('/login') }}" method="post">
      @csrf

      <div class="input">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="Masukkan email " required>
      </div>

      <div class="input">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Masukan Password" required>
      </div>
      <button type="submit" class="btn-login">Masuk</button>

      <div class="auto-link">
        <P>Belum punya akun? <a href="{{ url('/registrasi') }}">Daftar Disini</a></P>
      </div>
      </form>
      </div>
    </div>
    </div>
    
  </body>

