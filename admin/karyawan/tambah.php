<?php
require_once '../../auth/cek_session.php';
wajib_role('admin');

// Ambil daftar jabatan untuk mengisi dropdown
$jabatan = $conn->query("SELECT * FROM jabatan ORDER BY nama_jabatan");

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan bersihkan input dari form
    $nip        = trim($_POST['nip'] ?? '');
    $nama       = trim($_POST['nama'] ?? '');
    $jabatan_id = (int)($_POST['jabatan_id'] ?? 0);
    $no_hp      = trim($_POST['no_hp'] ?? '');
    $alamat     = trim($_POST['alamat'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $password   = $_POST['password'] ?? '';

    // Validasi sederhana di sisi server
    if ($nip === '' || $nama === '' || $jabatan_id === 0 || $username === '' || $password === '') {
        $error = 'NIP, nama, jabatan, username, dan password wajib diisi.';
    } else {
        // Transaksi: dua INSERT (karyawan + users) harus berhasil bersamaan
        // Kalau salah satu gagal, semuanya dibatalkan (rollback)
        $conn->begin_transaction();
        try {
            // 1) Simpan data karyawan
            $stmt = $conn->prepare("INSERT INTO karyawan (nip, nama, jabatan_id, no_hp, alamat) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiss", $nip, $nama, $jabatan_id, $no_hp, $alamat);
            $stmt->execute();
            $karyawan_id = $conn->insert_id; // id karyawan yang baru dibuat

            // 2) Buat akun login, password di-hash (tidak disimpan polos)
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, karyawan_id) VALUES (?, ?, 'karyawan', ?)");
            $stmt->bind_param("ssi", $username, $hash, $karyawan_id);
            $stmt->execute();

            $conn->commit(); // semua berhasil, simpan permanen
            $_SESSION['pesan'] = 'Karyawan dan akun login berhasil ditambahkan.';
            $_SESSION['pesan_tipe'] = 'success';
            header("Location: index.php");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback(); // ada yang gagal, batalkan semuanya
            // Kode 1062 = data duplikat (NIP atau username sudah ada)
            $error = ($e->getCode() == 1062)
                ? 'NIP atau username sudah dipakai.'
                : 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="container mt-4" style="max-width:700px">
  <h3 class="mb-3">Tambah Karyawan</h3>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="POST">
        <h6 class="text-muted">Data Karyawan</h6>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">NIP</label>
            <!-- value=... mengisi ulang form kalau terjadi error, supaya tidak mengetik ulang -->
            <input type="text" name="nip" class="form-control" required value="<?= htmlspecialchars($_POST['nip'] ?? '') ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama" class="form-control" required value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Jabatan</label>
            <select name="jabatan_id" class="form-select" required>
              <option value="">-- Pilih Jabatan --</option>
              <?php while ($j = $jabatan->fetch_assoc()): ?>
                <option value="<?= $j['id'] ?>" <?= (($_POST['jabatan_id'] ?? '') == $j['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($j['nama_jabatan']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">No. HP</label>
            <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Alamat</label>
          <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
        </div>

        <hr>
        <h6 class="text-muted">Akun Login Karyawan</h6>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="index.php" class="btn btn-secondary">Batal</a>
      </form>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>