<?php
session_start();
require_once '../config/koneksi.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user && password_verify($password, $user['password'])) {
    session_regenerate_id(true);
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['username']    = $user['username'];
    $_SESSION['role']        = $user['role'];
    $_SESSION['karyawan_id'] = $user['karyawan_id'];

    $tujuan = $user['role'] === 'admin' ? 'admin/dashboard.php' : 'karyawan/dashboard.php';
    header("Location: " . BASE_URL . $tujuan);
} else {
    header("Location: " . BASE_URL . "auth/login.php?error=1");
}
exit;