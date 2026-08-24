<?php
// Mengambil nama tamu dari URL (misal: index.php?to=Budi)
// Jika tidak ada di URL, default nama adalah 'Tamu Undangan'
$guest_name = isset($_GET['to']) && !empty(trim($_GET['to'])) 
    ? htmlspecialchars($_GET['to'], ENT_QUOTES, 'UTF-8') 
    : 'Tamu Undangan';

header('Content-Type: application/json; charset=utf-8');

$db_file = __DIR__ . '/database_undangan.sqlite';

try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query_create_table = "CREATE TABLE IF NOT EXISTS ucapan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        pesan TEXT NOT NULL,
        waktu DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($query_create_table);

    $request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($request_method === 'POST') {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $input = [];

        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?? [];
        } else {
            $input = $_POST;
        }

        $nama = trim($input['nama'] ?? '');
        $pesan = trim($input['pesan'] ?? '');

        if ($nama === '' || $pesan === '') {
            echo json_encode(['status' => 'error', 'message' => 'Nama dan ucapan wajib diisi.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO ucapan (nama, pesan, waktu) VALUES (:nama, :pesan, datetime('now', 'localtime'))");
        $stmt->execute([
            ':nama' => $nama,
            ':pesan' => $pesan,
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Ucapan berhasil disimpan.']);
        exit;
    }

    // GET: ambil semua ucapan
    $stmt = $pdo->query("SELECT * FROM ucapan ORDER BY id DESC");
    $ucapan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($ucapan_list, JSON_UNESCAPED_UNICODE);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Koneksi database gagal: ' . $e->getMessage()]);
    exit;
}
