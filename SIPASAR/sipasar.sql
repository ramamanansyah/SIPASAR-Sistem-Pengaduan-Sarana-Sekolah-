-- Database SIPASAR (Sistem Pengaduan Sarana Sekolah)
-- Sesuai dengan ERD pada dokumen UKK

CREATE DATABASE IF NOT EXISTS sipasar;
USE sipasar;

-- Tabel Admin
CREATE TABLE admin (
    id_admin INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- Tabel Siswa
CREATE TABLE siswa (
    nisn VARCHAR(10) PRIMARY KEY,
    nama VARCHAR(50) NOT NULL,
    kelas VARCHAR(10) NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- Tabel Kategori
CREATE TABLE kategori (
    id_kategori INT AUTO_INCREMENT PRIMARY KEY,
    ket_kategori VARCHAR(30) NOT NULL
);

-- Tabel Aspirasi
CREATE TABLE aspirasi (
    id_aspirasi INT AUTO_INCREMENT PRIMARY KEY,
    nisn VARCHAR(10) NOT NULL,
    id_kategori INT NOT NULL,
    lokasi VARCHAR(50) NOT NULL,
    keterangan TEXT NOT NULL,
    tanggal DATE NOT NULL DEFAULT CURRENT_DATE,
    status ENUM('Menunggu', 'Proses', 'Selesai') NOT NULL DEFAULT 'Menunggu',
    feedback TEXT,
    FOREIGN KEY (nisn) REFERENCES siswa(nisn),
    FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori)
);

-- Insert data default admin
INSERT INTO admin (username, password) VALUES 
('admin', MD5('admin123'));

-- Insert data kategori default
INSERT INTO kategori (ket_kategori) VALUES 
('Fasilitas Kelas'),
('Toilet/WC'),
('Laboratorium'),
('Perpustakaan'),
('Kantin'),
('Lapangan Olahraga'),
('Lainnya');

-- Insert data siswa contoh
INSERT INTO siswa (nisn, nama, kelas, password) VALUES 
('1234567890', 'Ahmad Rizki', 'XII RPL 1', MD5('123456')),
('0987654321', 'Siti Nurhaliza', 'XII RPL 2', MD5('123456')),
('1122334455', 'Budi Santoso', 'XII TKJ 1', MD5('123456')),
('2233445566', 'Dewi Sartika', 'XII BC 1', MD5('123456')),
('3344556677', 'Andi Pratama', 'XII BC 2', MD5('123456'));