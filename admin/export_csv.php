<?php
require_once '../auth/cek_session.php';
require_once '../includes/fungsi.php';
wajib_role('admin');

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $tanggal = date('Y-m-d');

$stmt = $conn->prepare("
    SELECT a.*, k.nip, k.nama, j.nama_jabatan
    FROM absensi a
    JOIN karyawan k ON a.karyawan_id = k.id
    LEFT JOIN jabatan j ON k.jabatan_id = j.id
    WHERE a.tanggal = ?
    ORDER BY k.nama
");
$stmt->bind_param("s", $tanggal);
$stmt->execute();
$hasil = $stmt->get_result();

// Header HTTP supaya browser mengunduh file (bukan menampilkannya)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="absensi_' . $tanggal . '.csv"');

echo "\xEF\xBB\xBF"; // BOM supaya Excel membaca karakter dengan benar
$out = fopen('php://output', 'w');
fputcsv($out, ['Tanggal', 'NIP', 'Nama', 'Jabatan', 'Jam Masuk', 'Jam Pulang', 'Status', 'Keterangan']);

while ($r = $hasil->fetch_assoc()) {
    // Keterangan hanya untuk yang punya jam masuk
    $ket = $r['jam_masuk'] ? (terlambat($r['jam_masuk']) ? 'Terlambat' : 'Tepat Waktu') : '-';
    if (!empty($r['keterangan'])) $ket = $r['keterangan'];
    fputcsv($out, [$r['tanggal'], $r['nip'], $r['nama'], $r['nama_jabatan'], $r['jam_masuk'], $r['jam_pulang'], $r['status'], $ket]);
}
fclose($out);