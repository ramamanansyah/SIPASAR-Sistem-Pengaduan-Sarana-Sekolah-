<?php
require_once 'config.php';

// Cek apakah user sudah login sebagai admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    redirect('index.php');
}

// Proses update status dan feedback aspirasi
if ($_POST) {
    $action = isset($_POST['action']) ? clean_input($_POST['action']) : 'update';
    $id_aspirasi = isset($_POST['id_aspirasi']) ? clean_input($_POST['id_aspirasi']) : '';
    $status = isset($_POST['status']) ? clean_input($_POST['status']) : '';
    $feedback = isset($_POST['feedback']) ? clean_input($_POST['feedback']) : '';
    
    if (empty($id_aspirasi)) {
        $_SESSION['error'] = 'Data tidak lengkap!';
        redirect('admin_dashboard.php');
    }
    
    if ($action === 'delete') {
        $delete_query = "DELETE FROM aspirasi WHERE id_aspirasi = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $id_aspirasi);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Aspirasi berhasil dihapus.';
        } else {
            $_SESSION['error'] = 'Gagal menghapus aspirasi!';
        }
        redirect('admin_dashboard.php');
    }
    
    if ($action === 'finish') {
        $status = 'Selesai';
    }
    
    if (empty($status)) {
        $_SESSION['error'] = 'Status tidak valid!';
        redirect('admin_dashboard.php');
    }
    
    $allowed_status = ['Menunggu', 'Proses', 'Selesai'];
    if (!in_array($status, $allowed_status)) {
        $_SESSION['error'] = 'Status tidak valid!';
        redirect('admin_dashboard.php');
    }
    
    $update_query = "UPDATE aspirasi SET status = ?, feedback = ? WHERE id_aspirasi = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssi", $status, $feedback, $id_aspirasi);
    
    if ($stmt->execute()) {
        if ($action === 'finish') {
            $_SESSION['success'] = 'Aspirasi ditandai selesai.';
        } else {
            $_SESSION['success'] = 'Status dan feedback aspirasi berhasil diupdate!';
        }
    } else {
        $_SESSION['error'] = 'Gagal mengupdate aspirasi!';
    }
    
    redirect('admin_dashboard.php');
} else {
    redirect('admin_dashboard.php');
}
?>
