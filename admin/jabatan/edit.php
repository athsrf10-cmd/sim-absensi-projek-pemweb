<?php
require_once '../../auth/cek_session.php';
wajib_role('admin');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM jabatan WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$jabatan = $stmt->get_result()->fetch_assoc();

if (!$jabatan) {
    $_SESSION['pesan'] = 'Data jabatan tidak ditemukan.';
    $_SESSION['pesan_tipe'] = 'danger';
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_jabatan'] ?? '');
    if ($nama === '') {
        $error = 'Nama jabatan wajib diisi.';
    } else {
        $stmt = $conn->prepare("UPDATE jabatan SET nama_jabatan = ? WHERE id = ?");
        $stmt->bind_param("si", $nama, $id);
        $stmt->execute();
        $_SESSION['pesan'] = 'Jabatan berhasil diperbarui.';
        $_SESSION['pesan_tipe'] = 'success';
        header("Location: index.php");
        exit;
    }
}

include '../../includes/header.php';
?>

<div class="container mt-4" style="max-width:600px">
  <h3 class="mb-3">Edit Jabatan</h3>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="id" value="<?= $jabatan['id'] ?>">
        <div class="mb-3">
          <label class="form-label">Nama Jabatan</label>
          <input type="text" name="nama_jabatan" class="form-control"
                 value="<?= htmlspecialchars($jabatan['nama_jabatan']) ?>" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary">Update</button>
        <a href="index.php" class="btn btn-secondary">Batal</a>
      </form>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>