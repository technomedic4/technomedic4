<!DOCTYPE html>
<html lang="id">
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

                @if ($errors->any())
                    <div class="form-errors" role="alert">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="input">
                    <label for="nama">Nama lengkap</label>
                    <input type="text" id="nama" name="name" value="{{ old('name') }}" maxlength="150" placeholder="Masukkan nama lengkap" required>
                </div>
                <div class="input">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="100" placeholder="Masukkan email" required>
                </div>
                <div class="input">
                    <label for="phone_number">Nomor telepon</label>
                    <input type="tel" id="phone_number" name="phone_number" value="{{ old('phone_number') }}" maxlength="20" placeholder="Masukkan nomor telepon" required>
                </div>
                <div class="input">
                    <label for="gender">Jenis kelamin</label>
                    <select id="gender" name="gender" required>
                        <option value="">Pilih jenis kelamin</option>
                        <option value="L" @selected(old('gender') === 'L')>Laki-laki</option>
                        <option value="P" @selected(old('gender') === 'P')>Perempuan</option>
                    </select>
                </div>
                <div class="input">
                    <label for="birth_date">Tanggal lahir</label>
                    <input type="date" id="birth_date" name="birth_date" value="{{ old('birth_date') }}" max="{{ now()->toDateString() }}" required>
                </div>
                <div class="input">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Masukkan password" minlength="6" required>
                </div>

                <button type="submit" class="btn-login">Daftarkan</button>
                <div class="auto-link">
                    <p>Sudah punya akun? <a href="{{ url('/login') }}">Login</a></p>
                </div>
            </form>
        </div>
    </div>
    </div>
  </body>