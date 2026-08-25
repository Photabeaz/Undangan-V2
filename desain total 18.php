<?php
// ==========================================
// BACKEND SQLITE & API JSON: SIMPAN & AMBIL DATA UCAPAN
// ==========================================

$db_file = __DIR__ . '/database_undangan.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query_create_table = "CREATE TABLE IF NOT EXISTS ucapan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        pesan TEXT NOT NULL,
        kehadiran TEXT DEFAULT 'Hadir',
        waktu DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($query_create_table);

    $columns = $pdo->query("PRAGMA table_info(ucapan)")->fetchAll(PDO::FETCH_ASSOC);
    $has_kehadiran = false;
    foreach($columns as $col) {
        if($col['name'] === 'kehadiran') $has_kehadiran = true;
    }
    if(!$has_kehadiran) {
        $pdo->exec("ALTER TABLE ucapan ADD COLUMN kehadiran TEXT DEFAULT 'Hadir'");
    }

    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
    if (strpos($contentType, 'application/json') !== false) {
        $content = trim(file_get_contents("php://input"));
        $decoded = json_decode($content, true);

        if (!empty($decoded['nama']) && !empty($decoded['pesan'])) {
            $nama = htmlspecialchars(trim($decoded['nama']));
            $pesan = htmlspecialchars(trim($decoded['pesan']));
            $kehadiran = htmlspecialchars(trim($decoded['kehadiran'] ?? 'Hadir'));

            $stmt = $pdo->prepare("INSERT INTO ucapan (nama, pesan, kehadiran, waktu) VALUES (:nama, :pesan, :kehadiran, datetime('now', 'localtime'))");
            $stmt->execute([
                ':nama' => $nama,
                ':pesan' => $pesan,
                ':kehadiran' => $kehadiran
            ]);

            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Ucapan berhasil dikirim']);
            exit;
        } else {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nama dan pesan tidak boleh kosong']);
            exit;
        }
    }

    if (isset($_GET['action']) && $_GET['action'] === 'get_wishes') {
        $stmt = $pdo->query("SELECT * FROM ucapan ORDER BY id DESC");
        $ucapan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($ucapan_list as &$u) {
            $u['waktu'] = date('d M Y, H:i', strtotime($u['waktu']));
        }

        $stmt_count = $pdo->query("SELECT kehadiran, COUNT(*) as jml FROM ucapan GROUP BY kehadiran");
        $raw_counts = $stmt_count->fetchAll(PDO::FETCH_ASSOC);
        
        $counts = ['Hadir' => 0, 'Tidak Hadir' => 0, 'Ragu-ragu' => 0];
        foreach($raw_counts as $row) {
            if(isset($counts[$row['kehadiran']])) {
                $counts[$row['kehadiran']] = $row['jml'];
            }
        }

        header('Content-Type: application/json');
        echo json_encode(['wishes' => $ucapan_list, 'counts' => $counts]);
        exit;
    }

    $stmt = $pdo->query("SELECT * FROM ucapan ORDER BY id DESC");
    $ucapan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    if (strpos($contentType, 'application/json') !== false || (isset($_GET['action']) && $_GET['action'] === 'get_wishes')) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => "Koneksi Database Gagal: " . $e->getMessage()]);
        exit;
    }
    die("Koneksi Database Gagal: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Undangan Pernikahan | Bali</title>

    <meta property="og:title" content="Undangan Pernikahan Rama & Yuli" />
    <meta property="og:description" content="Selamat datang di undangan pernikahan Rama & Yuli. Terima kasih atas doa dan kehadirannya." />
    <meta property="og:image" content="foto/ft.jpg" />
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Montserrat:wght@300;400;500&display=swap" rel="stylesheet">
    
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        cream: '#fdfbf7',
                        creamDark: '#f2ece4',
                        gold: '#c5a059',
                        goldMilk: '#e6c27a',
                        textDark: '#4a4a4a',
                        btnBrown: '#8b733c'
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
        body { background-color: #fdfbf7; overflow-x: hidden; }
        body.locked { overflow-y: hidden; height: 100vh; }

        .fade-slide {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            opacity: 0; transition: opacity 1.5s ease-in-out; object-fit: cover;
        }
        .fade-slide.active { opacity: 1; }
        #cover-gate { transition: transform 1s ease-in-out, opacity 1s ease-in-out; }
        .gate-opened { transform: translateY(-100%); opacity: 0; pointer-events: none; }
        .glass-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.3); }

        @keyframes spin-slow { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .animate-spin-slow { animation: spin-slow 4s linear infinite; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #fdfbf7; }
        ::-webkit-scrollbar-thumb { background: #c5a059; border-radius: 10px; }

        /* BENTUK OVAL SEMPURNA UNTUK PAS FOTO */
        .shape-oval {
            object-fit: cover;
            width: 100%;
            height: 100%;
            border-radius: 50%;
        }
        .border-oval-wrapper {
            border-radius: 50%;
        }

        #lightbox {
            position: fixed; inset: 0; display: none; background: rgba(0,0,0,0.85);
            align-items: center; justify-content: center; z-index: 60; padding: 2rem;
        }
        #lightbox img {
            max-width: 90%; max-height: 80%; border-radius: 0.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
        }

        .video-panel {
            border: 4px solid #c5a059; padding: 0.5rem; border-radius: 0.75rem;
            background: #ffffff; max-width: 900px; margin: 1.5rem auto;
        }

        .gallery-link img { cursor: pointer; transition: transform .25s ease; }
        .gallery-link img:hover { transform: scale(1.03); }
        
        .wave-divider {
            position: absolute; bottom: 0; left: 0; width: 100%;
            overflow: hidden; line-height: 0; pointer-events: none; z-index: 15; transform: translateY(1px);
        }
        .wave-divider svg { position: relative; display: block; width: 100%; height: 70px; }
        @media (min-width: 768px) { .wave-divider svg { height: 120px; } }
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
            <button onclick="openInvitation()" class="bg-gold text-white px-8 py-3 rounded-full font-sans text-sm tracking-widest hover:bg-yellow-700 transition shadow-lg">
                <i class="fa-solid fa-envelope-open mr-2"></i> BUKA UNDANGAN
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
            <div class="relative z-20 text-center text-white flex flex-col items-center" data-aos="fade-up" data-aos-duration="1500">
                <p class="font-sans tracking-[0.2em] mb-2 uppercase text-xs md:text-sm">We Are Getting Married</p>
                <h2 class="font-serif text-6xl md:text-8xl italic mb-4">Rama & Yuli </h2>
                <p class="font-serif text-xl md:text-2xl mt-4">7 . 6 . 2026</p>
            </div>
            <div class="absolute bottom-[20%] z-25 animate-bounce">
                <i class="fa-solid fa-chevron-down text-white text-2xl opacity-70"></i>
            </div>
            <div class="wave-divider">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                    <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V120H0Z" fill="rgba(253, 251, 247, 0.35)"></path>
                    <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5V120H0Z" fill="rgba(253, 251, 247, 0.65)"></path>
                    <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V120H0Z" fill="#fdfbf7"></path>
                </svg>
            </div>
        </section>

        <!-- Bagian Pengantin -->
        <section class="relative pb-20 pt-10">
            <div class="absolute inset-0 bg-cover bg-center opacity-20 pointer-events-none" style="background-image: url('foto/begron.jpg');"></div>
            <div class="relative z-10 container mx-auto px-6 text-center max-w-4xl">
                <div data-aos="fade-up">
                    <h3 class="font-serif text-3xl font-bold mb-4 text-gold">Om Swastiastu</h3>
                    <p class="text-sm md:text-base leading-relaxed mb-12">
                        Atas Asung Kertha Wara Nugraha Ida Sang Hyang Widhi Wasa/Tuhan Yang Maha Esa, kami bermaksud mengundang Bapak/Ibu/Saudara/i pada acara Resepsi Pernikahan putra-putri kami.
                    </p>
                </div>

                <div class="flex flex-col md:flex-row items-center justify-center gap-12 mt-10">
                    <div class="flex-1 text-center" data-aos="fade-up">
                        <!-- Border oval sempurna -->
                        <div class="border-oval-wrapper w-64 h-80 mx-auto overflow-hidden border-[5px] border-goldMilk p-1 shadow-[0_10px_25px_rgba(230,194,122,0.4)] mb-6">
                            <img src="foto/ft (4).jpg" alt="Groom" class="w-full h-full object-cover shape-oval">
                        </div>
                        <h4 class="font-serif text-3xl font-bold mb-2">I Kadek Rama</h4>
                        <p class="text-sm mb-1">Putra Pertama dari pasangan</p>
                        <p class="font-semibold text-sm mb-3"> I Wayan Mugiana & Ni Kadek Kartini (Alm)</p>
                        <p class="text-xs text-gray-500 italic mb-4"><i class="fa-solid fa-location-dot text-gold mr-1"></i> Br. Selat, Buahan Kaja, Payangan</p>
                        <a href="#" class="inline-block bg-white border border-gray-200 text-gray-600 px-4 py-1.5 rounded-full text-xs font-semibold hover:bg-gold hover:text-white hover:border-gold transition shadow-sm">
                            <i class="fa-brands fa-instagram text-lg align-middle mr-1"></i> @rama_ig
                        </a>
                    </div>

                    <div class="text-5xl font-serif text-gold" data-aos="zoom-in">&</div>

                    <div class="flex-1 text-center" data-aos="fade-up">
                        <div class="border-oval-wrapper w-64 h-80 mx-auto overflow-hidden border-[5px] border-goldMilk p-1 shadow-[0_10px_25px_rgba(230,194,122,0.4)] mb-6">
                            <img src="foto/ft (5).jpg" alt="Bride" class="w-full h-full object-cover shape-oval">
                        </div>
                        <h4 class="font-serif text-3xl font-bold mb-2">Ni Luh Putu Sri Yulianti</h4>
                        <p class="text-sm mb-1">Putri Pertama dari pasangan</p>
                        <p class="font-semibold text-sm mb-3">I Putu Alit Saputra & Ni Kadek Noridani</p>
                        <p class="text-xs text-gray-500 italic mb-4"><i class="fa-solid fa-location-dot text-gold mr-1"></i> Br. Dinas Kaja, Denpasar</p>
                        <a href="#" class="inline-block bg-white border border-gray-200 text-gray-600 px-4 py-1.5 rounded-full text-xs font-semibold hover:bg-gold hover:text-white hover:border-gold transition shadow-sm">
                            <i class="fa-brands fa-instagram text-lg align-middle mr-1"></i> @yulianti_ig
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Desain Resepsi Sesuai Referensi -->
        <section class="pt-20 pb-10 relative bg-creamDark">
            <div class="container mx-auto px-4 relative z-10">
                <!-- Wrapper dengan atap melengkung (arch) -->
                <div class="max-w-3xl mx-auto bg-white rounded-t-[100px] md:rounded-t-[180px] p-8 md:p-14 shadow-lg text-center" data-aos="fade-up">
                    <h3 class="font-serif text-4xl mb-6 text-gold font-bold">Resepsi Pernikahan</h3>
                    
                    <p class="text-sm md:text-base text-gray-600 mb-10 leading-relaxed max-w-xl mx-auto">
                        Merupakan suatu kehormatan dan kebahagiaan bagi kami, apabila Bapak/Ibu/Saudara/i berkenan hadir untuk memberikan doa restu kepada kami pada :
                    </p>
                    
                    <!-- Kotak Tanggal -->
                    <div class="border border-gold p-6 mb-4 rounded-sm" data-aos="zoom-in">
                        <i class="fa-regular fa-calendar text-4xl text-gold mb-3"></i>
                        <p class="font-bold text-xl text-gold mb-1">Minggu,</p>
                        <p class="text-gray-700 tracking-wide">7 Juni 2026</p>
                    </div>

                    <!-- Kotak Waktu -->
                    <div class="border border-gold p-6 mb-8 rounded-sm" data-aos="zoom-in" data-aos-delay="100">
                        <i class="fa-regular fa-clock text-4xl text-gold mb-3"></i>
                        <p class="font-bold text-xl text-gold mb-1">13.00 WITA</p>
                        <p class="text-gray-700">s/d Selesai</p>
                    </div>

                    <div class="mb-8 text-gray-700 font-medium" data-aos="fade-up">
                        <i class="fa-solid fa-location-dot text-gold mr-2"></i> 
                        Br. Gambih, Desa Buahan, Kec. Payangan
                    </div>

                    <a href="https://maps.app.goo.gl/B9cpzbiL5PhXhvDW9" target="_blank" class="block w-full bg-btnBrown text-white py-3.5 rounded-sm text-sm font-semibold hover:bg-yellow-800 transition shadow" data-aos="fade-up">
                        <i class="fa-solid fa-map-location-dot mr-2"></i> Buka Maps
                    </a>
                </div>
            </div>
        </section>

        <!-- Hitung Mundur -->
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
                <a href="https://calendar.google.com/calendar/render?action=TEMPLATE&text=Pernikahan+Rama+%26+Yuli&dates=20260607T050000Z/20260607T110000Z&details=Resepsi+Pernikahan+Rama+dan+Yuli&location=Br+Gambih+Desa+Buahan+Kec+Payangan" target="_blank" class="inline-block bg-gold text-white px-8 py-3 rounded-full text-sm font-semibold tracking-wide shadow-lg hover:bg-yellow-700 transition" data-aos="fade-up">
                    <i class="fa-regular fa-calendar-check mr-2"></i> SAVE THE DATE
                </a>
            </div>
        </section>

        <!-- Galeri & Video -->
        <section class="py-20 relative overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center opacity-20 pointer-events-none" style="background-image: url('foto/bg1.jpg');"></div>
            <div class="container mx-auto px-6">
                <div class="text-center mb-12" data-aos="fade-up">
                    <h3 class="font-serif text-4xl text-gold mb-2">Wedding Gallery</h3>
                    <p class="text-sm text-gray-500">Momen bahagia yang kami abadikan.</p>
                </div>
                
                <div class="columns-2 md:columns-4 gap-1 md:gap-2 space-y-1 md:space-y-2" id="gallery-container"></div>

                <!-- Lightbox Full Function for gallery -->
                <div id="lightbox" role="dialog" aria-hidden="true" class="fixed inset-0 hidden bg-black/90 z-[60] flex items-center justify-center">
                    <button id="lightbox-prev" class="absolute left-4 md:left-10 top-1/2 transform -translate-y-1/2 text-white text-3xl md:text-5xl hover:text-gold transition z-[70]"><i class="fa-solid fa-chevron-left"></i></button>
                    <button class="lightbox-close absolute top-6 right-6 text-white text-4xl hover:text-gold transition z-[70]">&times;</button>
                    <button id="lightbox-next" class="absolute right-4 md:right-10 top-1/2 transform -translate-y-1/2 text-white text-3xl md:text-5xl hover:text-gold transition z-[70]"><i class="fa-solid fa-chevron-right"></i></button>
                    <img src="" alt="Preview image" class="max-w-[90%] max-h-[85vh] object-contain rounded-sm shadow-2xl relative z-[65]">
                    <p class="absolute bottom-6 left-1/2 transform -translate-x-1/2 text-white text-sm z-[70]"><span id="lightbox-counter">1</span> / <span id="lightbox-total">20</span></p>
                </div>

                <div class="video-panel border-2 border-[#D4AF37] rounded-lg mt-12 p-6 font-serif text-center" data-aos="fade-up">
                    <h3 class="font-serif font-semibold text-4xl text-gold mb-6">Momen Kami</h3>
                    <div class="aspect-w-16 aspect-h-9 max-w-3xl mx-auto">
                        <iframe width="100%" height="450" src="https://www.youtube.com/embed/dQw4w9WgXcQ" frameborder="0" allowfullscreen class="rounded-md"></iframe>
                    </div>
                </div>
            </div>
        </section>

        <!-- Kado Digital -->
        <section class="py-16 bg-cream text-center">
            <div class="container mx-auto px-6" data-aos="zoom-in">
                <h3 class="font-serif text-4xl text-gold mb-4">Wedding Gift</h3>
                <p class="text-sm text-gray-600 mb-8 max-w-xl mx-auto">Bagi keluarga dan sahabat yang ingin mengirimkan kado, silakan menekan tombol di bawah ini.</p>
                <button onclick="toggleGiftModal()" class="bg-gold text-white px-8 py-3 rounded-full text-sm font-semibold hover:bg-yellow-700 transition shadow-lg">
                    <i class="fa-solid fa-gift mr-2"></i> Kirim Kado Digital
                </button>
            </div>
        </section>
        
        <!-- Buku Tamu & RSVP -->
        <section id="guestbook" class="py-16 px-6 bg-creamDark">
            <div class="content-relative max-w-2xl mx-auto">
                <h2 class="font-serif text-4xl text-gold font-bold mb-8 text-center" data-aos="fade-up">RSVP & Ucapan</h2>
                
                <div class="flex justify-center gap-4 mb-8" data-aos="fade-up">
                    <div class="bg-white px-4 py-3 rounded-lg shadow text-center flex-1 border-t-2 border-green-500">
                        <p class="text-2xl font-bold text-gray-800" id="count-hadir">0</p>
                        <p class="text-xs text-gray-500">Hadir</p>
                    </div>
                    <div class="bg-white px-4 py-3 rounded-lg shadow text-center flex-1 border-t-2 border-red-500">
                        <p class="text-2xl font-bold text-gray-800" id="count-tidak-hadir">0</p>
                        <p class="text-xs text-gray-500">Tidak Hadir</p>
                    </div>
                    <div class="bg-white px-4 py-3 rounded-lg shadow text-center flex-1 border-t-2 border-yellow-500">
                        <p class="text-2xl font-bold text-gray-800" id="count-ragu">0</p>
                        <p class="text-xs text-gray-500">Ragu-ragu</p>
                    </div>
                </div>

                <form id="wishes-form" class="bg-white p-6 rounded-xl shadow-lg mb-8" data-aos="fade-up">
                    <div class="mb-4">
                        <label class="block text-sm mb-1 text-gray-600">Nama Anda</label>
                        <input id="guestName" name="nama" type="text" value="<?= htmlspecialchars($guest_name) ?>" readonly class="w-full border border-gray-300 p-2 rounded bg-gray-100 text-gray-700 cursor-not-allowed focus:outline-none focus:border-gold">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm mb-1 text-gray-600">Konfirmasi Kehadiran</label>
                        <select id="guestAttendance" name="kehadiran" class="w-full border border-gray-300 p-2 rounded focus:outline-none focus:border-gold bg-white">
                            <option value="Hadir">Hadir</option>
                            <option value="Tidak Hadir">Tidak Hadir</option>
                            <option value="Ragu-ragu">Ragu-ragu</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm mb-1 text-gray-600">Ucapan</label>
                        <textarea id="guestMessage" name="pesan" rows="3" required class="w-full border border-gray-300 p-2 rounded focus:outline-none focus:border-gold" placeholder="Tulis doa & ucapan Anda di sini..."></textarea>
                    </div>
                    <button type="submit" class="w-full bg-gold text-white py-2 rounded hover:bg-yellow-600 transition shadow <?= $guest_allowed ? '' : 'opacity-55 cursor-not-allowed' ?>" <?= $guest_allowed ? '' : 'disabled' ?> >
                        Kirim RSVP & Ucapan
                    </button>
                    <?php if (!$guest_allowed): ?>
                        <p class="text-xs text-red-500 mt-2 text-center"><span class="font-semibold">Anda harus menggunakan link undangan khusus (contoh: ?to=NamaAnda) untuk dapat mengirim ucapan.</span></p>
                    <?php endif; ?>
                </form>

                <div id="wishes-list" class="max-h-80 overflow-y-auto pr-2 space-y-4"></div>
            </div>
        </section>

        <!-- Footer -->
        <section class="py-16 bg-gray-900 text-white text-center relative overflow-hidden bg-cover bg-center" style="background-image: url('foto/ft (9) 1.jpg');">
             <div class="absolute inset-0 bg-gradient-to-t from-black via-black/30 to-transparent z-0"></div>
             <div class="container mx-auto px-6 relative z-10" data-aos="fade-up">
                 <p class="text-sm md:text-base leading-relaxed mb-8 max-w-2xl mx-auto">
                     Merupakan suatu kehormatan dan kebahagiaan bagi kami apabila Bapak/Ibu/Saudara/i berkenan hadir untuk memberikan doa restu kepada kedua mempelai.<br><br>
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

    <!-- Modal Kado Digital -->
    <div id="gift-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/60 backdrop-blur-sm transition-opacity">
        <div class="bg-white w-11/12 max-w-md p-6 rounded-2xl shadow-2xl relative text-center">
            <button onclick="toggleGiftModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-800"><i class="fa-solid fa-xmark text-xl"></i></button>
            <h3 class="font-serif text-2xl text-gold font-bold mb-4">Kirim Kado</h3>
            <p class="text-sm text-gray-600 mb-6">Silakan transfer ke nomor rekening di bawah ini:</p>
            <div class="bg-gray-100 p-4 rounded-xl mb-4 border border-gray-200">
                <p class="font-bold text-blue-900 mb-1 text-lg">Bank Mandiri</p>
                <p class="text-gray-700 tracking-widest text-xl mb-2" id="rek-mandiri">1234567890123</p>
                <p class="text-sm text-gray-500 mb-4">a.n. I Kadek Rama</p>
                <button onclick="copyRekening('1234567890123')" class="bg-white border-2 border-gold text-gold hover:bg-gold hover:text-white px-6 py-2 rounded-full text-sm transition shadow-sm w-full font-semibold">
                    <i class="fa-regular fa-copy mr-1"></i> Salin Nomor Rekening
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-4 italic">Terima kasih atas doa & hadiah dari Anda.</p>
        </div>
    </div>

    <div id="toast" class="fixed bottom-5 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white px-4 py-2 rounded-lg opacity-0 transition-opacity duration-300 pointer-events-none z-50">
        Nomor Rekening Tersalin!
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: false, offset: 50, duration: 800, easing: 'ease-out-cubic' });

        const bgMusic = document.getElementById('backsound');
        const musicBtn = document.getElementById('music-control-btn');
        const musicIcon = document.getElementById('music-icon');

        function openInvitation() {
            const gate = document.getElementById('cover-gate');
            const main = document.getElementById('main-content');
            gate.classList.add('gate-opened');
            setTimeout(() => {
                gate.style.display = 'none';
                main.classList.remove('hidden');
                document.body.classList.remove('locked');
                playMusic();
                musicBtn.classList.remove('hidden');
                AOS.refresh();
            }, 1000);
        }

        function playMusic() {
            bgMusic.play().then(() => { musicIcon.className = "fa-solid fa-compact-disc text-xl animate-spin-slow"; }).catch(err => {});
        }

        function toggleMusic() {
            if (bgMusic.paused) { bgMusic.play(); musicIcon.className = "fa-solid fa-compact-disc text-xl animate-spin-slow"; } 
            else { bgMusic.pause(); musicIcon.className = "fa-solid fa-circle-play text-xl"; }
        }

        const slides = document.querySelectorAll('.fade-slide');
        let currentSlide = 0;
        setInterval(() => {
            slides[currentSlide].classList.remove('active');
            currentSlide = (currentSlide + 1) % slides.length;
            slides[currentSlide].classList.add('active');
        }, 4000);

        const countDownDate = new Date("Jun 7, 2026 13:00:00").getTime();
        const intervalTimer = setInterval(function() {
            const now = new Date().getTime();
            const distance = countDownDate - now;
            document.getElementById("days").innerHTML = Math.floor(distance / (1000 * 60 * 60 * 24)).toString().padStart(2, '0');
            document.getElementById("hours").innerHTML = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)).toString().padStart(2, '0');
            document.getElementById("minutes").innerHTML = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60)).toString().padStart(2, '0');
            document.getElementById("seconds").innerHTML = Math.floor((distance % (1000 * 60)) / 1000).toString().padStart(2, '0');
            if (distance < 0) clearInterval(intervalTimer);
        }, 1000);

        function toggleGiftModal() { document.getElementById('gift-modal').classList.toggle('hidden'); }
        function copyRekening(text) {
            navigator.clipboard.writeText(text).then(() => {
                const toast = document.getElementById('toast');
                toast.style.opacity = '1';
                setTimeout(() => { toast.style.opacity = '0'; }, 2000);
            });
        }

        // ==========================================
        //  GALERI FOTO & LIGHTBOX LENGKAP (20 FOTO)
        // ==========================================
        const galleryContainer = document.getElementById('gallery-container');
        const galleryImages = [];
        // Loop untuk memuat 20 foto
        for (let i = 1; i <= 20; i++) {
            const src = `foto/ph (${i}).jpg`;
            galleryImages.push(src);
            const a = document.createElement('a');
            a.href = '#'; 
            a.className = `gallery-link block overflow-hidden rounded-lg shadow-sm break-inside-avoid`;
            a.setAttribute('data-aos', 'zoom-in'); 
            a.setAttribute('data-src', src);
            a.innerHTML = `<img src="${src}" class="w-full h-auto object-cover hover:scale-105 transition duration-300">`;
            galleryContainer.appendChild(a);
        }

        const lightbox = document.getElementById('lightbox');
        let currentImageIndex = 0;
        if (lightbox) {
            const lightboxImg = lightbox.querySelector('img');
            const lightboxCounter = document.getElementById('lightbox-counter');
            const lightboxTotal = document.getElementById('lightbox-total');
            const btnPrev = document.getElementById('lightbox-prev');
            const btnNext = document.getElementById('lightbox-next');
            const btnClose = lightbox.querySelector('.lightbox-close');
            
            lightboxTotal.textContent = galleryImages.length;
            
            const openLightbox = (index) => {
                currentImageIndex = index;
                lightboxImg.src = galleryImages[currentImageIndex];
                lightboxCounter.textContent = currentImageIndex + 1;
                lightbox.style.display = 'flex';
            };
            
            const closeLightbox = () => { lightbox.style.display = 'none'; };
            
            // Navigasi Prev/Next
            const showPrev = (e) => {
                if(e) e.stopPropagation();
                currentImageIndex = (currentImageIndex - 1 + galleryImages.length) % galleryImages.length;
                openLightbox(currentImageIndex);
            };
            const showNext = (e) => {
                if(e) e.stopPropagation();
                currentImageIndex = (currentImageIndex + 1) % galleryImages.length;
                openLightbox(currentImageIndex);
            };
            
            galleryContainer.addEventListener('click', (e) => {
                const link = e.target.closest('.gallery-link');
                if (!link) return; 
                e.preventDefault();
                const index = galleryImages.indexOf(link.dataset.src);
                if (index >= 0) openLightbox(index);
            });

            btnClose.addEventListener('click', closeLightbox);
            btnPrev.addEventListener('click', showPrev);
            btnNext.addEventListener('click', showNext);
            
            // Tutup jika klik area hitam di luar gambar
            lightbox.addEventListener('click', (e) => {
                if (e.target === lightbox) closeLightbox();
            });
            
            // Kontrol menggunakan keyboard
            document.addEventListener('keydown', (e) => {
                if (lightbox.style.display !== 'flex') return;
                if (e.key === 'ArrowLeft') showPrev();
                if (e.key === 'ArrowRight') showNext();
                if (e.key === 'Escape') closeLightbox();
            });
        }

        // ==========================================
        //  SISTEM UCAPAN & RSVP BACKEND
        // ==========================================
        const listContainer = document.getElementById('wishes-list');
        const guestNameFromLink = <?= json_encode($guest_allowed ? $guest_name : '') ?>;
        const apiUrl = window.location.pathname + '?action=get_wishes';

        async function fetchWishes() {
            try {
                if (window.location.protocol === 'blob:' || window.location.protocol === 'about:') return;
                
                const response = await fetch(apiUrl);
                const data = await response.json();
                
                document.getElementById('count-hadir').innerText = data.counts['Hadir'] || 0;
                document.getElementById('count-tidak-hadir').innerText = data.counts['Tidak Hadir'] || 0;
                document.getElementById('count-ragu').innerText = data.counts['Ragu-ragu'] || 0;

                listContainer.innerHTML = '';
                if (data.wishes.length === 0) {
                    listContainer.innerHTML = '<p class="text-center text-sm text-gray-400 py-4">Belum ada ucapan. Jadilah yang pertama!</p>';
                    return;
                }

                data.wishes.forEach(wish => {
                    const statusColor = wish.kehadiran === 'Hadir' ? 'text-green-600' : (wish.kehadiran === 'Tidak Hadir' ? 'text-red-600' : 'text-yellow-600');
                    const item = document.createElement('div');
                    item.className = "bg-white p-4 rounded-lg shadow border-l-4 border-gold";
                    item.innerHTML = `
                        <div class="flex justify-between items-start mb-1">
                            <h4 class="font-bold text-dark">${wish.nama}</h4>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-gray-100 font-semibold ${statusColor}">${wish.kehadiran}</span>
                        </div>
                        <p class="text-xs text-gray-400 mb-2">${wish.waktu}</p>
                        <p class="text-sm text-gray-700">"${wish.pesan}"</p>
                    `;
                    listContainer.appendChild(item);
                });
            } catch (error) { console.log('Server API error/mode preview'); }
        }

        async function submitWish(e) {
            e.preventDefault();
            const nama = guestNameFromLink;
            const pesanInput = document.getElementById('guestMessage').value.trim();
            const kehadiranInput = document.getElementById('guestAttendance').value;

            if (!nama || !pesanInput) return alert('Nama dan ucapan harus diisi, pastikan via link khusus.');

            try {
                const response = await fetch(window.location.pathname + (window.location.search || ''), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nama, pesan: pesanInput, kehadiran: kehadiranInput })
                });
                
                const result = await response.json();
                if (result.status === 'success') {
                    document.getElementById('guestMessage').value = '';
                    fetchWishes();
                } else {
                    alert(result.message || 'Gagal menyimpan ucapan.');
                }
            } catch (error) { alert("Gagal terhubung ke server backend."); }
        }

        document.getElementById('wishes-form').addEventListener('submit', submitWish);
        fetchWishes();
    </script>
</body>
</html>