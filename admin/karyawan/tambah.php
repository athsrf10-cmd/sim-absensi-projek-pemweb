<?php
require_once '../../auth/cek_session.php';
wajib_role('admin');

// Daftar jabatan untuk dropdown (tampil: Nama (KODE))
$jabatan = $conn->query("SELECT id, kode_jabatan, nama_jabatan FROM jabatan ORDER BY nama_jabatan");

// Nilai awal form. Kalau terjadi error, diisi ulang dari input pengguna
$f = ['nip' => '', 'nama' => '', 'jabatan_id' => 0, 'no_hp' => '', 'alamat' => '', 'username' => ''];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil dan bersihkan semua isian form
    foreach ($f as $kunci => $nilai) { $f[$kunci] = trim($_POST[$kunci] ?? ''); }
    $f['jabatan_id'] = (int)$f['jabatan_id'];
    $f['no_hp'] = preg_replace('/[\s-]/', '', $f['no_hp']);   // hapus spasi dan strip: 0812-3456 -> 08123456
    $password = $_POST['password'] ?? '';

    if ($f['nip'] === '' || $f['nama'] === '' || $f['jabatan_id'] === 0
        || $f['no_hp'] === '' || $f['username'] === '' || $password === '') {
        $error = 'Semua isian bertanda * wajib diisi.';
    } elseif (!preg_match('/^\+?[0-9]{8,15}$/', $f['no_hp'])) {
        $error = 'No. HP tidak valid. Gunakan angka saja (8-15 digit), contoh: 08123456789.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } else {
        // Transaksi: simpan karyawan + akun login bersamaan, kalau salah satu gagal dibatalkan semua
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO karyawan (nip, nama, jabatan_id, no_hp, alamat) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiss", $f['nip'], $f['nama'], $f['jabatan_id'], $f['no_hp'], $f['alamat']);
            $stmt->execute();
            $karyawan_id = $conn->insert_id;   // id karyawan yang baru dibuat

            $hash = password_hash($password, PASSWORD_DEFAULT);   // password disimpan dalam bentuk hash
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, karyawan_id) VALUES (?, ?, 'karyawan', ?)");
            $stmt->bind_param("ssi", $f['username'], $hash, $karyawan_id);
            $stmt->execute();

            $conn->commit();
            $_SESSION['pesan'] = 'Karyawan dan akun login berhasil ditambahkan.';
            $_SESSION['pesan_tipe'] = 'success';
            header("Location: index.php");
            exit;
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $error = ($e->getCode() == 1062)
                ? 'NIP atau username sudah dipakai.'
                : 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="kf-wrap">

  <!-- Judul halaman + tombol kembali -->
  <div class="kf-top">
    <div>
      <div class="k-eyebrow">Manajemen Pegawai</div>
      <h1 class="k-title">Tambah Karyawan</h1>
      <p class="k-lead">Data pegawai baru beserta akun login untuk absensi.</p>
    </div>
    <a href="index.php" class="k-btn-putih"><i class="bi bi-arrow-left"></i> Kembali</a>
  </div>

  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

  <!-- autocomplete="off": mencegah browser mengisi form dengan data login admin -->
  <form method="POST" autocomplete="off">

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
              <input type="text" name="nip" required placeholder="Masukkan NIP" value="<?= h($f['nip']) ?>" autofocus>
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
                  <!-- selected: mengembalikan pilihan sebelumnya kalau form error -->
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
              <!-- autocomplete="off" + name khusus supaya tidak terisi otomatis -->
              <input type="text" name="username" required autocomplete="off" placeholder="Masukkan username" value="<?= h($f['username']) ?>">
            </div>
          </div>
          <div>
            <label class="kf-label">Password <span class="wajib">*</span></label>
            <div class="kf-input"><i class="bi bi-lock"></i>
              <!-- new-password: memberi tahu browser ini password BARU, jangan diisi dengan yang tersimpan -->
              <input type="password" id="password" name="password" required minlength="6" autocomplete="new-password" placeholder="Minimal 6 karakter">
              <button type="button" class="k-eye-btn toggle-pass" data-target="password"><i class="bi bi-eye"></i></button>
            </div>
            <div class="kf-hint">Minimal 6 karakter.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tombol aksi -->
    <div class="kf-actions">
      <a href="index.php" class="k-btn-putih">Batal</a>
      <button type="submit" class="k-btn-biru"><i class="bi bi-check-lg"></i> Simpan</button>
    </div>
  </form>
</div>

<?php include '../../includes/footer.php'; ?>