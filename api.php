<?php
// ==========================================
// BACKEND SQLITE & API JSON: SIMPAN & AMBIL DATA UCAPAN
// ==========================================

$host = 'localhost';
$dbname = 'database_undangan';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo->exec("USE `$dbname`");

    $query_create_table = "CREATE TABLE IF NOT EXISTS ucapan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(255) NOT NULL,
        pesan TEXT NOT NULL,
        kehadiran VARCHAR(50) DEFAULT 'Hadir',
        waktu DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($query_create_table);

    $columns = $pdo->query("SHOW COLUMNS FROM ucapan")->fetchAll(PDO::FETCH_ASSOC);
    $has_kehadiran = false;
    foreach($columns as $col) {
        if($col['Field'] === 'kehadiran') $has_kehadiran = true;
    }
    if(!$has_kehadiran) {
        $pdo->exec("ALTER TABLE ucapan ADD COLUMN kehadiran VARCHAR(50) DEFAULT 'Hadir'");
    }

    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $content = trim(file_get_contents("php://input"));
        $decoded = json_decode($content, true);

        if (!empty($decoded['nama']) && !empty($decoded['pesan'])) {
            $nama = htmlspecialchars(trim($decoded['nama']));
            $pesan = htmlspecialchars(trim($decoded['pesan']));
            $kehadiran = htmlspecialchars(trim($decoded['kehadiran'] ?? 'Hadir'));

            $stmt = $pdo->prepare("INSERT INTO ucapan (nama, pesan, kehadiran, waktu) VALUES (:nama, :pesan, :kehadiran, NOW())");
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
