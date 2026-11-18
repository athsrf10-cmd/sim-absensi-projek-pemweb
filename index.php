<?php
session_start();
require_once 'config/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
} elseif ($_SESSION['role'] === 'admin') {
    header("Location: " . BASE_URL . "admin/dashboard.php");
} else {
    header("Location: " . BASE_URL . "karyawan/dashboard.php");
}
exit;