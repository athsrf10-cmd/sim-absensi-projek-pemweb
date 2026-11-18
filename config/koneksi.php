<?php
// Alamat dasar proyek (dipakai untuk semua link dan aset)
define('BASE_URL', '/sistem-absensi/');

// ===== Aturan jam kerja (ubah di sini kalau jam kerja berbeda) =====
define('JAM_MASUK', '08:00:00');   // jam masuk resmi
define('TOLERANSI_MENIT', 15);     // toleransi keterlambatan (menit)

// Zona waktu PHP ke WIB
date_default_timezone_set('Asia/Jakarta');

// Koneksi ke database (sesuaikan nama database Anda)
$conn = new mysqli("localhost", "root", "", "sim_absensi");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Zona waktu MySQL ke WIB, supaya CURDATE()/CURTIME() sesuai
$conn->query("SET time_zone = '+07:00'");