<?php
require_once 'config.php';

// Proses registrasi berdasarkan role yang dipilih
if ($_POST) {
    $role = clean_input($_POST['role']);
    
    if ($role == 'admin') {
        // Proses registrasi admin
        $username = clean_input($_POST['username']);
        $password = clean_input($_POST['password']);
        
        // Validasi input
        if (empty($username) || empty($password)) {
            $_SESSION['error'] = 'Username dan password harus diisi!';
            redirect('register.php');
        }
        
        // Cek apakah username sudah ada
        $check_query = "SELECT username FROM admin WHERE username = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['error'] = 'Username sudah digunakan!';
            redirect('register.php');
        }
        
        // Insert admin baru
        $insert_query = "INSERT INTO admin (username, password) VALUES (?, MD5(?))";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ss", $username, $password);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Registrasi admin berhasil! Silakan login.';
            redirect('register.php');
        } else {
            $_SESSION['error'] = 'Registrasi gagal! Coba lagi.';
            redirect('register.php');
        }
        
    } elseif ($role == 'siswa') {
        // Proses registrasi siswa
        $nama = clean_input($_POST['nama']);
        $nisn = clean_input($_POST['nisn']);
        $kelas = clean_input($_POST['kelas']);
        $password = clean_input($_POST['password']);
        
        // Validasi input
        if (empty($nama) || empty($nisn) || empty($kelas) || empty($password)) {
            $_SESSION['error'] = 'Nama, NISN, kelas, dan password harus diisi!';
            redirect('register.php');
        }
        
        // Validasi nama (minimal 3 karakter, hanya huruf dan spasi)
        if (strlen($nama) < 3 || !preg_match('/^[a-zA-Z\s]+$/', $nama)) {
            $_SESSION['error'] = 'Nama minimal 3 karakter dan hanya boleh huruf dan spasi!';
            redirect('register.php');
        }
        
        // Validasi NISN (harus 10 digit)
        if (!preg_match('/^[0-9]{10}$/', $nisn)) {
            $_SESSION['error'] = 'NISN harus berupa 10 digit angka!';
            redirect('register.php');
        }
        
        // Validasi password (minimal 6 karakter)
        if (strlen($password) < 6) {
            $_SESSION['error'] = 'Password minimal 6 karakter!';
            redirect('register.php');
        }
        
        // Cek apakah NISN sudah ada
        $check_query = "SELECT nisn FROM siswa WHERE nisn = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("s", $nisn);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['error'] = 'NISN sudah terdaftar!';
            redirect('register.php');
        }
        
        // Insert siswa baru
        $insert_query = "INSERT INTO siswa (nisn, nama, kelas, password) VALUES (?, ?, ?, MD5(?))";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ssss", $nisn, $nama, $kelas, $password);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Registrasi siswa berhasil! Silakan login dengan NISN Anda.';
            redirect('register.php');
        } else {
            $_SESSION['error'] = 'Registrasi gagal! Coba lagi.';
            redirect('register.php');
        }
    } else {
        $_SESSION['error'] = 'Pilih role terlebih dahulu!';
        redirect('register.php');
    }
} else {
    redirect('register.php');
}
?>