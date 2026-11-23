<?php
require_once '../../auth/cek_session.php';
wajib_role('admin');

// id dari URL (saat dibuka) atau dari form (saat disimpan)
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// Ambil data karyawan beserta username akunnya
$data = query_aman($conn, "
    SELECT k.*, u.username
    FROM karyawan k LEFT JOIN users u ON u.karyawan_id = k.id
    WHERE k.id = ?", "i", [$id])->fetch_assoc();

if (!$data) {
    $_SESSION['pesan'] = 'Data karyawan tidak ditemukan.';
    $_SESSION['pesan_tipe'] = 'danger';
    header("Location: index.php");
    exit;
}

$jabatan = $conn->query("SELECT id, kode_jabatan, nama_jabatan FROM jabatan ORDER BY nama_jabatan");

// Nilai awal form = data di database
$f = [
    'nip' => $data['nip'], 'nama' => $data['nama'], 'jabatan_id' => (int)$data['jabatan_id'],
    'no_hp' => $data['no_hp'] ?? '', 'alamat' => $data['alamat'] ?? '', 'username' => $data['username'] ?? ''
];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($f as $kunci => $nilai) { $f[$kunci] = trim($_POST[$kunci] ?? ''); }
    $f['jabatan_id'] = (int)$f['jabatan_id'];
    $f['no_hp'] = preg_replace('/[\s-]/', '', $f['no_hp']);   // hapus spasi dan strip
    $password = $_POST['password'] ?? '';                     // kosong = password tidak diubah

    if ($f['nip'] === '' || $f['nama'] === '' || $f['jabatan_id'] === 0
        || $f['no_hp'] === '' || $f['username'] === '') {
        $error = 'Semua isian bertanda * wajib diisi.';
    } elseif (!preg_match('/^\+?[0-9]{8,15}$/', $f['no_hp'])) {
        $error = 'No. HP tidak valid. Gunakan angka saja (8-15 digit), contoh: 08123456789.';
    } elseif ($password !== '' && strlen($password) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } else {
        $conn->begin_transaction();
        try {
            // 1) Update data karyawan
            $stmt = $conn->prepare("UPDATE karyawan SET nip=?, nama=?, jabatan_id=?, no_hp=?, alamat=? WHERE id=?");
            $stmt->bind_param("ssissi", $f['nip'], $f['nama'], $f['jabatan_id'], $f['no_hp'], $f['alamat'], $id);
            $stmt->execute();

            // 2) Update akun login. Password hanya diganti kalau kolomnya diisi
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username=?, password=? WHERE karyawan_id=?");
                $stmt->bind_param("ssi", $f['username'], $hash, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=? WHERE karyawan_id=?");
                $stmt->bind_param("si", $f['username'], $id);
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
}

include '../../includes/header.php';
?>

<div class="kf-wrap">

  <div class="kf-top">
    <div>
      <div class="k-eyebrow">Manajemen Pegawai</div>
      <h1 class="k-title">Edit Karyawan</h1>
      <p class="k-lead">Perbarui data <b><?= h($data['nama']) ?></b> dan akun loginnya.</p>
    </div>
    <a href="index.php" class="k-btn-putih"><i class="bi bi-arrow-left"></i> Kembali</a>
  </div>

  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

  <form method="POST" autocomplete="off">
    <!-- id ikut terkirim saat form disubmit -->
    <input type="hidden" name="id" value="<?= $id ?>">

    <!-- ===== Kartu 1: Data Pegawai ===== -->
    <div class="kf-card">
      <div class="kf-card-head">
        <span class="kf-ico"><i class="bi bi-person-vcard"></i></span>
        <div><b>Data Pegawai</b><small>Informasi identitas dan kontak resmi karyawan.</small></div>
      </div>
      <div class="kf-card-body">
        <div class="kf-grid">
          <div>
            <label class="kf-label">NIP <span class="wajib">*</span></label>
            <div class="kf-input"><i class="bi bi-hash"></i>
              <input type="text" name="nip" required placeholder="Masukkan NIP" value="<?= h($f['nip']) ?>">
            </div>
          </div>
          <div>
            <label class="kf-label">Nama Lengkap <span class="wajib">*</span></label>
            <div class="kf-input"><i class="bi bi-person"></i>
              <input type="text" name="nama" required placeholder="Masukkan nama lengkap" value="<?= h($f['nama']) ?>">
            </div>
          </div>
          <div>
            <label class="kf-label">Jabatan <span class="wajib">*</span></label>
            <div class="kf-input"><i class="bi bi-briefcase"></i>
              <select name="jabatan_id" required>
                <option value="">-- Pilih Jabatan --</option>
                <?php while ($j = $jabatan->fetch_assoc()): ?>
                  <!-- selected: jabatan saat ini otomatis terpilih -->
                  <option value="<?= $j['id'] ?>" <?= $f['jabatan_id'] == $j['id'] ? 'selected' : '' ?>>
                    <?= h($j['nama_jabatan']) ?> (<?= h($j['kode_jabatan']) ?>)
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>
          <div>
            <label class="kf-label">No. HP <span class="wajib">*</span></label>
            <div class="kf-input"><i class="bi bi-telephone"></i>
              <input type="text" name="no_hp" required inputmode="tel" placeholder="Contoh: 08123456789" value="<?= h($f['no_hp']) ?>">
            </div>
          </div>
          <div class="kf-full">
            <label class="kf-label">Alamat</label>
            <div class="kf-input area"><i class="bi bi-geo-alt"></i>
              <textarea name="alamat" rows="2" placeholder="Masukkan alamat lengkap..."><?= h($f['alamat']) ?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Kartu 2: Akun Login ===== -->
    <div class="kf-card">
      <div class="kf-card-head">
        <span class="kf-ico"><i class="bi bi-key"></i></span>
        <div><b>Akun Login</b><small>Kredensial akses pegawai untuk sistem absensi.</small></div>
      </div>
      <div class="kf-card-body">
        <div class="kf-grid">
          <div>
            <label class="kf-label">Username <span class="wajib">*</span></label>
            <div class="kf-input"><i class="bi bi-at"></i>
              <input type="text" name="username" required autocomplete="off" placeholder="Masukkan username" value="<?= h($f['username']) ?>">
            </div>
          </div>
          <div>
            <label class="kf-label">Password Baru</label>
            <div class="kf-input"><i class="bi bi-lock"></i>
              <!-- new-password: mencegah browser menimpa dengan password tersimpan (misalnya milik admin) -->
              <input type="password" id="password" name="password" autocomplete="new-password" placeholder="Kosongkan jika tidak diganti">
              <button type="button" class="k-eye-btn toggle-pass" data-target="password"><i class="bi bi-eye"></i></button>
            </div>
            <div class="kf-hint">Kosongkan kalau password tidak ingin diubah.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="kf-actions">
      <a href="index.php" class="k-btn-putih">Batal</a>
      <button type="submit" class="k-btn-biru"><i class="bi bi-check-lg"></i> Update</button>
    </div>
  </form>
</div>

<?php include '../../includes/footer.php'; ?>