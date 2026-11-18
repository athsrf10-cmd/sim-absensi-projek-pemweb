<?php
// Mulai session dan muat koneksi (untuk BASE_URL)
session_start();
require_once '../config/koneksi.php';

// Kalau sudah login, tidak perlu melihat halaman login lagi
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk - Karyon</title>

  <!-- Font Inter (teks) dan Plus Jakarta Sans (judul) dari Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <!-- Bootstrap (untuk reset dan alert) + Bootstrap Icons (ikon) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- CSS khusus halaman login -->
  <link href="<?= BASE_URL ?>assets/login.css" rel="stylesheet">
</head>
<body class="k-login">

  <!-- ===== Bilah atas (topbar) ===== -->
  <header class="k-topbar">
    <div class="k-brand">
      <!-- Kotak logo. Ganti isinya dengan <img src="..."> kalau punya file logo -->
      <span class="k-logo-box"><i class="bi bi-person-badge"></i></span>
      <span class="k-brand-name">Karyon</span>
      <span class="k-brand-tag">HCM ENTERPRISE</span>
      <span class="k-status"><span class="k-dot"></span><span data-i18n="status">Sistem Online Aktif</span></span>
    </div>

    <div class="k-top-right">
      <!-- Tombol ganti bahasa ID / EN (dikendalikan login.js) -->
      <div class="k-lang">
        <button type="button" class="active" data-lang="id">ID</button>
        <button type="button" data-lang="en">EN</button>
      </div>
      <span class="k-avatar"><i class="bi bi-person-fill"></i></span>
    </div>
  </header>

  <!-- ===== Konten utama: kartu login ===== -->
  <main class="k-main">
    <div class="k-card">

      <div class="k-card-brand">
        <i class="bi bi-shield-fill-check"></i><span>Karyon</span>
      </div>
      <h1 class="k-title" data-i18n="judul">Masuk ke Akun</h1>
      <p class="k-subtitle" data-i18n="subjudul">Sistem Informasi Manajemen dan Absensi</p>

      <!-- Pesan error muncul kalau proses_login.php mengirim ?error=1 -->
      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger py-2 small" data-i18n="error">Username atau password salah.</div>
      <?php endif; ?>

      <!-- Form dikirim ke proses_login.php dengan method POST -->
      <form action="proses_login.php" method="POST" id="form-login">

        <label class="k-label" for="username" data-i18n="label_user">Username</label>
        <div class="k-input">
          <i class="bi bi-person-vcard"></i>
          <input type="text" id="username" name="username" required autofocus
                 placeholder="Masukkan username" data-i18n-ph="ph_user">
        </div>

        <div class="k-row">
          <label class="k-label" for="password" data-i18n="label_pass">Password</label>
          <a href="#" id="lupa-sandi" class="k-link" data-i18n="lupa">Lupa Sandi?</a>
        </div>
        <div class="k-input">
          <i class="bi bi-lock"></i>
          <input type="password" id="password" name="password" required
                 placeholder="Masukkan kata sandi" data-i18n-ph="ph_pass">
          <!-- Tombol mata: menampilkan/menyembunyikan password -->
          <button type="button" id="toggle-pass" class="k-eye" aria-label="Tampilkan password">
            <i class="bi bi-eye"></i>
          </button>
        </div>

        <!-- Checkbox ini hanya tampilan (belum menyimpan sesi lebih lama) -->
        <label class="k-check">
          <input type="checkbox" checked> <span data-i18n="ingat">Ingat saya</span>
        </label>

        <button type="submit" class="k-btn">
          <span data-i18n="tombol">Masuk ke Sistem</span> <i class="bi bi-arrow-right"></i>
        </button>
      </form>

      <div class="k-card-foot">
        <span class="k-secure"><i class="bi bi-lock-fill"></i> <span data-i18n="aman">Kata sandi tersimpan terenkripsi</span></span>
        <a href="#" id="bantuan-hrd" class="k-link" data-i18n="bantuan_hrd">Bantuan HRD</a>
      </div>
    </div>
  </main>

  <!-- ===== Footer ===== -->
  <footer class="k-footer">
    <span>&copy; <?= date('Y') ?> Karyon HCM Enterprise. <span data-i18n="hak_cipta">Seluruh hak cipta dilindungi.</span></span>
    <nav>
      <a href="#" data-i18n="f_bantuan">Bantuan</a>
      <a href="#" data-i18n="f_privasi">Kebijakan Privasi</a>
      <a href="#" data-i18n="f_syarat">Syarat &amp; Ketentuan</a>
    </nav>
  </footer>

  <script src="<?= BASE_URL ?>assets/login.js"></script>
</body>
</html>