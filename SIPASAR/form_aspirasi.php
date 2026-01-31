<?php
require_once 'config.php';

// Cek apakah user sudah login sebagai siswa
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'siswa') {
    redirect('index.php');
}

// Ambil data kategori untuk dropdown
$kategori_query = "SELECT * FROM kategori ORDER BY ket_kategori";
$kategori_result = $conn->query($kategori_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Aspirasi - SIPASAR</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-body">
    <div class="form-container wide">
        <div class="header">
            <h1>Form Aspirasi Siswa</h1>
            <p>Sampaikan pengaduan sarana sekolah Anda</p>
        </div>
        
        <div class="user-info-box">
            <strong>NISN:</strong> <?php echo $_SESSION['nisn']; ?> | 
            <strong>Nama:</strong> <?php echo $_SESSION['nama']; ?> | 
            <strong>Kelas:</strong> <?php echo $_SESSION['kelas']; ?>
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
        
        <form method="POST" action="proses_aspirasi.php" id="aspirasiForm">
            <div class="form-group">
                <label for="nama">Nama Lengkap <span class="required">*</span></label>
                <input type="text" name="nama" id="nama" value="<?php echo $_SESSION['nama']; ?>" readonly style="background: #f8f9fa;">
            </div>
            
            <div class="form-group">
                <label for="nisn">NISN <span class="required">*</span></label>
                <input type="text" name="nisn" id="nisn" value="<?php echo $_SESSION['nisn']; ?>" readonly style="background: #f8f9fa;">
            </div>
            
            <div class="form-group">
                <label for="kelas">Kelas <span class="required">*</span></label>
                <input type="text" name="kelas" id="kelas" value="<?php echo $_SESSION['kelas']; ?>" readonly style="background: #f8f9fa;">
            </div>
            
            <div class="form-group">
                <label for="kategori">Kategori Pengaduan <span class="required">*</span></label>
                <select name="kategori" id="kategori" required>
                    <option value="">Pilih Kategori</option>
                    <?php while($kategori = $kategori_result->fetch_assoc()): ?>
                        <option value="<?php echo $kategori['id_kategori']; ?>">
                            <?php echo $kategori['ket_kategori']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="lokasi">Lokasi Sarana <span class="required">*</span></label>
                <input type="text" name="lokasi" id="lokasi" placeholder="Contoh: Ruang Kelas XII RPL 1, Toilet Lantai 2, dll." required maxlength="50">
            </div>
            
            <div class="form-group">
                <label for="keterangan">Keterangan Aspirasi <span class="required">*</span></label>
                <textarea name="keterangan" id="keterangan" placeholder="Jelaskan detail pengaduan sarana sekolah yang ingin Anda sampaikan..." required></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary full-width">Kirim Aspirasi</button>
            <a href="siswa_dashboard.php" class="btn btn-secondary full-width">Kembali ke Dashboard</a>
        </form>
        
        <div class="auth-link">
            <a href="siswa_dashboard.php">← Kembali ke Dashboard</a>
        </div>
    </div>
    
    <script src="js/script.js"></script>
</body>
</html>