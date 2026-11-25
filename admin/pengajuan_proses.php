<?php
require_once '../auth/cek_session.php';
wajib_role('admin');   // hanya admin yang boleh memproses pengajuan

// Hanya menerima permintaan POST (bukan dari mengetik URL langsung)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: pengajuan.php");
    exit;
}

$aksi = $_POST['aksi'] ?? '';
$id   = (int)($_POST['id'] ?? 0);

// Alamat kembali ke halaman pengajuan dengan filter yang sama (hanya kunci yang diizinkan)
parse_str($_POST['balik'] ?? '', $balik);
$boleh  = ['status', 'jenis', 'karyawan', 'page'];
$balik  = array_intersect_key($balik, array_flip($boleh));
$tujuan = 'pengajuan.php' . ($balik ? '?' . http_build_query($balik) : '');

function pesan($teks, $tipe) {
    $_SESSION['pesan'] = $teks;
    $_SESSION['pesan_tipe'] = $tipe;
}

// Ambil pengajuan beserta nama karyawannya
$p = query_aman($conn, "
    SELECT p.*, k.nama FROM pengajuan p
    JOIN karyawan k ON p.karyawan_id = k.id
    WHERE p.id = ?", "i", [$id])->fetch_assoc();

if (!$p) {
    pesan('Pengajuan tidak ditemukan atau sudah dihapus.', 'warning');
    header("Location: " . $tujuan);
    exit;
}

try {
    if ($aksi === 'setujui') {
        // ---------- SETUJUI: buat baris absensi untuk tiap hari kerja pada rentang tanggal ----------
        if ($p['status'] !== 'Menunggu') {
            pesan('Pengajuan ini sudah diproses sebelumnya.', 'warning');
        } else {
            $conn->begin_transaction();

            // Tanggal yang sudah berstatus Hadir (karyawan sudah absen) tidak ditimpa
            $sudah_hadir = [];
            $res = query_aman($conn, "
                SELECT tanggal FROM absensi
                WHERE karyawan_id = ? AND tanggal BETWEEN ? AND ? AND status = 'Hadir'",
                "iss", [$p['karyawan_id'], $p['tanggal_mulai'], $p['tanggal_selesai']]);
            foreach ($res as $r) { $sudah_hadir[$r['tanggal']] = true; }

            // Keterangan hanya untuk Izin dan Cuti. Sakit tidak memakai keterangan
            $ket = $p['jenis'] === 'Sakit' ? null : $p['alasan'];

            // Kalau karyawan + tanggal sudah ada (mis. Alpha), barisnya diganti (UNIQUE karyawan_id + tanggal)
            $stmt = $conn->prepare("
                INSERT INTO absensi (karyawan_id, tanggal, jam_masuk, jam_pulang, status, keterangan, pengajuan_id)
                VALUES (?, ?, NULL, NULL, ?, ?, ?)
                ON DUPLICATE KEY UPDATE jam_masuk = NULL, jam_pulang = NULL, status = VALUES(status),
                                        keterangan = VALUES(keterangan), pengajuan_id = VALUES(pengajuan_id)");

            $dicatat = 0; $dilewati = 0;
            for ($t = strtotime($p['tanggal_mulai']); $t <= strtotime($p['tanggal_selesai']); $t += 86400) {
                if ((int)date('N', $t) > 5) continue;          // lewati Sabtu dan Minggu
                $tgl = date('Y-m-d', $t);
                if (isset($sudah_hadir[$tgl])) { $dilewati++; continue; }
                $stmt->bind_param("isssi", $p['karyawan_id'], $tgl, $p['jenis'], $ket, $p['id']);
                $stmt->execute();
                $dicatat++;
            }

            $stmt = $conn->prepare("UPDATE pengajuan SET status = 'Disetujui', diproses_pada = NOW() WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $conn->commit();

            $teks = 'Pengajuan ' . strtolower($p['jenis']) . ' ' . $p['nama'] . ' disetujui. ' . $dicatat . ' hari tercatat di rekap absensi.';
            if ($dilewati > 0) $teks .= ' ' . $dilewati . ' hari dilewati karena karyawan sudah absen Hadir.';
            pesan($teks, 'success');
        }

    } elseif ($aksi === 'tolak') {
        // ---------- TOLAK: alasan penolakan wajib, tampil di riwayat karyawan ----------
        $catatan = trim($_POST['catatan'] ?? '');
        if ($p['status'] !== 'Menunggu') {
            pesan('Pengajuan ini sudah diproses sebelumnya.', 'warning');
        } elseif ($catatan === '' || mb_strlen($catatan) > 255) {
            pesan('Alasan penolakan wajib diisi (maksimal 255 karakter).', 'danger');
        } else {
            $stmt = $conn->prepare("UPDATE pengajuan SET status = 'Ditolak', catatan_admin = ?, diproses_pada = NOW() WHERE id = ?");
            $stmt->bind_param("si", $catatan, $id);
            $stmt->execute();
            pesan('Pengajuan ' . strtolower($p['jenis']) . ' ' . $p['nama'] . ' ditolak.', 'success');
        }

    } elseif ($aksi === 'hapus') {
        // ---------- HAPUS: pengajuan dan baris absensi bentukannya ikut terhapus ----------
        // Hapus berurutan: absensi (anak) dulu, baru pengajuan (induk)
        $conn->begin_transaction();
        $stmt = $conn->prepare("DELETE FROM absensi WHERE pengajuan_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt = $conn->prepare("DELETE FROM pengajuan WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $conn->commit();
        pesan('Riwayat ' . strtolower($p['jenis']) . ' ' . $p['nama'] . ' berhasil dihapus. Riwayat di halaman karyawan dan rekap absensi ikut terhapus.', 'success');

    } else {
        pesan('Aksi tidak dikenal.', 'danger');
    }
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    pesan('Gagal memproses data: ' . $e->getMessage(), 'danger');
}

header("Location: " . $tujuan);
exit;
