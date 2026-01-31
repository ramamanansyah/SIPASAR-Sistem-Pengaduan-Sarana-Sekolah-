<?php
require_once 'config.php';

// Cek apakah user sudah login sebagai siswa
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'siswa') {
    redirect('index.php');
}

// Fungsi untuk mengambil aspirasi siswa yang sedang login
function getAspirasiSiswa($conn, $nisn, $filter = []) {
    $where_conditions = ["a.nisn = ?"];
    $params = [$nisn];
    $types = "s";
    
    if (!empty($filter['kategori'])) {
        $where_conditions[] = "a.id_kategori = ?";
        $params[] = $filter['kategori'];
        $types .= "i";
    }
    
    if (!empty($filter['status'])) {
        $where_conditions[] = "a.status = ?";
        $params[] = $filter['status'];
        $types .= "s";
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    $query = "SELECT a.*, k.ket_kategori 
              FROM aspirasi a 
              JOIN kategori k ON a.id_kategori = k.id_kategori 
              $where_clause 
              ORDER BY a.tanggal DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}

// Proses filter
$filter = [];
if ($_GET) {
    if (!empty($_GET['kategori'])) $filter['kategori'] = clean_input($_GET['kategori']);
    if (!empty($_GET['status'])) $filter['status'] = clean_input($_GET['status']);
}

// Ambil data aspirasi siswa
$aspirasi_result = getAspirasiSiswa($conn, $_SESSION['nisn'], $filter);

// Hitung statistik
$stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Menunggu' THEN 1 ELSE 0 END) as menunggu,
                    SUM(CASE WHEN status = 'Proses' THEN 1 ELSE 0 END) as proses,
                    SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) as selesai
                FROM aspirasi WHERE nisn = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("s", $_SESSION['nisn']);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$kategori_query = "SELECT * FROM kategori ORDER BY ket_kategori";
$kategori_result = $conn->query($kategori_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - SIPASAR</title>
    <style>
        :root {
            --blue: #2563EB;
            --green: #10B981;
            --yellow: #F59E0B;
            --red: #EF4444;
            --bg: #F8FAFC;
            --text: #0F172A;
            --muted: #64748B;
            --card: #FFFFFF;
            --border: #E2E8F0;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .dashboard-header {
            background: var(--card);
            border-bottom: 1px solid var(--border);
            padding: 20px 24px;
        }
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header-content h1 {
            margin: 0 0 8px 0;
            font-size: 24px;
        }
        .user-info {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            color: var(--muted);
            font-size: 14px;
        }
        .container {
            max-width: 1200px;
            margin: 24px auto;
            padding: 0 24px 40px;
        }
        .btn, .add-btn, .logout-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid transparent;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-primary, .add-btn {
            background: var(--blue);
            color: #FFFFFF;
        }
        .logout-btn {
            background: transparent;
            color: var(--red);
            border-color: rgba(239, 68, 68, 0.3);
        }
        .refresh-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            color: var(--muted);
            font-size: 14px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.12);
            color: var(--green);
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            color: var(--red);
        }
        .stats-section {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
        }
        .stat-number {
            font-size: 22px;
            font-weight: 700;
        }
        .stat-label {
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
        }
        .content-section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 20px;
        }
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
        }
        .section-header h3 {
            margin: 0;
            font-size: 18px;
        }
        .filter-section {
            margin-bottom: 16px;
            padding: 16px;
            background: #F1F5F9;
            border-radius: 12px;
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            align-items: end;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            font-size: 14px;
            background: #FFFFFF;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
        }
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        thead th {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid var(--border);
            color: var(--muted);
            font-weight: 600;
            background: #F8FAFC;
        }
        tbody td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }
        tbody tr:hover {
            background: #F8FAFC;
        }
        .keterangan-cell, .feedback-cell {
            white-space: normal;
            word-break: break-word;
        }
        .status {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-menunggu {
            background: rgba(245, 158, 11, 0.15);
            color: var(--yellow);
        }
        .status-selesai {
            background: rgba(16, 185, 129, 0.15);
            color: var(--green);
        }
        .status-proses {
            background: rgba(37, 99, 235, 0.15);
            color: var(--blue);
        }
        .no-data {
            text-align: center;
            padding: 32px 16px;
            color: var(--muted);
        }
        .btn:focus-visible, .add-btn:focus-visible, .logout-btn:focus-visible, select:focus-visible {
            outline: 2px solid var(--blue);
            outline-offset: 2px;
        }
        @media (max-width: 1024px) {
            .stats-section {
                grid-template-columns: repeat(2, 1fr);
            }
            .filter-form {
                grid-template-columns: 1fr 1fr;
            }
        }
        @media (max-width: 768px) {
            .user-info {
                flex-direction: column;
                align-items: flex-start;
            }
            .stats-section {
                grid-template-columns: 1fr;
            }
            .filter-form {
                grid-template-columns: 1fr;
            }
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body class="dashboard-body" data-role="siswa">
    <div class="dashboard-header">
        <div class="header-content">
            <h1>Dashboard Siswa SIPASAR</h1>
            <div class="user-info">
                <span>NISN: <?php echo $_SESSION['nisn']; ?> | Nama: <?php echo $_SESSION['nama']; ?> | Kelas: <?php echo $_SESSION['kelas']; ?></span>
                <a href="form_aspirasi.php" class="add-btn">+ Buat Aspirasi</a>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Auto-refresh Status -->
        <div class="refresh-controls">
            <span id="last-refresh" class="last-update">Terakhir diperbarui: <?php echo date('d M Y, H:i'); ?></span>
        </div>
        
        <!-- Notification Container -->
        <div id="notification-container"></div>
        
        <?php
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-error">' . $_SESSION['error'] . '</div>';
            unset($_SESSION['error']);
        }
        ?>
        
        <!-- Statistics Section -->
        <div class="stats-section">
            <div class="stat-card stat-total">
                <div class="stat-number"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Aspirasi</div>
            </div>
            <div class="stat-card stat-menunggu">
                <div class="stat-number"><?php echo $stats['menunggu']; ?></div>
                <div class="stat-label">Menunggu</div>
            </div>
            <div class="stat-card stat-proses">
                <div class="stat-number"><?php echo $stats['proses']; ?></div>
                <div class="stat-label">Dalam Proses</div>
            </div>
            <div class="stat-card stat-selesai">
                <div class="stat-number"><?php echo $stats['selesai']; ?></div>
                <div class="stat-label">Selesai</div>
            </div>
        </div>
        
        <!-- Aspirasi Section -->
        <div class="content-section">
            <div class="section-header">
                <h3>Histori Aspirasi Anda</h3>
                <a href="form_aspirasi.php" class="btn btn-primary">+ Buat Aspirasi Baru</a>
            </div>
            
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="kategori">Kategori</label>
                        <select name="kategori" id="kategori">
                            <option value="">Semua Kategori</option>
                            <?php while($kategori = $kategori_result->fetch_assoc()): ?>
                                <option value="<?php echo $kategori['id_kategori']; ?>" <?php echo (isset($_GET['kategori']) && $_GET['kategori'] == $kategori['id_kategori']) ? 'selected' : ''; ?>>
                                    <?php echo $kategori['ket_kategori']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">Semua Status</option>
                            <option value="Menunggu" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Menunggu') ? 'selected' : ''; ?>>Menunggu</option>
                            <option value="Proses" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Proses') ? 'selected' : ''; ?>>Proses</option>
                            <option value="Selesai" <?php echo (isset($_GET['status']) && $_GET['status'] == 'Selesai') ? 'selected' : ''; ?>>Selesai</option>
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="siswa_dashboard.php" class="btn">Reset</a>
                    </div>
                </form>
            </div>

            <div class="table-container">
                <?php if ($aspirasi_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kategori</th>
                                <th>Lokasi</th>
                                <th>Keterangan</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Feedback Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while($row = $aspirasi_result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo $row['ket_kategori']; ?></td>
                                    <td><?php echo $row['lokasi']; ?></td>
                                    <td class="keterangan-cell"><?php echo $row['keterangan']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                    <td>
                                        <span class="status status-<?php echo strtolower($row['status']); ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td class="feedback-cell">
                                        <?php echo $row['feedback'] ? $row['feedback'] : '-'; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <p>Anda belum memiliki aspirasi.</p>
                        <p><a href="form_aspirasi.php" class="btn btn-primary mt-20">Buat Aspirasi Pertama</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
