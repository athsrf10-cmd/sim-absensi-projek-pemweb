<?php
require_once '../../auth/cek_session.php';
wajib_role('admin');

$kode = ''; $nama = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = strtoupper(trim($_POST['kode_jabatan'] ?? ''));  // kode selalu huruf besar
    $nama = trim($_POST['nama_jabatan'] ?? '');

    if ($kode === '' || $nama === '') {
        $error = 'Kode dan nama jabatan wajib diisi.';
    } elseif (!preg_match('/^[A-Z0-9][A-Z0-9-]{1,19}$/', $kode)) {
        // Aturan: 2-20 karakter, hanya huruf besar, angka, dan tanda strip
        $error = 'Kode hanya boleh huruf, angka, dan tanda strip (2-20 karakter).';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO jabatan (kode_jabatan, nama_jabatan) VALUES (?, ?)");
            $stmt->bind_param("ss", $kode, $nama);
            $stmt->execute();
            $_SESSION['pesan'] = 'Jabatan berhasil ditambahkan.';
            $_SESSION['pesan_tipe'] = 'success';
            header("Location: index.php");
            exit;
        } catch (mysqli_sql_exception $e) {
            // Kode 1062 = data duplikat (kode jabatan sudah ada)
            $error = ($e->getCode() == 1062)
                ? 'Kode jabatan sudah dipakai jabatan lain.'
                : 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="kf-wrap sempit">

  <div class="kf-top">
    <div>
      <div class="k-eyebrow">Organisasi &amp; Manajemen SDM</div>
      <h1 class="k-title">Tambah Jabatan</h1>
      <p class="k-lead">Tambahkan jabatan baru yang dapat dipilih saat mendaftarkan karyawan.</p>
    </div>
    <a href="index.php" class="k-btn-putih"><i class="bi bi-arrow-left"></i> Kembali</a>
  </div>

  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="kf-card">
      <div class="kf-card-head">
        <span class="kf-ico"><i class="bi bi-briefcase"></i></span>
        <div><b>Informasi Jabatan</b><small>Isikan kode dan nama jabatan yang akan didaftarkan ke sistem.</small></div>
      </div>

      <div class="kf-card-body">
        <div class="kf-grid">
          <div>
            <label class="kf-label">Kode Jabatan <span class="wajib">*</span></label>
            <div class="kf-input"><i class="bi bi-upc-scan"></i>
              <!-- data-upper: otomatis huruf besar (diatur script.js) -->
              <input type="text" name="kode_jabatan" required maxlength="20" data-upper
                     placeholder="contoh: JBT-HRD" value="<?= h($kode) ?>" autofocus>
            </div>
            <div class="kf-hint"><i class="bi bi-info-circle"></i> Huruf besar, angka, dan strip. Harus unik.</div>
          </div>
          <div>
            <label class="kf-label">Nama Jabatan <span class="wajib">*</span></label>
            <div class="kf-input"><i class="bi bi-building"></i>
              <input type="text" name="nama_jabatan" required maxlength="50"
                     placeholder="contoh: Human Resources" value="<?= h($nama) ?>">
            </div>
            <div class="kf-hint">Nama resmi posisi kerja di perusahaan.</div>
          </div>
        </div>
      </div>

      <!-- Tombol aksi berada di dalam kartu, sesuai desain -->
      <div class="kf-card-foot">
        <a href="index.php" class="k-btn-putih">Batal</a>
        <button type="submit" class="k-btn-biru"><i class="bi bi-check-lg"></i> Simpan Jabatan</button>
      </div>
    </div>
  </form>
</div>

<?php include '../../includes/footer.php'; ?>