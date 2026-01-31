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

if ($_SESSION['role'] == 'admin') {
    // Ambil semua aspirasi untuk admin
    $query = "SELECT a.*, s.nama, s.kelas, k.ket_kategori 
              FROM aspirasi a 
              JOIN siswa s ON a.nisn = s.nisn 
              JOIN kategori k ON a.id_kategori = k.id_kategori 
              ORDER BY a.tanggal DESC, a.id_aspirasi DESC";
    $result = $conn->query($query);
    
    $aspirasi = [];
    while ($row = $result->fetch_assoc()) {
        $aspirasi[] = $row;
    }
    
    // Hitung statistik
    $stats_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Menunggu' THEN 1 ELSE 0 END) as menunggu,
                        SUM(CASE WHEN status = 'Proses' THEN 1 ELSE 0 END) as proses,
                        SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) as selesai
                    FROM aspirasi";
    $stats_result = $conn->query($stats_query);
    $stats = $stats_result->fetch_assoc();
    $stats['responded'] = ($stats['proses'] ?? 0) + ($stats['selesai'] ?? 0);
    
    echo json_encode([
        'success' => true,
        'aspirasi' => $aspirasi,
        'stats' => $stats,
        'last_update' => date('Y-m-d H:i:s')
    ]);
    
} elseif ($_SESSION['role'] == 'siswa') {
    // Ambil aspirasi siswa yang login
    $nisn = $_SESSION['nisn'];
    $query = "SELECT a.*, k.ket_kategori 
              FROM aspirasi a 
              JOIN kategori k ON a.id_kategori = k.id_kategori 
              WHERE a.nisn = ? 
              ORDER BY a.tanggal DESC, a.id_aspirasi DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $nisn);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $aspirasi = [];
    while ($row = $result->fetch_assoc()) {
        $aspirasi[] = $row;
    }
    
    // Hitung statistik siswa
    $stats_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Menunggu' THEN 1 ELSE 0 END) as menunggu,
                        SUM(CASE WHEN status = 'Proses' THEN 1 ELSE 0 END) as proses,
                        SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) as selesai
                    FROM aspirasi WHERE nisn = ?";
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param("s", $nisn);
    $stmt->execute();
    $stats_result = $stmt->get_result();
    $stats = $stats_result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'aspirasi' => $aspirasi,
        'stats' => $stats,
        'last_update' => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode(['error' => 'Invalid role']);
}
?>
