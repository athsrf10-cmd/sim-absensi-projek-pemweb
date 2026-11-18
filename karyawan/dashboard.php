<?php
require_once '../auth/cek_session.php';
wajib_role('karyawan');

$karyawan_id = (int)$_SESSION['karyawan_id'];

// Data profil karyawan + nama jabatannya
$stmt = $conn->prepare("
    SELECT k.*, j.nama_jabatan
    FROM karyawan k
    LEFT JOIN jabatan j ON k.jabatan_id = j.id
    WHERE k.id = ?
");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$profil = $stmt->get_result()->fetch_assoc();

// Absensi hari ini (bisa kosong kalau belum absen)
$stmt = $conn->prepare("SELECT * FROM absensi WHERE karyawan_id = ? AND tanggal = CURDATE()");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$hari_ini = $stmt->get_result()->fetch_assoc();

// Total hadir pada bulan ini
$stmt = $conn->prepare("
    SELECT COUNT(*) AS n FROM absensi
    WHERE karyawan_id = ? AND status = 'Hadir'
      AND MONTH(tanggal) = MONTH(CURDATE()) AND YEAR(tanggal) = YEAR(CURDATE())
");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$hadir_bulan_ini = $stmt->get_result()->fetch_assoc()['n'];

include '../includes/header.php';
?>

<div class="container mt-4">
  <h3 class="mb-1">Dashboard Karyawan</h3>
  <p class="text-muted">Selamat datang, <?= htmlspecialchars($profil['nama'] ?? $_SESSION['username']) ?>.</p>

  <div class="row g-3">
    <!-- Kartu profil -->
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header fw-semibold">Profil Saya</div>
        <div class="card-body">
          <table class="table table-borderless mb-0">
            <tr><td class="text-muted">NIP</td><td><?= htmlspecialchars($profil['nip'] ?? '-') ?></td></tr>
            <tr><td class="text-muted">Nama</td><td><?= htmlspecialchars($profil['nama'] ?? '-') ?></td></tr>
            <tr><td class="text-muted">Jabatan</td><td><?= htmlspecialchars($profil['nama_jabatan'] ?? '-') ?></td></tr>
            <tr><td class="text-muted">No. HP</td><td><?= htmlspecialchars($profil['no_hp'] ?? '-') ?></td></tr>
          </table>
        </div>
      </div>
    </div>

    <!-- Kartu status absensi hari ini -->
    <div class="col-md-6">
      <div class="card h-100">
        <div class="card-header fw-semibold">Absensi Hari Ini (<?= date('d-m-Y') ?>)</div>
        <div class="card-body">
          <?php if (!$hari_ini): ?>
            <p class="text-muted">Anda belum absen hari ini.</p>
            <a href="absen.php" class="btn btn-success">Absen Sekarang</a>
          <?php else: ?>
            <p class="mb-1">Masuk: <strong><?= $hari_ini['jam_masuk'] ?></strong></p>
            <p>Pulang: <strong><?= $hari_ini['jam_pulang'] ?? 'Belum absen pulang' ?></strong></p>
            <?php if ($hari_ini['jam_pulang'] === null): ?>
              <a href="absen.php" class="btn btn-danger">Absen Pulang</a>
            <?php endif; ?>
          <?php endif; ?>
          <hr>
          <div class="text-muted">Total hadir bulan ini</div>
          <div class="fs-3 fw-bold"><?= $hadir_bulan_ini ?> hari</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>