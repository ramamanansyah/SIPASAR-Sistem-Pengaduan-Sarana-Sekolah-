<?php
require_once 'config.php';

// Cek apakah user sudah login sebagai siswa
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'siswa') {
    redirect('index.php');
}

// Proses input aspirasi siswa
if ($_POST) {
    $nama = clean_input($_POST['nama']);
    $nisn = clean_input($_POST['nisn']);
    $kelas = clean_input($_POST['kelas']);
    $kategori = clean_input($_POST['kategori']);
    $lokasi = clean_input($_POST['lokasi']);
    $keterangan = clean_input($_POST['keterangan']);
    
    // Validasi input wajib
    if (empty($nama) || empty($nisn) || empty($kategori) || empty($lokasi) || empty($keterangan)) {
        $_SESSION['error'] = 'Semua field wajib diisi!';
        redirect('form_aspirasi.php');
    }
    
    // Validasi NISN sesuai session
    if ($nisn != $_SESSION['nisn']) {
        $_SESSION['error'] = 'NISN tidak sesuai dengan akun yang login!';
        redirect('form_aspirasi.php');
    }
    
    // Validasi kategori ada di database
    $check_kategori = "SELECT id_kategori FROM kategori WHERE id_kategori = ?";
    $stmt = $conn->prepare($check_kategori);
    $stmt->bind_param("i", $kategori);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $_SESSION['error'] = 'Kategori tidak valid!';
        redirect('form_aspirasi.php');
    }
    
    // Validasi panjang keterangan minimal
    if (strlen($keterangan) < 10) {
        $_SESSION['error'] = 'Keterangan aspirasi minimal 10 karakter!';
        redirect('form_aspirasi.php');
    }
    
    // Insert data aspirasi ke database
    $insert_query = "INSERT INTO aspirasi (nisn, id_kategori, lokasi, keterangan, tanggal, status) 
                     VALUES (?, ?, ?, ?, CURDATE(), 'Menunggu')";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("siss", $nisn, $kategori, $lokasi, $keterangan);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Aspirasi berhasil dikirim! Status awal: Menunggu. Admin akan segera menindaklanjuti aspirasi Anda.';
        redirect('siswa_dashboard.php');
    } else {
        $_SESSION['error'] = 'Gagal mengirim aspirasi! Silakan coba lagi.';
        redirect('form_aspirasi.php');
    }
} else {
    redirect('form_aspirasi.php');
}
?>