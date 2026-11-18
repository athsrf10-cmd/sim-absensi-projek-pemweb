<?php
require_once '../../auth/cek_session.php';
wajib_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id = (int)($_POST['id'] ?? 0);

$cek = $conn->prepare("SELECT COUNT(*) AS n FROM karyawan WHERE jabatan_id = ?");
$cek->bind_param("i", $id);
$cek->execute();
$dipakai = $cek->get_result()->fetch_assoc()['n'];

if ($dipakai > 0) {
    $_SESSION['pesan'] = "Jabatan tidak bisa dihapus karena masih dipakai $dipakai karyawan.";
    $_SESSION['pesan_tipe'] = 'danger';
} else {
    $stmt = $conn->prepare("DELETE FROM jabatan WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['pesan'] = 'Jabatan berhasil dihapus.';
    $_SESSION['pesan_tipe'] = 'success';
}

header("Location: index.php");
exit;