<?php
require_once '../../auth/cek_session.php';
wajib_role('admin');

// id dari URL (saat dibuka) atau dari form (saat disimpan)
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// Ambil data jabatan beserta jumlah karyawan yang memakainya
$jabatan = query_aman($conn, "
    SELECT j.*, COUNT(k.id) AS jumlah
    FROM jabatan j LEFT JOIN karyawan k ON k.jabatan_id = j.id
    WHERE j.id = ? GROUP BY j.id", "i", [$id])->fetch_assoc();

if (!$jabatan) {
    $_SESSION['pesan'] = 'Data jabatan tidak ditemukan.';
    $_SESSION['pesan_tipe'] = 'danger';
    header("Location: index.php");
    exit;
}

// Nilai awal form = data di database
$kode = $jabatan['kode_jabatan'];
$nama = $jabatan['nama_jabatan'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = strtoupper(trim($_POST['kode_jabatan'] ?? ''));
    $nama = trim($_POST['nama_jabatan'] ?? '');

    if ($kode === '' || $nama === '') {
        $error = 'Kode dan nama jabatan wajib diisi.';
    } elseif (!preg_match('/^[A-Z0-9][A-Z0-9-]{1,19}$/', $kode)) {
        $error = 'Kode hanya boleh huruf, angka, dan tanda strip (2-20 karakter).';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE jabatan SET kode_jabatan = ?, nama_jabatan = ? WHERE id = ?");
            $stmt->bind_param("ssi", $kode, $nama, $id);
            $stmt->execute();
            $_SESSION['pesan'] = 'Jabatan berhasil diperbarui.';
            $_SESSION['pesan_tipe'] = 'success';
            header("Location: index.php");
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = ($e->getCode() == 1062)
                ? 'Kode jabatan sudah dipakai jabatan lain.'
                : 'Gagal memperbarui data: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="kf-wrap sempit">

  <div class="kf-top">
    <div>
      <div class="k-eyebrow">Organisasi &amp; Manajemen SDM</div>
      <h1 class="k-title">Edit Jabatan</h1>
      <p class="k-lead">Saat ini dipakai oleh <b><?= $jabatan['jumlah'] ?></b> karyawan.</p>
    </div>
    <a href="index.php" class="k-btn-putih"><i class="bi bi-arrow-left"></i> Kembali</a>
  </div>

  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

  <form method="POST" autocomplete="off">
    <!-- id ikut terkirim saat form disubmit -->
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="kf-card">
      <div class="kf-card-head">
        <span class="kf-ico"><i class="bi bi-briefcase"></i></span>
        <div><b>Informasi Jabatan</b><small>Ubah kode atau nama jabatan.</small></div>
      </div>

      <div class="kf-card-body">
        <div class="kf-grid">
          <div>
            <label class="kf-label">Kode Jabatan <span class="wajib">*</span></label>
            <div class="kf-input"><i class="bi bi-upc-scan"></i>
              <input type="text" name="kode_jabatan" required maxlength="20" data-upper value="<?= h($kode) ?>">
            </div>
            <div class="kf-hint"><i class="bi bi-info-circle"></i> Mengubah kode tidak memengaruhi data karyawan.</div>
          </div>
          <div>
            <label class="kf-label">Nama Jabatan <span class="wajib">*</span></label>
            <div class="kf-input"><i class="bi bi-building"></i>
              <input type="text" name="nama_jabatan" required maxlength="50" value="<?= h($nama) ?>" autofocus>
            </div>
            <div class="kf-hint">Nama resmi posisi kerja di perusahaan.</div>
          </div>
        </div>
      </div>

      <div class="kf-card-foot">
        <a href="index.php" class="k-btn-putih">Batal</a>
        <button type="submit" class="k-btn-biru"><i class="bi bi-check-lg"></i> Update Jabatan</button>
      </div>
    </div>
  </form>
</div>

<?php include '../../includes/footer.php'; ?>