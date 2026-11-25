<?php
require_once '../../auth/cek_session.php';
wajib_role('admin');

// Hanya menerima permintaan POST (bukan dari mengetik URL langsung)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$id = (int)($_POST['id'] ?? 0);

// Hapus berurutan: data anak dulu (absensi, users), baru data induk (karyawan)
// Kalau urutannya dibalik, akan ditolak oleh foreign key
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("DELETE FROM absensi WHERE karyawan_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM pengajuan WHERE karyawan_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM users WHERE karyawan_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM karyawan WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $conn->commit();
    $_SESSION['pesan'] = 'Karyawan beserta akun dan riwayat absensinya berhasil dihapus.';
    $_SESSION['pesan_tipe'] = 'success';
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    $_SESSION['pesan'] = 'Gagal menghapus data: ' . $e->getMessage();
    $_SESSION['pesan_tipe'] = 'danger';
}

header("Location: index.php");
exit;