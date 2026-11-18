<?php
require_once '../auth/cek_session.php';
require_once '../includes/fungsi.php';
wajib_role('admin');

$daftar      = $conn->query("SELECT id, nip, nama FROM karyawan ORDER BY nama");
$karyawan_id = (int)($_GET['karyawan'] ?? $_POST['karyawan_id'] ?? 0);
$tanggal     = $_GET['tanggal'] ?? $_POST['tanggal'] ?? date('Y-m-d');
$status = 'Hadir'; $jam_masuk = ''; $jam_pulang = '';

// Kalau karyawan + tanggal sudah punya data absensi, isi form dengan data itu (mode ubah)
if ($karyawan_id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $conn->prepare("SELECT * FROM absensi WHERE karyawan_id = ? AND tanggal = ?");
    $stmt->bind_param("is", $karyawan_id, $tanggal);
    $stmt->execute();
    if ($ada = $stmt->get_result()->fetch_assoc()) {
        $status     = $ada['status'];
        $jam_masuk  = substr($ada['jam_masuk'] ?? '', 0, 5);   // "08:02:14" -> "08:02"
        $jam_pulang = substr($ada['jam_pulang'] ?? '', 0, 5);
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status     = $_POST['status'] ?? '';
    $jam_masuk  = $_POST['jam_masuk'] ?? '';
    $jam_pulang = $_POST['jam_pulang'] ?? '';

    // Validasi
    if ($karyawan_id === 0) {
        $error = 'Pilih karyawan terlebih dahulu.';
    } elseif (!in_array($status, ['Hadir', 'Izin', 'Sakit', 'Alpha'])) {
        $error = 'Status tidak valid.';
    } elseif ($status === 'Hadir' && $jam_masuk === '') {
        $error = 'Jam masuk wajib diisi untuk status Hadir.';
    } else {
        // Izin/Sakit/Alpha tidak punya jam masuk/pulang
        if ($status !== 'Hadir') { $jam_masuk = ''; $jam_pulang = ''; }
        // Jam kosong disimpan sebagai NULL di database
        $jm = $jam_masuk  !== '' ? $jam_masuk  : null;
        $jp = $jam_pulang !== '' ? $jam_pulang : null;

        // ON DUPLICATE KEY UPDATE: kalau karyawan + tanggal sudah ada, data lama diperbarui
        // (bekerja karena tabel punya UNIQUE (karyawan_id, tanggal))
        $stmt = $conn->prepare("
            INSERT INTO absensi (karyawan_id, tanggal, jam_masuk, jam_pulang, status)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE jam_masuk = VALUES(jam_masuk),
                                    jam_pulang = VALUES(jam_pulang),
                                    status = VALUES(status)
        ");
        $stmt->bind_param("issss", $karyawan_id, $tanggal, $jm, $jp, $status);
        $stmt->execute();

        $_SESSION['pesan'] = 'Absensi berhasil dicatat.';
        $_SESSION['pesan_tipe'] = 'success';
        header("Location: dashboard.php?tanggal=" . urlencode($tanggal));
        exit;
    }
}

include '../includes/header.php';
?>

<div style="max-width:640px">
  <h2 class="k-title mb-3">Catat Absensi Manual</h2>

  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

  <div class="k-panel p-4">
    <form method="POST">
      <div class="mb-3">
        <label class="form-label fw-semibold">Karyawan</label>
        <select name="karyawan_id" class="form-select" required>
          <option value="">-- Pilih Karyawan --</option>
          <?php while ($k = $daftar->fetch_assoc()): ?>
            <option value="<?= $k['id'] ?>" <?= $karyawan_id == $k['id'] ? 'selected' : '' ?>>
              <?= h($k['nama']) ?> (<?= h($k['nip']) ?>)
            </option>
          <?php endwhile; ?>
        </select>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label fw-semibold">Tanggal</label>
          <input type="date" name="tanggal" class="form-control" value="<?= h($tanggal) ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label fw-semibold">Status</label>
          <select name="status" id="status-manual" class="form-select">
            <?php foreach (['Hadir', 'Izin', 'Sakit', 'Alpha'] as $s): ?>
              <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Kolom jam: otomatis nonaktif kalau status bukan Hadir (diatur script di bawah) -->
      <div class="row" id="kolom-jam">
        <div class="col-md-6 mb-3">
          <label class="form-label fw-semibold">Jam Masuk</label>
          <input type="time" name="jam_masuk" class="form-control" value="<?= h($jam_masuk) ?>">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label fw-semibold">Jam Pulang <span class="text-muted fw-normal">(opsional)</span></label>
          <input type="time" name="jam_pulang" class="form-control" value="<?= h($jam_pulang) ?>">
        </div>
      </div>

      <button type="submit" class="btn btn-primary">Simpan</button>
      <a href="dashboard.php?tanggal=<?= h($tanggal) ?>" class="btn btn-secondary">Batal</a>
    </form>
  </div>
</div>

<script>
  // Nonaktifkan input jam kalau status Izin / Sakit / Alpha
  var pilihStatus = document.getElementById('status-manual');
  var inputJam = document.querySelectorAll('#kolom-jam input');
  function aturJam() {
    var hadir = pilihStatus.value === 'Hadir';
    inputJam.forEach(function (el) { el.disabled = !hadir; });
  }
  pilihStatus.addEventListener('change', aturJam);
  aturJam(); // jalankan sekali saat halaman dibuka
</script>

<?php include '../includes/footer.php'; ?>