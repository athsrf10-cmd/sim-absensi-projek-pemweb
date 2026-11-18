<?php
require_once '../../auth/cek_session.php';
wajib_role('admin');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_jabatan'] ?? '');
    if ($nama === '') {
        $error = 'Nama jabatan wajib diisi.';
    } else {
        $stmt = $conn->prepare("INSERT INTO jabatan (nama_jabatan) VALUES (?)");
        $stmt->bind_param("s", $nama);
        $stmt->execute();
        $_SESSION['pesan'] = 'Jabatan berhasil ditambahkan.';
        $_SESSION['pesan_tipe'] = 'success';
        header("Location: index.php");
        exit;
    }
}

include '../../includes/header.php';
?>

<div class="container mt-4" style="max-width:600px">
  <h3 class="mb-3">Tambah Jabatan</h3>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Nama Jabatan</label>
          <input type="text" name="nama_jabatan" class="form-control" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="index.php" class="btn btn-secondary">Batal</a>
      </form>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>