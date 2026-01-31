<?php
session_start();
require_once '../config.php';

// Set header untuk JSON response
header('Content-Type: application/json');

// Cek apakah user sudah login
if (!isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$notifications = [];

if ($_SESSION['role'] == 'admin') {
    // Notifikasi untuk admin - aspirasi baru dalam 24 jam terakhir
    $query = "SELECT COUNT(*) as new_count 
              FROM aspirasi 
              WHERE tanggal >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $result = $conn->query($query);
    $new_aspirasi = $result->fetch_assoc()['new_count'];
    
    if ($new_aspirasi > 0) {
        $notifications[] = [
            'type' => 'info',
            'message' => "Ada {$new_aspirasi} aspirasi baru dalam 24 jam terakhir",
            'count' => $new_aspirasi
        ];
    }
    
    // Aspirasi yang belum ditanggapi
    $query = "SELECT COUNT(*) as pending_count 
              FROM aspirasi 
              WHERE status = 'Menunggu'";
    $result = $conn->query($query);
    $pending_count = $result->fetch_assoc()['pending_count'];
    
    if ($pending_count > 0) {
        $notifications[] = [
            'type' => 'warning',
            'message' => "{$pending_count} aspirasi menunggu ditanggapi",
            'count' => $pending_count
        ];
    }
    
} elseif ($_SESSION['role'] == 'siswa') {
    // Notifikasi untuk siswa - update status aspirasi
    $nisn = $_SESSION['nisn'];
    $query = "SELECT COUNT(*) as updated_count 
              FROM aspirasi 
              WHERE nisn = ? AND status != 'Menunggu' AND feedback IS NOT NULL 
              AND DATE(tanggal) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $nisn);
    $stmt->execute();
    $result = $stmt->get_result();
    $updated_count = $result->fetch_assoc()['updated_count'];
    
    if ($updated_count > 0) {
        $notifications[] = [
            'type' => 'success',
            'message' => "Ada {$updated_count} aspirasi yang telah diupdate admin",
            'count' => $updated_count
        ];
    }
}

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'timestamp' => time()
]);
?>