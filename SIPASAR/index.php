<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPASAR - Sistem Pengaduan Sarana Sekolah</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-body">
    <div class="form-container">
        <div class="header">
            <h1>SIPASAR</h1>
            <p>Sistem Pengaduan Sarana Sekolah</p>
        </div>
        
        <?php
        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
            unset($_SESSION['error']);
        }
        ?>
        
        <form method="POST" action="proses_login.php" id="loginForm" onsubmit="handleLogin(event)">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="role-selection">
                <label>Pilih Role:</label>
                <div class="role-buttons">
                    <div class="role-btn" onclick="selectRole('admin')">Admin</div>
                    <div class="role-btn" onclick="selectRole('siswa')">Siswa</div>
                </div>
                <input type="hidden" name="role" id="selectedRole" required>
            </div>
            
            <div class="form-group" id="usernameGroup">
                <label for="username">Username:</label>
                <input type="text" name="username" id="username" required>
            </div>
            
            <div class="form-group hidden" id="nisnGroup">
                <label for="nisn">NISN:</label>
                <input type="text" name="nisn" id="nisn" maxlength="10">
            </div>
            
            <div class="form-group" id="passwordGroup">
                <label for="password">Password:</label>
                <input type="password" name="password" id="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary full-width">Login</button>
        </form>
        
        <div class="auth-link">
            Belum punya akun? <a href="register.php">Daftar di sini</a>
        </div>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>