<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konsultasi Medis - Technomedic</title>
    <!-- Menghubungkan file CSS -->
    <link rel="stylesheet" href="{{ asset('css/konsultasi.css') }}">
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

   <div class="container">
        

        <div class="sidebar">
            <h2>Daftar Dokter</h2>
            

            <div class="doctor-card" onclick="selectDoctor('Dr. Rian Utama, Sp.PD', 'Penyakit Dalam', 'DR')">
                <div class="doctor-avatar">DR</div>
                <div class="doctor-info">
                    <h4>Dr. Rian Utama, Sp.PD</h4>
                    <p>Penyakit Dalam</p>
                </div>
            </div>

  
            <div class="doctor-card" onclick="selectDoctor('Dr. Siska Maharani', 'Umum', 'SM')">
                <div class="doctor-avatar" style="background-color: #d4edda; color: #155724;">SM</div>
                <div class="doctor-info">
                    <h4>Dr. Siska Maharani</h4>
                    <p>Umum</p>
                </div>
            </div>
        </div>


        <div class="chat-container">
            

            <div id="chatPlaceholder" class="chat-placeholder">
                <h3>Belum Memilih Dokter</h3>
                <p>Silakan pilih salah satu dokter di samping kiri untuk memulai sesi konsultasi.</p>
            </div>

            <!-- Area Chat Aktif (Tersembunyi Awalnya) -->
            <div id="chatActiveArea" class="chat-active-area">
                

                <div class="chat-header">
                    <div class="doctor-avatar" id="headerAvatar">DR</div>
                    <div class="chat-header-info">
                        <h3 id="headerDoctorName">Dr. Rian Utama, Sp.PD</h3>
                        <span class="status-online">● Online</span>
                    </div>
                </div>


                <div class="chat-messages" id="chatMessages">
                    <div class="message doctor">
                        Halo! Ada keluhan kesehatan apa yang bisa saya bantu hari ini?
                    </div>
                </div>


                <div class="chat-input-area">
                    <form id="chatForm" class="chat-form">
                        @csrf
                        <input type="text" id="messageInput" placeholder="Tuliskan keluhan atau pesan Anda di sini..." class="chat-input" autocomplete="off" required>
                        <button type="submit" class="btn-send">Kirim</button>
                    </form>
                </div>

            </div>

        </div>

    </div>

  
    <script>
        const chatPlaceholder = document.getElementById('chatPlaceholder');
        const chatActiveArea = document.getElementById('chatActiveArea');
        const headerDoctorName = document.getElementById('headerDoctorName');
        const headerAvatar = document.getElementById('headerAvatar');
        
        const chatForm = document.getElementById('chatForm');
        const messageInput = document.getElementById('messageInput');
        const chatMessages = document.getElementById('chatMessages');

   
        function selectDoctor(name, specialty, initials) {
            // Sembunyikan placeholder, tampilkan area chat
            chatPlaceholder.style.display = 'none';
            chatActiveArea.style.display = 'flex';

            
            headerDoctorName.textContent = name;
            headerAvatar.textContent = initials;
        }

        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const messageText = messageInput.value.trim();
            if (messageText === '') return;

            const newMessage = document.createElement('div');
            newMessage.classList.add('message', 'patient');
            newMessage.textContent = messageText;
            chatMessages.appendChild(newMessage);

            messageInput.value = '';
            chatMessages.scrollTop = chatMessages.scrollHeight;

            setTimeout(() => {
                const replyMessage = document.createElement('div');
                replyMessage.classList.add('message', 'doctor');
                replyMessage.textContent = "Baik, keluhan tersebut sudah saya catat. Ada gejala tambahan lain yang dirasakan?";
                chatMessages.appendChild(replyMessage);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }, 1000);
        });
    </script>

</body>
</html>