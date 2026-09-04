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

} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => "Koneksi Database Gagal: " . $e->getMessage()]);
    exit;
}
?>
