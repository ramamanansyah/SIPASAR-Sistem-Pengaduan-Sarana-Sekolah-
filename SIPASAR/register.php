<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - SIPASAR</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="register-body">
    <div class="form-container">
        <div class="header">
            <h1>Daftar Akun</h1>
            <p>Sistem Pengaduan Sarana Sekolah</p>
        </div>
        
        <?php
        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
            unset($_SESSION['error']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
            unset($_SESSION['success']);
        }
        ?>
        
        <form method="POST" action="proses_register.php" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="role-selection">
                <label>Pilih Role:</label>
                <div class="role-buttons">
                    <div class="role-btn" onclick="selectRole('admin')">Admin</div>
                    <div class="role-btn" onclick="selectRole('siswa')">Siswa</div>
                </div>
                <input type="hidden" name="role" id="selectedRole" required>
            </div>
            
            <div class="form-group hidden" id="usernameGroup">
                <label for="username">Username:</label>
                <input type="text" name="username" id="username">
            </div>
            
            <div class="form-group hidden" id="passwordGroup">
                <label for="password">Password:</label>
                <input type="password" name="password" id="password">
            </div>
            
            <div class="form-group hidden" id="namaGroup">
                <label for="nama">Nama Lengkap:</label>
                <input type="text" name="nama" id="nama" maxlength="50">
            </div>
            
            <div class="form-group hidden" id="nisnGroup">
                <label for="nisn">NISN:</label>
                <input type="text" name="nisn" id="nisn" maxlength="10">
            </div>
            
            <div class="form-group hidden" id="kelasGroup">
                <label for="kelas">Kelas:</label>
                <select name="kelas" id="kelas">
                    <option value="">Pilih Kelas</option>
                    <option value="XII RPL 1">XII RPL 1</option>
                    <option value="XII RPL 2">XII RPL 2</option>
                    <option value="XII TKJ 1">XII TKJ 1</option>
                    <option value="XII TKJ 2">XII TKJ 2</option>
                    <option value="XII BC 1">XII BC 1</option>
                    <option value="XII BC 2">XII BC 2</option>
                </select>
            </div>
            
            <div class="form-group hidden" id="passwordSiswaGroup">
                <label for="passwordSiswa">Password:</label>
                <input type="password" name="password" id="passwordSiswa" minlength="6">
            </div>
            
            <button type="submit" class="btn btn-primary full-width">Daftar</button>
        </form>
        
        <div class="auth-link">
            Sudah punya akun? <a href="index.php">Login di sini</a>
        </div>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>