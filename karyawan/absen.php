<?php
// Hanya role karyawan yang boleh membuka halaman ini
require_once '../auth/cek_session.php';
wajib_role('karyawan');

// id karyawan diambil dari session (disimpan saat login), bukan dari form,
// supaya karyawan tidak bisa mengabsenkan orang lain
$karyawan_id = (int)$_SESSION['karyawan_id'];

// Cek apakah hari ini sudah ada data absensi untuk karyawan ini
$stmt = $conn->prepare("SELECT * FROM absensi WHERE karyawan_id = ? AND tanggal = CURDATE()");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$hari_ini = $stmt->get_result()->fetch_assoc();

// Proses saat tombol absen ditekan (form dikirim lewat POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'masuk' && !$hari_ini) {
        // Absen masuk: buat baris baru dengan jam sekarang
        $stmt = $conn->prepare("INSERT INTO absensi (karyawan_id, tanggal, jam_masuk, status) VALUES (?, CURDATE(), CURTIME(), 'Hadir')");
        $stmt->bind_param("i", $karyawan_id);
        $stmt->execute();
        $_SESSION['pesan'] = 'Absen masuk berhasil dicatat.';
        $_SESSION['pesan_tipe'] = 'success';

    } elseif ($aksi === 'pulang' && $hari_ini && $hari_ini['jam_pulang'] === null) {
        // Absen pulang: hanya boleh kalau sudah absen masuk dan belum absen pulang
        $stmt = $conn->prepare("UPDATE absensi SET jam_pulang = CURTIME() WHERE id = ?");
        $stmt->bind_param("i", $hari_ini['id']);
        $stmt->execute();
        $_SESSION['pesan'] = 'Absen pulang berhasil dicatat.';
        $_SESSION['pesan_tipe'] = 'success';

    } else {
        $_SESSION['pesan'] = 'Aksi tidak valid atau sudah dilakukan hari ini.';
        $_SESSION['pesan_tipe'] = 'warning';
    }

    // Redirect supaya halaman tidak mengirim ulang form saat di-refresh (pola PRG)
    header("Location: absen.php");
    exit;
}

include '../includes/header.php';
?>

<div class="container mt-4" style="max-width:600px">
  <h3 class="mb-3">Absensi</h3>

  <!-- Pesan hasil aksi sebelumnya -->
  <?php if (isset($_SESSION['pesan'])): ?>
    <div class="alert alert-<?= $_SESSION['pesan_tipe'] ?> alert-dismissible fade show">
      <?= $_SESSION['pesan'] ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php unset($_SESSION['pesan'], $_SESSION['pesan_tipe']); ?>
  <?php endif; ?>

  <div class="card text-center">
    <div class="card-body p-4">
      <div class="text-muted"><?= date('d-m-Y') ?></div>
      <!-- Jam berjalan: diisi dan diperbarui tiap detik oleh JavaScript (assets/script.js) -->
      <div class="display-4 fw-bold mb-3" id="jam">--:--:--</div>

      <?php if (!$hari_ini): ?>
        <!-- Kondisi 1: belum absen sama sekali hari ini -->
        <p class="text-muted">Anda belum absen hari ini.</p>
        <form method="POST">
          <input type="hidden" name="aksi" value="masuk">
          <button type="submit" class="btn btn-success btn-lg w-100">Absen Masuk</button>
        </form>

      <?php elseif ($hari_ini['jam_pulang'] === null): ?>
        <!-- Kondisi 2: sudah masuk, belum pulang -->
        <p class="text-success">Absen masuk pukul <strong><?= $hari_ini['jam_masuk'] ?></strong></p>
        <form method="POST">
          <input type="hidden" name="aksi" value="pulang">
          <button type="submit" class="btn btn-danger btn-lg w-100">Absen Pulang</button>
        </form>

      <?php else: ?>
        <!-- Kondisi 3: masuk dan pulang sudah tercatat -->
        <div class="alert alert-info mb-0">
          Absensi hari ini selesai.<br>
          Masuk: <strong><?= $hari_ini['jam_masuk'] ?></strong> |
          Pulang: <strong><?= $hari_ini['jam_pulang'] ?></strong>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>