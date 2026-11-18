<?php
require_once '../../auth/cek_session.php';
wajib_role('admin');

// Ambil id dari URL (saat membuka halaman) atau dari form (saat disimpan)
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// Ambil data karyawan + username akunnya
$stmt = $conn->prepare("
    SELECT k.*, u.username
    FROM karyawan k
    LEFT JOIN users u ON u.karyawan_id = k.id
    WHERE k.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$karyawan = $stmt->get_result()->fetch_assoc();

// Kalau id tidak ada di database, kembali ke daftar
if (!$karyawan) {
    $_SESSION['pesan'] = 'Data karyawan tidak ditemukan.';
    $_SESSION['pesan_tipe'] = 'danger';
    header("Location: index.php");
    exit;
}

$jabatan = $conn->query("SELECT * FROM jabatan ORDER BY nama_jabatan");

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nip        = trim($_POST['nip'] ?? '');
    $nama       = trim($_POST['nama'] ?? '');
    $jabatan_id = (int)($_POST['jabatan_id'] ?? 0);
    $no_hp      = trim($_POST['no_hp'] ?? '');
    $alamat     = trim($_POST['alamat'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? ''; // boleh kosong = password tidak diubah

    if ($nip === '' || $nama === '' || $jabatan_id === 0 || $username === '') {
        $error = 'NIP, nama, jabatan, dan username wajib diisi.';
    } else {
        $conn->begin_transaction();
        try {
            // 1) Update data karyawan
            $stmt = $conn->prepare("UPDATE karyawan SET nip=?, nama=?, jabatan_id=?, no_hp=?, alamat=? WHERE id=?");
            $stmt->bind_param("ssissi", $nip, $nama, $jabatan_id, $no_hp, $alamat, $id);
            $stmt->execute();

            // 2) Update akun login. Password hanya diganti kalau kolomnya diisi
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username=?, password=? WHERE karyawan_id=?");
                $stmt->bind_param("ssi", $username, $hash, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=? WHERE karyawan_id=?");
                $stmt->bind_param("si", $username, $id);
            }
            $stmt->execute();

            $conn->commit();
            $_SESSION['pesan'] = 'Data karyawan berhasil diperbarui.';
            $_SESSION['pesan_tipe'] = 'success';
            header("Location: index.php");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $error = ($e->getCode() == 1062)
                ? 'NIP atau username sudah dipakai karyawan lain.'
                : 'Gagal memperbarui data: ' . $e->getMessage();
        }
    }
    // Kalau gagal, tampilkan kembali input terakhir pengguna di form
    $karyawan = array_merge($karyawan, $_POST);
}

include '../../includes/header.php';
?>

<div class="container mt-4" style="max-width:700px">
  <h3 class="mb-3">Edit Karyawan</h3>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="POST">
        <!-- id disimpan tersembunyi supaya ikut terkirim saat form disubmit -->
        <input type="hidden" name="id" value="<?= $id ?>">

        <h6 class="text-muted">Data Karyawan</h6>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">NIP</label>
            <input type="text" name="nip" class="form-control" required value="<?= htmlspecialchars($karyawan['nip']) ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama" class="form-control" required value="<?= htmlspecialchars($karyawan['nama']) ?>">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Jabatan</label>
            <select name="jabatan_id" class="form-select" required>
              <option value="">-- Pilih Jabatan --</option>
              <?php while ($j = $jabatan->fetch_assoc()): ?>
                <!-- selected: jabatan saat ini otomatis terpilih -->
                <option value="<?= $j['id'] ?>" <?= ($karyawan['jabatan_id'] == $j['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($j['nama_jabatan']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">No. HP</label>
            <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($karyawan['no_hp'] ?? '') ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Alamat</label>
          <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($karyawan['alamat'] ?? '') ?></textarea>
        </div>

        <hr>
        <h6 class="text-muted">Akun Login Karyawan</h6>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($karyawan['username'] ?? '') ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Password Baru</label>
            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diganti">
          </div>
        </div>

        <button type="submit" class="btn btn-primary">Update</button>
        <a href="index.php" class="btn btn-secondary">Batal</a>
      </form>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>