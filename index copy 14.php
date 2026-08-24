<?php

// ==========================================
// BACKEND SQLITE: SIMPAN & AMBIL DATA UCAPAN
// ==========================================

// Lokasi file database SQLite (akan otomatis terbuat jika belum ada)
$db_file = __DIR__ . '/database_undangan.sqlite';

try {
    // Membuat koneksi PDO ke SQLite
    $pdo = new PDO("sqlite:" . $db_file);
    // Set error mode ke exception untuk mempermudah debungging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Membuat tabel 'ucapan' jika belum ada di database
    $query_create_table = "CREATE TABLE IF NOT EXISTS ucapan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        pesan TEXT NOT NULL,
        waktu DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($query_create_table);

    // 1. PROSES SIMPAN DATA (Jika ada form yang di-submit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ucapan'])) {
        // Ambil data dan bersihkan dari tag HTML berbahaya
        $nama = htmlspecialchars(trim($_POST['nama']));
        $pesan = htmlspecialchars(trim($_POST['pesan']));

        if (!empty($nama) && !empty($pesan)) {
            // Gunakan prepared statement untuk keamanan dari SQL Injection
            $stmt = $pdo->prepare("INSERT INTO ucapan (nama, pesan, waktu) VALUES (:nama, :pesan, datetime('now', 'localtime'))");
            $stmt->execute([
                ':nama' => $nama,
                ':pesan' => $pesan
            ]);
            
            // Redirect (Muat ulang halaman) ke ID guestbook agar tidak terjadi resubmit saat halaman di-refresh
            header("Location: " . $_SERVER['PHP_SELF'] . "#guestbook");
            exit;
        }
    }

    // 2. PROSES AMBIL DATA (Untuk ditampilkan di daftar ucapan)
    $stmt = $pdo->query("SELECT * FROM ucapan ORDER BY id DESC");
    $ucapan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Nama tamu undangan yang hanya bisa dikirim dari link ?to=Nama
    $guest_name = 'Tamu Undangan';
    $guest_allowed = false;
    if (isset($_GET['to'])) {
        $potential_name = trim($_GET['to']);
        if ($potential_name !== '') {
            $guest_name = htmlspecialchars($potential_name);
            $guest_allowed = true;
        }
    }

} catch (PDOException $e) {
    // Hentikan proses jika gagal terhubung ke database
    die("Koneksi Database Gagal: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undangan Pernikahan | Bali</title>

    <!-- Open Graph / WhatsApp / Telegram Preview -->
    <meta property="og:title" content="Undangan Pernikahan Rama & Yuli" />
    <meta property="og:description" content="Selamat datang di undangan pernikahan Rama & Yuli. Terima kasih atas doa dan kehadirannya." />
    <meta property="og:image" content="foto/ft.jpg" />
    <meta property="og:image:alt" content="Undangan Pernikahan Rama & Yuli" />
    <meta property="og:url" content="https://example.com/undangan" />
    <meta property="og:type" content="website" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="Undangan Pernikahan Rama & Yuli" />
    <meta name="twitter:description" content="Selamat datang di undangan pernikahan Rama & Yuli. Terima kasih atas doa dan kehadirannya." />
    <meta name="twitter:image" content="foto/ft.jpg" />
    <meta name="twitter:image:alt" content="Undangan Pernikahan Rama & Yuli" />

    <script src="https://cdn.tailwindcss.com"></script>
    
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cream: '#fdfbf7',
                        creamDark: '#f2ece4',
                        gold: '#c5a059',
                        textDark: '#4a4a4a'
                    },
                    fontFamily: {
                        serif: ['Cormorant Garamond', 'serif'],
                        sans: ['Montserrat', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <style>
        body {
            background-color: #fdfbf7;
            overflow-x: hidden;
        }

        /* Lock scrolling di awal sebelum tombol dibuka ditekan */
        body.locked {
            overflow-y: hidden;
            height: 100vh;
        }

        .bg-overlay-cream {
            background-color: rgba(253, 251, 247, 0.85);
        }

        /* Fading Slider */
        .fade-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            transition: opacity 1.5s ease-in-out;
            object-fit: cover;
        }
        .fade-slide.active {
            opacity: 1;
        }

        #cover-gate {
            transition: transform 1s ease-in-out, opacity 1s ease-in-out;
        }
        .gate-opened {
            transform: translateY(-100%);
            opacity: 0;
            pointer-events: none;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Rotasi lambat untuk tombol musik aktif */
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .animate-spin-slow {
            animation: spin-slow 4s linear infinite;
        }

        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #fdfbf7;
        }
        ::-webkit-scrollbar-thumb {
            background: #c5a059;
            border-radius: 10px;
        }
        
        /* Portrait shape for couple photos: rounded top-right & bottom-left */
        .shape-portrait {
            object-fit: cover;
            width: 100%;
            height: 100%;
            border-radius: 0 2.5rem 0 2.5rem;
        }

        /* Gallery lightbox */
        #lightbox {
            position: fixed;
            inset: 0;
            display: none;
            background: rgba(0,0,0,0.85);
            align-items: center;
            justify-content: center;
            z-index: 60;
            padding: 2rem;
        }
        #lightbox img {
            max-width: 90%;
            max-height: 80%;
            border-radius: 0.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
        }

        /* Video panel border */
        .video-panel {
            border: 4px solid #c5a059;
            padding: 0.5rem;
            border-radius: 0.75rem;
            background: #ffffff;
            max-width: 900px;
            margin: 1.5rem auto;
        }

        .gallery-link img { cursor: pointer; transition: transform .25s ease; }
        .gallery-link img:hover { transform: scale(1.03); }
    </style>
</head>
<body class="font-sans text-textDark antialiased locked">

    <audio id="backsound" loop src="sound.mp3"></audio>

    <button id="music-control-btn" onclick="toggleMusic()" class="fixed bottom-6 right-6 z-40 bg-gold text-white w-12 h-12 rounded-full shadow-2xl flex items-center justify-center hover:bg-yellow-700 transition duration-300 transform hover:scale-110 hidden">
        <i id="music-icon" class="fa-solid fa-circle-pause text-xl"></i>
    </button>

    <div id="cover-gate" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-cream overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="foto/ft.jpg" alt="Background Cover" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-black/40"></div>
        </div>
        
        <div class="z-10 text-center text-white px-6">
            <p class="font-sans text-sm tracking-[0.3em] mb-4 uppercase">The Wedding Of</p>
            <h1 class="font-serif text-5xl md:text-7xl mb-4 italic">Rama & Yuli </h1>
            <p class="font-sans text-sm md:text-base mb-8">Kepada Yth. Bapak/Ibu/Saudara/i</p>
            <div class="glass-panel inline-block px-6 py-2 rounded-lg mb-8 text-gray-800">
                <p class="font-bold text-lg text-black"><?= $guest_name ?></p>
            </div>
            <br>
            <button onclick="openInvitation()" class="bg-gold text-white px-8 py-3 rounded-full font-sans text-sm tracking-widest hover:bg-yellow-700 transition duration-300 shadow-lg flex items-center gap-2 mx-auto">
                <i class="fa-solid fa-envelope-open"></i> BUKA UNDANGAN
            </button>
        </div>
    </div>

    <div id="main-content" class="hidden relative">

        <section class="relative w-full h-screen overflow-hidden flex items-center justify-center">
    
    <div id="hero-slider">
        <img src="foto/ft (1).jpg" class="fade-slide active">
        <img src="foto/ft (2).jpg" class="fade-slide">
        <img src="foto/ft (3).jpg" class="fade-slide">
    </div>

    <div class="absolute inset-0 bg-black/30 z-10"></div>
    
    <div class="absolute bottom-0 left-0 right-0 h-60 bg-gradient-to-t from-white to-transparent z-15 pointer-events-none"></div>
    
    <div class="relative z-20 text-center text-white flex flex-col items-center" data-aos="fade-up" data-aos-duration="1500">
        <p class="font-sans tracking-[0.2em] mb-2 uppercase text-xs md:text-sm">We Are Getting Married</p>
        <h2 class="font-serif text-6xl md:text-8xl italic mb-4">Rama & Yuli </h2>
        <p class="font-serif text-xl md:text-2xl mt-4">7 . 6 . 2026</p>
    </div>

    <div class="absolute bottom-10 z-25 animate-bounce">
        <i class="fa-solid fa-chevron-down text-white text-2xl opacity-70"></i>
    </div>

</section>

        <section class="relative py-20 ">
            <div class="absolute inset-0 bg-cover bg-center opacity-20 pointer-events-none" 
         style="background-image: url('foto/begron.jpg');">
    </div>
          

            <div class="relative z-10 container mx-auto px-6 text-center max-w-4xl">
                <div data-aos="fade-up">
                    <h3 class="font-serif text-3xl font-bold mb-4 text-gold">Om Swastiastu</h3>
                    <p class="text-sm md:text-base leading-relaxed mb-12">
                        Atas Asung Kertha Wara Nugraha Ida Sang Hyang Widhi Wasa/Tuhan Yang Maha Esa, kami bermaksud mengundang Bapak/Ibu/Saudara/i pada acara Resepsi Pernikahan putra-putri kami.
                    </p>
                </div>

                <div class="flex flex-col md:flex-row items-center justify-center gap-12 mt-10">
                    <div class="flex-1 text-center" data-aos="fade-right">
                        <div class="w-64 h-80 mx-auto overflow-hidden border-4 border-gold p-1 shadow-lg mb-6 rounded-tr-[2.5rem] rounded-bl-[2.5rem]">
                            <img src="foto/ft (4).jpg" alt="Groom" class="w-full h-full object-cover shape-portrait">
                        </div>
                        <h4 class="font-serif text-3xl font-bold mb-2">I Kadek Rama</h4>
                        <p class="text-sm mb-1">Putra Pertama dari pasangan</p>
                        <p class="font-semibold text-sm mb-3"> I Wayan Mugiana 
                            & 
                            Ni kadek kartini almarhum</p>
                        <p class="text-xs text-gray-500 italic"><i class="fa-solid fa-location-dot text-gold mr-1"></i> Br selat, Buahan kaja, Payangan</p>
                    </div>

                    <div class="text-5xl font-serif text-gold" data-aos="zoom-in">&</div>

                    <div class="flex-1 text-center" data-aos="fade-left">
                        <div class="w-64 h-80 mx-auto overflow-hidden border-4 border-gold p-1 shadow-lg mb-6 rounded-tr-[2.5rem] rounded-bl-[2.5rem]">
                            <img src="foto/ft (5).jpg" alt="Bride" class="w-full h-full object-cover shape-portrait">
                        </div>
                        <h4 class="font-serif text-3xl font-bold mb-2">Ni luh Putu Sri Yulianti</h4>
                        <p class="text-sm mb-1">Putri Pertama dari pasangan</p>
                        <p class="font-semibold text-sm mb-3">I Putu alit Saputra 
                            & 
                            Ni kadek noridani</p>
                        <p class="text-xs text-gray-500 italic"><i class="fa-solid fa-location-dot text-gold mr-1"></i> Br. Dinas Kaja, Denpasar, Bali</p>
                    </div>
                </div>
            </div>
        </section>
      
    <section class="py-20 bg-creamDark relative text-center bg-cover bg-center" style="background-image: url('foto/ft (8).jpg');">
      <h3 class="font-serif text-4xl text-gold mb-8" data-aos="fade-up">Menuju Hari Bahagia</h3>
    <div class="absolute inset-0 bg-gradient-to-t from-white via-white/30 to-transparent z-0"></div>
            
    <div class="container mx-auto px-6 z-10 relative">     
              
                
                <div class="flex justify-center gap-4 md:gap-8 mb-10" data-aos="zoom-in">
                    <div class="bg-white shadow-md rounded-lg w-20 h-24 md:w-24 md:h-28 flex flex-col items-center justify-center border-t-4 border-gold">
                        <span id="days" class="text-3xl md:text-4xl font-bold font-serif text-gold">00</span>
                        <span class="text-xs md:text-sm uppercase tracking-wider mt-1">Hari</span>
                    </div>
                    <div class="bg-white shadow-md rounded-lg w-20 h-24 md:w-24 md:h-28 flex flex-col items-center justify-center border-t-4 border-gold">
                        <span id="hours" class="text-3xl md:text-4xl font-bold font-serif text-gold">00</span>
                        <span class="text-xs md:text-sm uppercase tracking-wider mt-1">Jam</span>
                    </div>
                    <div class="bg-white shadow-md rounded-lg w-20 h-24 md:w-24 md:h-28 flex flex-col items-center justify-center border-t-4 border-gold">
                        <span id="minutes" class="text-3xl md:text-4xl font-bold font-serif text-gold">00</span>
                        <span class="text-xs md:text-sm uppercase tracking-wider mt-1">Menit</span>
                    </div>
                    <div class="bg-white shadow-md rounded-lg w-20 h-24 md:w-24 md:h-28 flex flex-col items-center justify-center border-t-4 border-gold">
                        <span id="seconds" class="text-3xl md:text-4xl font-bold font-serif text-gold">00</span>
                        <span class="text-xs md:text-sm uppercase tracking-wider mt-1">Detik</span>
                    </div>
                </div>

                <a href="https://calendar.google.com/calendar/render?action=TEMPLATE&text=Pernikahan+Bagus+%26+Ayu&dates=20261225T010000Z/20261225T070000Z&details=Resepsi+Pernikahan+Bagus+dan+Ayu&location=Gedung+Serbaguna+Bali" target="_blank" class="inline-block bg-gold text-white px-8 py-3 rounded-full text-sm font-semibold tracking-wide shadow-lg hover:bg-yellow-700 transition" data-aos="fade-up">
                    <i class="fa-regular fa-calendar-check mr-2"></i> SAVE THE DATE
                </a>
            </div>
        </section>

        <section class="py-20 relative bg-cream">
             <div class="absolute inset-0 z-0">
                <img src="foto/bg1.jpg" class="w-full h-full object-cover opacity-10 grayscale">
                <div class="absolute inset-0 bg-overlay-cream"></div>
            </div>
            <div class="container mx-auto px-6 relative z-10">
                <div class="max-w-2xl mx-auto glass-panel p-8 rounded-2xl shadow-xl text-center" data-aos="flip-up">
                    <i class="fa-solid fa-champagne-glasses text-4xl text-gold mb-4"></i>
                    <h3 class="font-serif text-4xl mb-2 text-gold">Resepsi Pernikahan</h3>
                    <p class="mb-6 italic text-gray-500">Minggu, 7 Juni 2026</p>
                    
                    <div class="flex justify-center items-center gap-4 mb-6">
                        <i class="fa-regular fa-clock text-xl text-gold"></i>
                        <p class="font-semibold text-lg"> 13.00 - Selesai</p>
                    </div>

                    <div class="border-t border-b border-gray-200 py-6 mb-6">
                        <i class="fa-solid fa-map-location-dot text-2xl text-gold mb-3"></i>
                        <h4 class="font-bold text-lg mb-1">Br, Gambih ,Desa Buahan ,Kec Payangan</h4>
                        <p class="text-sm text-gray-600"></p>
                    </div>

                    <a href="https://maps.app.goo.gl/B9cpzbiL5PhXhvDW9" target="_blank" class="inline-block border-2 border-gold text-gold px-6 py-2 rounded-full text-sm font-semibold hover:bg-gold hover:text-white transition">
                        <i class="fa-solid fa-map"></i> Buka Google Maps
                    </a>
                </div>
            </div>
        </section>

        <section class="py-20 relative overflow-hidden">
    
    <div class="absolute inset-0 bg-cover bg-center opacity-20 pointer-events-none" 
         style="background-image: url('foto/bg1.jpg');">
    </div>
            <div class="container mx-auto px-6">
                <div class="text-center mb-12" data-aos="fade-up">
                    <h3 class="font-serif text-4xl text-gold mb-2">Wedding Gallery</h3>
                    <p class="text-sm text-gray-500">Momen bahagia yang kami abadikan.</p>
                </div>
                
                <div class="columns-2 md:columns-4 gap-1 md:gap-2 space-y-1 md:space-y-2" id="gallery-container">
                    </div>

                <!-- Lightbox for gallery -->
                <div id="lightbox" class="" role="dialog" aria-hidden="true">
                    <button id="lightbox-prev" class="absolute left-6 top-1/2 transform -translate-y-1/2 text-white text-3xl hover:text-gold transition z-50" aria-label="Previous image"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="absolute top-6 right-6 text-white text-2xl lightbox-close hover:text-gold transition" aria-label="Close">&times;</button>
                    <button id="lightbox-next" class="absolute right-6 top-1/2 transform -translate-y-1/2 text-white text-3xl hover:text-gold transition z-50" aria-label="Next image"><i class="fa-solid fa-chevron-right"></i></button>
                    <img src="" alt="Preview image">
                    <p class="absolute bottom-6 left-1/2 transform -translate-x-1/2 text-white text-sm"><span id="lightbox-counter">1</span> / <span id="lightbox-total">20</span></p>
                </div>

                <div class="video-panel border-2 border-[#D4AF37] rounded-lg mt-8 p-6 font-serif text-center" data-aos="fade-up">
    
    <h3 class="font-serif font-semibold text-4xl text-gold mb-6">Momen Kami</h3>
    
    <div class="aspect-w-16 aspect-h-9 max-w-3xl mx-auto">
        <iframe width="100%" height="450" src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="rounded-md"></iframe>
    </div>
    
</div>
            </div>
        </section>
        
              <section id="guestbook" class="py-16 px-6 bg-section-overlay">
            <div class="content-relative">
                <h2 class="font-serif text-4xl text-gold font-bold mb-8 text-center" data-aos="fade-down">Doa & Ucapan</h2>
                
                <form id="wishes-form" method="POST" action="" class="bg-white p-6 rounded-xl shadow-lg mb-8" data-aos="fade-up">
                    <div class="mb-4">
                        <label class="block text-sm mb-1 text-gray-600">Nama Anda</label>
                        <input id="guestName" name="nama" type="text" value="<?= htmlspecialchars($guest_name) ?>" readonly class="w-full border border-gray-300 p-2 rounded bg-gray-100 text-gray-700 cursor-not-allowed focus:outline-none focus:border-gold">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm mb-1 text-gray-600">Ucapan</label>
                        <textarea id="guestMessage" name="pesan" rows="3" required class="w-full border border-gray-300 p-2 rounded focus:outline-none focus:border-gold"></textarea>
                    </div>
                    <button type="submit" name="submit_ucapan" class="w-full bg-gold text-white py-2 rounded hover:bg-yellow-600 transition shadow" <?= $guest_allowed ? '' : 'disabled' ?> >
                        Kirim Ucapan
                    </button>
                    <?php if (!$guest_allowed): ?>
                        <p class="text-xs text-gray-500 mt-2"><span class="font-semibold"></span>.</p>
                    <?php endif; ?>
                </form>

                <div id="wishes-list" class="max-h-80 overflow-y-auto pr-2 space-y-4">
                    <?php if (count($ucapan_list) > 0): ?>
                        <?php foreach($ucapan_list as $ucapan): ?>
                            <div class="bg-white p-4 rounded-lg shadow border-l-4 border-gold">
                                <h4 class="font-bold text-dark"><?= htmlspecialchars($ucapan['nama']) ?></h4>
                                <p class="text-xs text-gray-400 mb-2"><?= date('d M Y, H:i', strtotime($ucapan['waktu'])) ?></p>
                                <p class="text-sm text-gray-700">"<?= htmlspecialchars($ucapan['pesan']) ?>"</p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-sm text-gray-500 italic">Belum ada ucapan, jadilah yang pertama!</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>


        <section class="py-16 bg-gray-900 text-white text-center relative overflow-hidden  bg-cover bg-center" style="background-image: url('foto/ft (9) 1.jpg');">

    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent z-0"></div>
             <div class="container mx-auto px-6 relative z-10" data-aos="fade-up">
                 <p class="text-sm md:text-base leading-relaxed mb-8 max-w-2xl mx-auto">
                     Merupakan suatu kehormatan dan kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir untuk memberikan doa restu kepada kedua mempelai.
                     <br><br>
                     Atas kehadiran dan doa restunya kami ucapkan terima kasih.
                 </p>
                 <h3 class="font-serif text-2xl text-gold mb-8">Om Santih Santih Santih Om</h3>
                 <h2 class="font-serif text-4xl italic mb-4">Rama & Yuli </h2>
                 <div class="flex items-center justify-center gap-2 text-xs text-gray-400">
                     <span>Undangan digital oleh <a href="https://www.instagram.com/agusarya_306/" target="_blank" class="text-gold hover:underline transition">Arya</a></span>
                     <a href="https://www.instagram.com/agusarya_306/" target="_blank" class="text-gold hover:text-yellow-600 transition">
                         <i class="fa-brands fa-instagram"></i>
                     </a>
                 </div>
             </div>
        </section>

    </div>

    <div id="toast" class="fixed bottom-5 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white px-4 py-2 rounded-lg opacity-0 transition-opacity duration-300 pointer-events-none z-50">
        Nomor Rekening Tersalin!
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        // Inisialisasi Sistem Animasi
        AOS.init({
            once: false, 
            offset: 50,
            duration: 800,
            easing: 'ease-out-cubic',
        });

        const bgMusic = document.getElementById('backsound');
        const musicBtn = document.getElementById('music-control-btn');
        const musicIcon = document.getElementById('music-icon');

        // 1. Fungsi Pembuka Undangan & Pemutar Musik Utama
        function openInvitation() {
            const gate = document.getElementById('cover-gate');
            const main = document.getElementById('main-content');
            
            gate.classList.add('gate-opened');
            setTimeout(() => {
                gate.style.display = 'none';
                main.classList.remove('hidden');
                
                // Hapus efek pengunci scroll layar
                document.body.classList.remove('locked');
                
                // Mainkan Musik Latar & Tampilkan tombol kontrol musik
                playMusic();
                musicBtn.classList.remove('hidden');
                
                AOS.refresh();
            }, 1000);
        }

        // 2. Kontrol Musik (Play/Pause)
        function playMusic() {
            bgMusic.play().then(() => {
                musicIcon.className = "fa-solid fa-compact-disc text-xl animate-spin-slow";
            }).catch(err => {
                console.log("Autoplay dicegah oleh browser, membutuhkan interaksi user.");
            });
        }

        function toggleMusic() {
            if (bgMusic.paused) {
                bgMusic.play();
                musicIcon.className = "fa-solid fa-compact-disc text-xl animate-spin-slow";
            } else {
                bgMusic.pause();
                musicIcon.className = "fa-solid fa-circle-play text-xl";
            }
        }

        // 3. Fading Image Slider Hero Section
        const slides = document.querySelectorAll('.fade-slide');
        let currentSlide = 0;
        setInterval(() => {
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }, 4000);

        // 4. Hitung Mundur (Countdown Timer)
        const countDownDate = new Date("Jun 7, 2026 13:00:00").getTime();
        const intervalTimer = setInterval(function() {
            const now = new Date().getTime();
            const distance = countDownDate - now;

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById("days").innerHTML = days < 10 ? '0'+days : days;
            document.getElementById("hours").innerHTML = hours < 10 ? '0'+hours : hours;
            document.getElementById("minutes").innerHTML = minutes < 10 ? '0'+minutes : minutes;
            document.getElementById("seconds").innerHTML = seconds < 10 ? '0'+seconds : seconds;

            if (distance < 0) {
                clearInterval(intervalTimer);
            }
        }, 1000);

        // 5. Render Galeri Berisi 20 Foto Statis Elegan
        const galleryContainer = document.getElementById('gallery-container');
        const sampleImages = [
            'foto/ph (1).jpg',
            'foto/ph (2).jpg',
            'foto/ph (3).jpg',
            'foto/ph (4).jpg',
            'foto/ph (5).jpg',
            'foto/ph (6).jpg',
            'foto/ph (7).jpg',
            'foto/ph (8).jpg',
            'foto/ph (9).jpg',
            'foto/ph (10).jpg',
            'foto/ph (11).jpg',
            'foto/ph (12).jpg',
            'foto/ph (13).jpg',
            'foto/ph (14).jpg',
            'foto/ph (15).jpg',

        ];

        const galleryImages = [];
        for (let i = 0; i < sampleImages.length; i++) {
            const src = sampleImages[i];
            const delay = (i % 4) * 100;
            const a = document.createElement('a');
            a.href = '#';
            a.className = `gallery-link block overflow-hidden rounded-lg shadow-sm break-inside-avoid`;
            a.setAttribute('data-aos', 'zoom-in');
            a.setAttribute('data-aos-delay', delay.toString());
            a.setAttribute('data-src', src);
            a.innerHTML = `<img src="${src}" class="w-full h-auto object-cover">`;
            galleryContainer.appendChild(a);
            galleryImages.push(src);
        }

        // Lightbox handlers dengan navigation
        const lightbox = document.getElementById('lightbox');
        let currentImageIndex = 0;
        
        if (lightbox) {
            const lightboxImg = lightbox.querySelector('img');
            const lightboxCounter = document.getElementById('lightbox-counter');
            const lightboxTotal = document.getElementById('lightbox-total');
            const lightboxPrev = document.getElementById('lightbox-prev');
            const lightboxNext = document.getElementById('lightbox-next');
            const lightboxClose = lightbox.querySelector('.lightbox-close');
            
            lightboxTotal.textContent = galleryImages.length;
            
            const openLightbox = (index) => {
                currentImageIndex = index;
                lightboxImg.src = galleryImages[currentImageIndex];
                lightboxCounter.textContent = currentImageIndex + 1;
                lightbox.style.display = 'flex';
                lightbox.setAttribute('aria-hidden', 'false');
            };
            
            const closeLightbox = () => {
                lightbox.style.display = 'none';
                lightbox.setAttribute('aria-hidden', 'true');
                lightboxImg.src = '';
            };
            
            const showPrevImage = () => {
                currentImageIndex = (currentImageIndex - 1 + galleryImages.length) % galleryImages.length;
                lightboxImg.src = galleryImages[currentImageIndex];
                lightboxCounter.textContent = currentImageIndex + 1;
            };
            
            const showNextImage = () => {
                currentImageIndex = (currentImageIndex + 1) % galleryImages.length;
                lightboxImg.src = galleryImages[currentImageIndex];
                lightboxCounter.textContent = currentImageIndex + 1;
            };
            
            galleryContainer.addEventListener('click', (e) => {
                const link = e.target.closest('.gallery-link');
                if (!link) return;
                e.preventDefault();
                const index = galleryImages.indexOf(link.dataset.src);
                openLightbox(index >= 0 ? index : 0);
            });

            lightboxClose.addEventListener('click', closeLightbox);
            lightbox.addEventListener('click', (e) => {
                if (e.target.id === 'lightbox') closeLightbox();
            });
            lightboxPrev.addEventListener('click', showPrevImage);
            lightboxNext.addEventListener('click', showNextImage);
            
            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (lightbox.style.display !== 'flex') return;
                if (e.key === 'ArrowLeft') showPrevImage();
                if (e.key === 'ArrowRight') showNextImage();
                if (e.key === 'Escape') closeLightbox();
            });
        }

        // 6. Fitur Salin Rekening & Tombol Gift
        function toggleGiftModal() {
            document.getElementById('gift-modal').classList.toggle('hidden');
        }

        function copyRekening(text) {
            navigator.clipboard.writeText(text).then(() => {
                const toast = document.getElementById('toast');
                toast.style.opacity = '1';
                setTimeout(() => { toast.style.opacity = '0'; }, 2000);
            });
        }

        // ==========================================
        //  KONEKSI FRONTEND KE BACKEND (PHP & SQLITE)
        // ==========================================
        const listContainer = document.getElementById('wishes-list');
        const guestNameFromLink = <?= json_encode($guest_allowed ? $guest_name : '') ?>;
        const apiUrl = './ucapan.php';

        // Fungsi Mengambil Data dari ucapan.php
        async function fetchWishes() {
            try {
                // Mencegah error URL di lingkungan preview (blob sandbox)
                if (window.location.protocol === 'blob:' || window.location.protocol === 'about:') {
                    throw new Error("Berjalan di mode Sandbox Preview");
                }

                const response = await fetch(apiUrl);
                const contentType = response.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    const text = await response.text();
                    throw new Error('Respon server bukan JSON: ' + text);
                }
                const wishes = await response.json();
                
                listContainer.innerHTML = '';
                
                if (wishes.length === 0) {
                    listContainer.innerHTML = '<p class="text-center text-sm text-gray-400 py-4">Belum ada ucapan. Jadilah yang pertama!</p>';
                    return;
                }

                wishes.forEach(wish => {
                    const item = document.createElement('div');
                    item.className = "bg-white p-4 rounded-lg shadow border-l-4 border-gold";
                    item.innerHTML = `
                        <h4 class="font-bold text-dark">${wish.nama}</h4>
                        <p class="text-xs text-gray-400 mb-2">${wish.waktu}</p>
                        <p class="text-sm text-gray-700">"${wish.pesan}"</p>
                    `;
                    listContainer.appendChild(item);
                });
            } catch (error) {
                console.log('Mode Preview: Fitur database PHP hanya berjalan di Laragon. Menampilkan data dummy.');
                // Fallback UI untuk Preview di browser/sandbox
                listContainer.innerHTML = `
                    <div class="bg-gray-50 p-4 rounded-xl border border-gray-100 flex gap-3">
                        <div class="w-10 h-10 rounded-full bg-gold text-white flex items-center justify-center font-bold font-serif shrink-0">L</div>
                        <div>
                            <h4 class="font-bold text-sm text-gray-800">Mode Preview</h4>
                            <p class="text-sm text-gray-600 mt-1">Koneksi backend belum tersedia. Jalankan index.php di Laragon untuk mencoba fitur ucapan nyata.</p>
                        </div>
                    </div>`;
            }
        }

        // Fungsi Mengirim Ucapan ke ucapan.php via POST JSON
        async function submitWish(e) {
            e.preventDefault();
            const nama = guestNameFromLink;
            const pesanInput = document.getElementById('guestMessage').value.trim();

            if (!nama) {
                alert('Kirim ucapan hanya bisa lewat link khusus ?to=Nama.');
                return;
            }

            if (!pesanInput) {
                alert('Ucapan harus diisi.');
                return;
            }

            try {
                // Cegah error post di lingkungan preview
                if (window.location.protocol === 'blob:' || window.location.protocol === 'about:') {
                    alert("Mode Preview: Fitur simpan data memerlukan PHP Server (Laragon). Silakan coba di localhost komputermu!");
                    return;
                }

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nama, pesan: pesanInput })
                });
                
                const result = await response.json();
                if (!response.ok) {
                    throw new Error(result.message || 'Gagal menyimpan ucapan.');
                }

                if (result.status === 'success') {
                    document.getElementById('wishes-form').reset();
                    // Reload data terbaru setelah berhasil disimpan
                    fetchWishes();
                } else {
                    alert(result.message || 'Gagal menyimpan ucapan.');
                }
            } catch (error) {
                console.error('Gagal mengirim ucapan:', error);
                alert("Gagal terhubung ke server backend.");
            }
        }

        document.getElementById('wishes-form').addEventListener('submit', submitWish);

        // Ambil data ucapan ketika halaman pertama kali dibuka
        fetchWishes();
    </script>
</body>
</html>