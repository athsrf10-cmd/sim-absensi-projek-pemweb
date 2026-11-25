<?php
// Menandai menu yang sedang dibuka dengan membandingkan alamat halaman
$halaman_sekarang = $_SERVER['SCRIPT_NAME'];
function menu_aktif($bagian) {
    global $halaman_sekarang;
    return strpos($halaman_sekarang, $bagian) !== false ? 'active' : '';
}

$adalah_admin = ($_SESSION['role'] === 'admin');
$nama_tampil  = ucfirst($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Karyon - Sistem Informasi Manajemen dan Absensi</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>assets/style.css" rel="stylesheet">
  <link href="<?= BASE_URL ?>assets/karyon.css" rel="stylesheet">
</head>
<body class="k-app">

<!-- ===== Navbar atas ===== -->
<nav class="navbar navbar-expand-lg k-nav">
  <div class="container-fluid px-0">

    <!-- Logo + nama sistem -->
    <a class="k-nav-brand" href="<?= BASE_URL ?>index.php">
      <span class="k-nav-logo"><i class="bi bi-fingerprint"></i></span>
      <span><b>Karyon</b><small>HCM ENTERPRISE</small></span>
    </a>
    <span class="k-pill-online d-none d-xl-inline-flex"><span class="k-dot"></span>Sistem Online Aktif</span>

    <!-- Tombol menu untuk layar kecil -->
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#k-menu">
      <i class="bi bi-list fs-2"></i>
    </button>

    <div class="collapse navbar-collapse" id="k-menu">
      <!-- Menu tengah: berbeda untuk admin dan karyawan -->
      <ul class="navbar-nav mx-auto k-tabs">
        <?php if ($adalah_admin): ?>
          <li class="nav-item"><a class="nav-link <?= menu_aktif('admin/dashboard') ?>" href="<?= BASE_URL ?>admin/dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link <?= menu_aktif('admin/karyawan/') ?>" href="<?= BASE_URL ?>admin/karyawan/index.php">Karyawan</a></li>
          <li class="nav-item"><a class="nav-link <?= menu_aktif('admin/jabatan/') ?>" href="<?= BASE_URL ?>admin/jabatan/index.php">Jabatan</a></li>
          <li class="nav-item"><a class="nav-link <?= menu_aktif('rekap_absensi') ?>" href="<?= BASE_URL ?>admin/rekap_absensi.php">Rekap Absensi</a></li>
          <?php $jml_menunggu = jumlah_pengajuan_menunggu($conn); ?>
          <li class="nav-item"><a class="nav-link <?= menu_aktif('admin/pengajuan') ?>" href="<?= BASE_URL ?>admin/pengajuan.php">Pengajuan Izin &amp; Cuti<?php if ($jml_menunggu > 0): ?><span class="k-notif"><?= $jml_menunggu ?></span><?php endif; ?></a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link <?= menu_aktif('karyawan/dashboard') ?>" href="<?= BASE_URL ?>karyawan/dashboard.php">Dashboard</a></li>
          <li class="nav-item"><a class="nav-link <?= menu_aktif('karyawan/absen') ?>" href="<?= BASE_URL ?>karyawan/absen.php">Absen</a></li>
          <li class="nav-item"><a class="nav-link <?= menu_aktif('karyawan/riwayat') ?>" href="<?= BASE_URL ?>karyawan/riwayat.php">Riwayat</a></li>
        <?php endif; ?>
      </ul>

      <!-- Kanan: info pengguna + tombol keluar -->
      <div class="k-user">
        <span class="k-user-avatar"><i class="bi bi-person-fill"></i></span>
        <span class="k-user-info">
          <b><?= h($nama_tampil) ?></b>
          <small><?= $adalah_admin ? 'Admin HR' : 'Karyawan' ?></small>
        </span>
        <a href="<?= BASE_URL ?>auth/logout.php" class="k-btn-keluar"><i class="bi bi-box-arrow-right"></i> Keluar</a>
      </div>
    </div>
  </div>
</nav>

<!-- Pembungkus isi halaman (ditutup di footer.php) -->
<div class="k-page">