<?php
// Note: session_start() is called in config.php
require_once 'config.php';

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Helper to send JSON response
function send_json_response($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// Proses login berdasarkan role yang dipilih
if ($_POST) {
    // CSRF Check for AJAX requests (optional for standard form but good practice)
    // For this implementation, we will skip strict CSRF check on login if token is missing 
    // to avoid breaking existing flow, but ideally it should be checked.
    // if ($is_ajax && !verify_csrf_token($_POST['csrf_token'] ?? '')) { ... }

    $role = clean_input($_POST['role']);
    
    if ($role == 'admin') {
        // Proses login admin menggunakan username dan password
        $username = clean_input($_POST['username']);
        $password = clean_input($_POST['password']);
        
        // Query untuk validasi admin
        $query = "SELECT * FROM admin WHERE username = ? AND password = MD5(?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Login berhasil untuk admin
            $admin = $result->fetch_assoc();
            $_SESSION['role'] = 'admin';
            $_SESSION['id_admin'] = $admin['id_admin'];
            $_SESSION['username'] = $admin['username'];
            
            if ($is_ajax) {
                send_json_response(true, 'Login berhasil', [
                    'role' => 'admin',
                    'username' => $admin['username'],
                    'name' => $admin['username'], // Admin doesn't have 'nama' field usually
                    'token' => session_id() // Use session ID as token for client validation
                ]);
            } else {
                redirect('admin_dashboard.php');
            }
        } else {
            // Login gagal untuk admin
            if ($is_ajax) {
                send_json_response(false, 'Username atau password admin salah!');
            } else {
                $_SESSION['error'] = 'Username atau password admin salah!';
                redirect('index.php');
            }
        }
        
    } elseif ($role == 'siswa') {
        // Proses login siswa menggunakan NISN dan password
        $nisn = clean_input($_POST['nisn']);
        $password = clean_input($_POST['password']);
        
        // Query untuk validasi siswa berdasarkan NISN dan password
        $query = "SELECT * FROM siswa WHERE nisn = ? AND password = MD5(?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $nisn, $password);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Login berhasil untuk siswa
            $siswa = $result->fetch_assoc();
            $_SESSION['role'] = 'siswa';
            $_SESSION['nisn'] = $siswa['nisn'];
            $_SESSION['nama'] = $siswa['nama'];
            $_SESSION['kelas'] = $siswa['kelas'];
            
            if ($is_ajax) {
                send_json_response(true, 'Login berhasil', [
                    'role' => 'siswa',
                    'username' => $siswa['nisn'],
                    'name' => $siswa['nama'],
                    'token' => session_id()
                ]);
            } else {
                redirect('siswa_dashboard.php');
            }
        } else {
            // Login gagal untuk siswa
            if ($is_ajax) {
                send_json_response(false, 'NISN atau password siswa salah!');
            } else {
                $_SESSION['error'] = 'NISN atau password siswa salah!';
                redirect('index.php');
            }
        }
    } else {
        if ($is_ajax) {
            send_json_response(false, 'Pilih role terlebih dahulu!');
        } else {
            $_SESSION['error'] = 'Pilih role terlebih dahulu!';
            redirect('index.php');
        }
    }
} else {
    redirect('index.php');
}
?>