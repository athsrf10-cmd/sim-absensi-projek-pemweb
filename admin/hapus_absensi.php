<?php
require_once '../auth/cek_session.php';
wajib_role('admin');   // hanya admin yang boleh menghapus

// Hanya menerima permintaan POST (bukan dari mengetik URL langsung)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: rekap_absensi.php");
    exit;
}

$id = (int)($_POST['id'] ?? 0);

// Susun alamat kembali ke halaman rekap dengan filter yang sama seperti sebelumnya.
// Hanya kunci yang diizinkan (whitelist) yang dipakai, supaya aman dari input aneh
parse_str($_POST['balik'] ?? '', $balik);
$boleh  = ['dari', 'sampai', 'karyawan', 'status', 'per', 'page'];
$balik  = array_intersect_key($balik, array_flip($boleh));
$tujuan = 'rekap_absensi.php' . ($balik ? '?' . http_build_query($balik) : '');

// Ambil nama dan tanggal dulu, untuk ditampilkan di pesan sukses
$info = query_aman($conn, "
    SELECT a.tanggal, k.nama
    FROM absensi a JOIN karyawan k ON a.karyawan_id = k.id
    WHERE a.id = ?", "i", [$id])->fetch_assoc();

if (!$info) {
    $_SESSION['pesan'] = 'Data absensi tidak ditemukan atau sudah dihapus.';
    $_SESSION['pesan_tipe'] = 'warning';
} else {
    try {
        // Hapus satu baris absensi berdasarkan id-nya
        $stmt = $conn->prepare("DELETE FROM absensi WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $_SESSION['pesan'] = 'Riwayat absensi ' . $info['nama'] . ' tanggal '
                           . date('d-m-Y', strtotime($info['tanggal'])) . ' berhasil dihapus.';
        $_SESSION['pesan_tipe'] = 'success';
    } catch (mysqli_sql_exception $e) {
        $_SESSION['pesan'] = 'Gagal menghapus data: ' . $e->getMessage();
        $_SESSION['pesan_tipe'] = 'danger';
    }
}

header("Location: " . $tujuan);
exit;