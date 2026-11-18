<?php
require_once '../auth/cek_session.php';
wajib_role('karyawan');

$karyawan_id = (int)$_SESSION['karyawan_id'];

// Ambil riwayat absensi HANYA milik karyawan yang login (WHERE karyawan_id = ?)
$stmt = $conn->prepare("SELECT * FROM absensi WHERE karyawan_id = ? ORDER BY tanggal DESC");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$data = $stmt->get_result();

include '../includes/header.php';
?>

<div class="container mt-4">
  <h3 class="mb-3">Riwayat Absensi</h3>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr><th>No</th><th>Tanggal</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if ($data->num_rows > 0): $no = 1; ?>
            <?php while ($row = $data->fetch_assoc()): ?>
              <tr>
                <td><?= $no++ ?></td>
                <!-- date(...) mengubah format 2026-11-18 menjadi 18-11-2026 -->
                <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                <td><?= $row['jam_masuk'] ?? '-' ?></td>
                <td><?= $row['jam_pulang'] ?? '-' ?></td>
                <td><span class="badge text-bg-info"><?= $row['status'] ?></span></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" class="text-center text-muted">Belum ada riwayat absensi.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>