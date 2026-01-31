<?php
require_once 'config.php';

// Cek apakah user sudah login sebagai admin
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    redirect('index.php');
}

// Fungsi untuk mengambil data aspirasi dengan filter
function getAspirasi($conn, $filter = []) {
    $where_conditions = [];
    $params = [];
    $types = "";
    
    // Filter berdasarkan tanggal
    if (!empty($filter['tanggal'])) {
        $where_conditions[] = "DATE(a.tanggal) = ?";
        $params[] = $filter['tanggal'];
        $types .= "s";
    }
    
    // Filter berdasarkan bulan
    if (!empty($filter['bulan'])) {
        $where_conditions[] = "MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = YEAR(CURDATE())";
        $params[] = $filter['bulan'];
        $types .= "i";
    }
    
    // Filter berdasarkan siswa (NISN)
    if (!empty($filter['nisn'])) {
        $where_conditions[] = "a.nisn = ?";
        $params[] = $filter['nisn'];
        $types .= "s";
    }
    
    // Filter berdasarkan kategori
    if (!empty($filter['kategori'])) {
        $where_conditions[] = "a.id_kategori = ?";
        $params[] = $filter['kategori'];
        $types .= "i";
    }
    
    $where_clause = "";
    if (!empty($where_conditions)) {
        $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    }
    
    $query = "SELECT a.*, s.nama, s.kelas, k.ket_kategori 
              FROM aspirasi a 
              JOIN siswa s ON a.nisn = s.nisn 
              JOIN kategori k ON a.id_kategori = k.id_kategori 
              $where_clause 
              ORDER BY a.tanggal DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

function formatTanggalExport($dateString) {
    $timestamp = strtotime($dateString);
    return $timestamp ? date('d M Y', $timestamp) : $dateString;
}

function escapePdfText($text) {
    $text = str_replace("\\", "\\\\", $text);
    $text = str_replace("(", "\\(", $text);
    $text = str_replace(")", "\\)", $text);
    $text = preg_replace("/[\r\n]+/", " ", $text);
    return $text;
}

function buildPdfContent($lines) {
    $content = "BT\n/F1 10 Tf\n14 TL\n40 800 Td\n";
    foreach ($lines as $line) {
        $content .= "(" . escapePdfText($line) . ") Tj\nT*\n";
    }
    $content .= "ET";
    return $content;
}

// Proses filter
$filter = [];
if ($_GET) {
    if (!empty($_GET['tanggal'])) $filter['tanggal'] = clean_input($_GET['tanggal']);
    if (!empty($_GET['bulan'])) $filter['bulan'] = clean_input($_GET['bulan']);
    if (!empty($_GET['nisn'])) $filter['nisn'] = clean_input($_GET['nisn']);
    if (!empty($_GET['kategori'])) $filter['kategori'] = clean_input($_GET['kategori']);
}

if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $format = isset($_GET['format']) ? strtolower(clean_input($_GET['format'])) : '';
    $allowed = ['csv', 'xls', 'pdf'];
    if (!in_array($format, $allowed)) {
        $_SESSION['error'] = 'Format ekspor tidak valid.';
        redirect('admin_dashboard.php');
    }
    
    $export_result = getAspirasi($conn, $filter);
    $rows = [];
    while ($row = $export_result->fetch_assoc()) {
        $rows[] = $row;
    }
    
    $filename = 'aspirasi_' . date('Ymd_His');
    $headers = ['No', 'NISN', 'Nama', 'Kelas', 'Kategori', 'Lokasi', 'Keterangan', 'Tanggal', 'Status', 'Feedback'];
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        $no = 1;
        foreach ($rows as $row) {
            fputcsv($output, [
                $no++,
                $row['nisn'],
                $row['nama'],
                $row['kelas'],
                $row['ket_kategori'],
                $row['lokasi'],
                $row['keterangan'],
                formatTanggalExport($row['tanggal']),
                $row['status'],
                $row['feedback']
            ]);
        }
        fclose($output);
        exit();
    }
    
    if ($format === 'xls') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        echo '<html><head><meta charset="UTF-8"></head><body>';
        echo '<table border="1">';
        echo '<tr>';
        foreach ($headers as $header) {
            echo '<th>' . $header . '</th>';
        }
        echo '</tr>';
        $no = 1;
        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . $no++ . '</td>';
            echo '<td>' . $row['nisn'] . '</td>';
            echo '<td>' . $row['nama'] . '</td>';
            echo '<td>' . $row['kelas'] . '</td>';
            echo '<td>' . $row['ket_kategori'] . '</td>';
            echo '<td>' . $row['lokasi'] . '</td>';
            echo '<td>' . $row['keterangan'] . '</td>';
            echo '<td>' . formatTanggalExport($row['tanggal']) . '</td>';
            echo '<td>' . $row['status'] . '</td>';
            echo '<td>' . $row['feedback'] . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
        exit();
    }
    
    if ($format === 'pdf') {
        $lines = [];
        $lines[] = 'Laporan Aspirasi SIPASAR';
        $lines[] = '';
        $lines[] = implode(' | ', $headers);
        $no = 1;
        foreach ($rows as $row) {
            $line = [
                $no++,
                $row['nisn'],
                $row['nama'],
                $row['kelas'],
                $row['ket_kategori'],
                $row['lokasi'],
                $row['keterangan'],
                formatTanggalExport($row['tanggal']),
                $row['status'],
                $row['feedback']
            ];
            $lineText = implode(' | ', array_map('strval', $line));
            $wrapped = wordwrap($lineText, 120, "\n", true);
            $lines = array_merge($lines, explode("\n", $wrapped));
        }
        
        $linesPerPage = 45;
        $chunks = array_chunk($lines, $linesPerPage);
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $nextId = 4;
        $pageRefs = [];
        
        foreach ($chunks as $chunk) {
            $content = buildPdfContent($chunk);
            $contentId = $nextId++;
            $objects[$contentId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $pageId = $nextId++;
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents " . $contentId . " 0 R >>";
            $pageRefs[] = $pageId . " 0 R";
        }
        
        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $pageRefs) . "] /Count " . count($pageRefs) . " >>";
        
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        $totalObjects = count($objects);
        for ($i = 1; $i <= $totalObjects; $i++) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
        }
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . ($totalObjects + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObjects; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . ($totalObjects + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
        echo $pdf;
        exit();
    }
}

// Ambil data aspirasi
$aspirasi_result = getAspirasi($conn, $filter);

// Statistik untuk kartu
$stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Menunggu' THEN 1 ELSE 0 END) as menunggu,
                    SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) as selesai
                FROM aspirasi";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Ambil data kategori untuk filter
$kategori_query = "SELECT * FROM kategori ORDER BY ket_kategori";
$kategori_result = $conn->query($kategori_query);

// Ambil data siswa untuk filter
$siswa_query = "SELECT DISTINCT nisn, nama, kelas FROM siswa ORDER BY kelas, nama";
$siswa_result = $conn->query($siswa_query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SIPASAR</title>
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
            max-width: 1300px;
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
            max-width: 1300px;
            margin: 24px auto;
            padding: 0 24px 40px;
        }
        .btn, .logout-btn {
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
        .btn-primary {
            background: var(--blue);
            color: #FFFFFF;
        }
        .btn-secondary {
            background: #E2E8F0;
            color: #0F172A;
        }
        .btn-export {
            background: #F1F5F9;
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn-small {
            padding: 8px 12px;
            font-size: 13px;
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
        .refresh-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .last-update {
            display: inline-flex;
            align-items: center;
            gap: 8px;
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
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 20px;
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
        .filter-section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .filter-section h3 {
            margin: 0 0 12px 0;
            font-size: 16px;
        }
        .filter-form {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            align-items: end;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .form-group input, .form-group select {
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
            margin-bottom: 16px;
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
        tbody tr:nth-child(even) {
            background: #F8FAFC;
        }
        tbody tr:hover {
            background: #EEF2FF;
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
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .icon-btn {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #FFFFFF;
            color: var(--text);
            cursor: pointer;
            transition: transform 0.15s ease, background 0.15s ease, border-color 0.15s ease;
        }
        .icon-btn:hover {
            background: #F1F5F9;
            border-color: #CBD5F5;
        }
        .icon-btn:active {
            transform: scale(0.96);
        }
        .icon-btn svg {
            width: 18px;
            height: 18px;
        }
        .icon-edit {
            color: var(--blue);
        }
        .icon-delete {
            color: var(--red);
        }
        .icon-finish {
            color: var(--green);
        }
        .icon-btn[data-tooltip]::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: calc(100% + 6px);
            left: 50%;
            transform: translateX(-50%);
            background: #0F172A;
            color: #FFFFFF;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 6px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }
        .icon-btn:hover::after, .icon-btn:focus-visible::after {
            opacity: 1;
        }
        .icon-btn:focus-visible, .btn:focus-visible, .logout-btn:focus-visible, input:focus-visible, select:focus-visible {
            outline: 2px solid var(--blue);
            outline-offset: 2px;
        }
        .no-data {
            text-align: center;
            padding: 32px 16px;
            color: var(--muted);
        }
        .mt-20 {
            margin-top: 20px;
        }
        .text-center {
            text-align: center;
        }
        .notification {
            background: #FFFFFF;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08);
        }
        .notification-success {
            border-color: rgba(16, 185, 129, 0.3);
        }
        .notification-warning {
            border-color: rgba(245, 158, 11, 0.3);
        }
        .notification-close {
            background: transparent;
            border: none;
            font-size: 18px;
            cursor: pointer;
        }
        .temp-notification {
            position: fixed;
            right: 20px;
            bottom: 20px;
            background: #0F172A;
            color: #FFFFFF;
            padding: 10px 14px;
            border-radius: 10px;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 1200;
        }
        .temp-notification.show {
            opacity: 1;
            transform: translateY(0);
        }
        .temp-notification-success {
            background: var(--green);
        }
        .temp-notification-error {
            background: var(--red);
        }
        .scroll-indicator {
            padding: 8px 12px;
            font-size: 12px;
            color: var(--muted);
            text-align: center;
            background: #F8FAFC;
        }
        .updated {
            color: var(--blue);
        }
        .export-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        .export-controls select {
            min-width: 140px;
        }
        .export-status {
            margin-top: 8px;
            font-size: 13px;
            color: var(--muted);
        }
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            z-index: 1100;
            padding: 20px;
        }
        .modal-content {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 20px;
            max-width: 520px;
            margin: 60px auto;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.2);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .close {
            cursor: pointer;
            font-size: 24px;
        }
        #edit_feedback {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            font-size: 14px;
            min-height: 100px;
            resize: vertical;
        }
        @media (max-width: 1200px) {
            .filter-form {
                grid-template-columns: repeat(2, 1fr);
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
            .action-buttons {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body class="dashboard-body" data-role="admin">
    <div class="dashboard-header">
        <div class="header-content">
            <h1>Dashboard Admin SIPASAR</h1>
            <div class="user-info">
                <span>Selamat datang, <?php echo $_SESSION['username']; ?></span>
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
                <div class="stat-label">Status Menunggu</div>
            </div>
            <div class="stat-card stat-selesai">
                <div class="stat-number"><?php echo $stats['selesai']; ?></div>
                <div class="stat-label">Status Selesai</div>
            </div>
        </div>
        <!-- Filter Section -->
        <div class="filter-section" id="filter-section">
            <h3>Filter Aspirasi</h3>
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="tanggal">Tanggal:</label>
                    <input type="date" name="tanggal" id="tanggal" value="<?php echo $_GET['tanggal'] ?? ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="bulan">Bulan:</label>
                    <select name="bulan" id="bulan">
                        <option value="">Semua Bulan</option>
                        <?php for($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo (isset($_GET['bulan']) && $_GET['bulan'] == $i) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="nisn">Siswa (NISN):</label>
                    <input type="text" name="nisn" id="nisn" list="nisn-list" placeholder="Cari NISN" value="<?php echo $_GET['nisn'] ?? ''; ?>">
                    <datalist id="nisn-list">
                        <?php while($siswa = $siswa_result->fetch_assoc()): ?>
                            <option value="<?php echo $siswa['nisn']; ?>"><?php echo $siswa['nisn'] . ' - ' . $siswa['nama'] . ' (' . $siswa['kelas'] . ')'; ?></option>
                        <?php endwhile; ?>
                    </datalist>
                </div>
                
                <div class="form-group">
                    <label for="kategori">Kategori:</label>
                    <select name="kategori" id="kategori">
                        <option value="">Semua Kategori</option>
                        <?php while($kategori = $kategori_result->fetch_assoc()): ?>
                            <option value="<?php echo $kategori['id_kategori']; ?>" <?php echo (isset($_GET['kategori']) && $_GET['kategori'] == $kategori['id_kategori']) ? 'selected' : ''; ?>>
                                <?php echo $kategori['ket_kategori']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary" name="action" value="filter">Filter</button>
                    <a href="admin_dashboard.php" class="btn btn-secondary">Reset</a>
                </div>
                <div class="export-controls">
                    <div class="form-group">
                        <label for="format">Format Ekspor:</label>
                        <select name="format" id="format">
                            <option value="csv" <?php echo (isset($_GET['format']) && $_GET['format'] == 'csv') ? 'selected' : ''; ?>>CSV</option>
                            <option value="xls" <?php echo (isset($_GET['format']) && $_GET['format'] == 'xls') ? 'selected' : ''; ?>>Excel</option>
                            <option value="pdf" <?php echo (isset($_GET['format']) && $_GET['format'] == 'pdf') ? 'selected' : ''; ?>>PDF</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-export" name="action" value="export" id="exportBtn" formtarget="_blank">Ekspor</button>
                    <div id="export-status" class="export-status" role="status" aria-live="polite"></div>
                </div>
            </form>
        </div>
        
        <!-- Aspirasi Section -->
        <div class="content-section">
            <div class="section-header">
                <h3>Daftar Aspirasi Siswa</h3>
            </div>
            
            <div class="table-container">
                <?php if ($aspirasi_result->num_rows > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>NISN</th>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th>Kategori</th>
                                <th>Lokasi</th>
                                <th>Keterangan</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Feedback</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while($row = $aspirasi_result->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $no++; ?></td>
                                    <td><?php echo $row['nisn']; ?></td>
                                    <td><?php echo $row['nama']; ?></td>
                                    <td><?php echo $row['kelas']; ?></td>
                                    <td><?php echo $row['ket_kategori']; ?></td>
                                    <td><?php echo $row['lokasi']; ?></td>
                                    <td><?php echo substr($row['keterangan'], 0, 50) . (strlen($row['keterangan']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                    <td>
                                        <span class="status status-<?php echo strtolower($row['status']); ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['feedback'] ? substr($row['feedback'], 0, 30) . '...' : '-'; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="icon-btn icon-edit btn-edit" data-tooltip="Edit" aria-label="Edit" onclick="editAspirasi(<?php echo $row['id_aspirasi']; ?>, '<?php echo $row['status']; ?>', '<?php echo addslashes($row['feedback']); ?>')">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path fill="currentColor" d="M3 17.25V21h3.75l11-11-3.75-3.75-11 11zm14.71-9.04a1.003 1.003 0 000-1.42l-2.5-2.5a1.003 1.003 0 00-1.42 0l-1.83 1.83 3.75 3.75 2-1.66z"/>
                                                </svg>
                                            </button>
                                            <button class="icon-btn icon-finish btn-save" type="button" data-tooltip="Selesai" aria-label="Selesai">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path fill="currentColor" d="M9 16.2l-3.5-3.5L4 14.2l5 5 11-11-1.5-1.5z"/>
                                                </svg>
                                            </button>
                                            <button class="icon-btn icon-delete btn-delete" type="button" data-tooltip="Hapus" aria-label="Hapus">
                                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                    <path fill="currentColor" d="M6 7h12l-1 14H7L6 7zm3-3h6l1 2H8l1-2z"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <p>Tidak ada data aspirasi yang ditemukan.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit Aspirasi -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Status & Feedback Aspirasi</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <form method="POST" action="update_aspirasi.php">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id_aspirasi" id="edit_id_aspirasi">
                
                <div class="form-group">
                    <label for="edit_status">Status:</label>
                    <select name="status" id="edit_status" required>
                        <option value="Menunggu">Menunggu</option>
                        <option value="Proses">Proses</option>
                        <option value="Selesai">Selesai</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_feedback">Feedback:</label>
                    <textarea name="feedback" id="edit_feedback" rows="4"></textarea>
                </div>
                
                <div class="mt-20 text-center">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    
    <form method="POST" action="update_aspirasi.php" id="quickActionForm">
        <input type="hidden" name="action" id="quick_action">
        <input type="hidden" name="id_aspirasi" id="quick_id_aspirasi">
        <input type="hidden" name="status" id="quick_status">
        <input type="hidden" name="feedback" id="quick_feedback">
    </form>
    
    <script src="js/script.js"></script>
</body>
</html>
