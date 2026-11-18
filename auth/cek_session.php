<?php
// Mulai session kalau belum berjalan
if (session_status() === PHP_SESSION_NONE) session_start();

// Muat koneksi database dan konstanta (BASE_URL, JAM_MASUK, dst.)
require_once __DIR__ . '/../config/koneksi.php';

// Muat fungsi bantu (h, inisial, terlambat, dll.) supaya header.php dan
// semua halaman bisa memakainya tanpa perlu memuatnya sendiri.
// require_once memastikan file hanya dimuat sekali walaupun dipanggil berkali-kali
require_once __DIR__ . '/../includes/fungsi.php';

// Paksa harus login: kalau belum, arahkan ke halaman login
function wajib_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "auth/login.php");
        exit;
    }
}

// Paksa harus punya role tertentu ('admin' atau 'karyawan')
function wajib_role($role) {
    wajib_login();
    if ($_SESSION['role'] !== $role) {
        $tujuan = $_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'karyawan/dashboard.php';
        header("Location: " . BASE_URL . $tujuan);
        exit;
    }
}