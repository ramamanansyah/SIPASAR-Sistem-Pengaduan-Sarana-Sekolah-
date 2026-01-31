<?php
// File konfigurasi koneksi database SIPASAR
// Menggunakan MySQLi untuk koneksi ke database

// Set session lifetime to 24 hours to match client-side persistence requirement
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params(86400);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$username = "root";
$password = "";
$database = "sipasar";

// Membuat koneksi ke database MySQL
$conn = new mysqli($host, $username, $password, $database);

// Cek koneksi database
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Set charset untuk mendukung karakter Indonesia
$conn->set_charset("utf8");

// Fungsi untuk membersihkan input dari user (mencegah SQL injection)
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($conn, $data);
}

// Fungsi untuk redirect halaman
function redirect($url) {
    header("Location: $url");
    exit();
}

// CSRF Protection Helper
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}
?>