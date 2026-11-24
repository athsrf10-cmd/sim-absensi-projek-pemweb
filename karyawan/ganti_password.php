<?php
require_once '../auth/cek_session.php';
wajib_role('karyawan');

date_default_timezone_set('Asia/Jakarta');

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$karyawan_id = (int)$_SESSION['karyawan_id'];

/* ==========================================================
   KONFIGURASI TABEL PENGGUNA
   Sesuaikan dengan database Anda: tabel yang menyimpan kata sandi,
   kolom penghubung ke karyawan, dan nama kolom kata sandinya.
========================================================== */
$TABEL_USER      = 'users';
$KOLOM_ID        = 'karyawan_id';
$KOLOM_PASSWORD  = 'password';
$NILAI_ID        = $karyawan_id;
foreach ([$TABEL_USER, $KOLOM_ID, $KOLOM_PASSWORD] as $nama_konfig) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $nama_konfig)) die('Konfigurasi tidak valid.');
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

// ---------- Data untuk navbar ----------
$stmt = $conn->prepare("
    SELECT k.*, j.nama_jabatan
    FROM karyawan k
    LEFT JOIN jabatan j ON k.jabatan_id = j.id
    WHERE k.id = ?
");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$profil = $stmt->get_result()->fetch_assoc() ?: [];
$nama         = $profil['nama'] ?? $_SESSION['username'] ?? 'Karyawan';
$nama_jabatan = $profil['nama_jabatan'] ?? 'Karyawan';
$nip          = $profil['nip'] ?? '-';

$errors = [];

// ---------- Proses ganti kata sandi ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lama    = $_POST['password_lama']  ?? '';
    $baru    = $_POST['password_baru']  ?? '';
    $konfirm = $_POST['password_konfirmasi'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errors['umum'] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        if ($lama === '')    $errors['lama'] = 'Kata sandi saat ini wajib diisi.';
        if ($baru === '')    $errors['baru'] = 'Kata sandi baru wajib diisi.';
        elseif (strlen($baru) < 8)                $errors['baru'] = 'Minimal 8 karakter.';
        elseif (!preg_match('/[a-z]/', $baru) || !preg_match('/[A-Z]/', $baru) || !preg_match('/[0-9]/', $baru))
                                                  $errors['baru'] = 'Harus mengandung huruf besar, huruf kecil, dan angka.';
        elseif ($baru === $lama)                  $errors['baru'] = 'Kata sandi baru tidak boleh sama dengan yang lama.';
        if ($konfirm === '') $errors['konfirmasi'] = 'Ulangi kata sandi baru.';
        elseif ($baru !== $konfirm) $errors['konfirmasi'] = 'Konfirmasi kata sandi tidak cocok.';

        if (!$errors) {
            try {
                $stmt = $conn->prepare("SELECT `$KOLOM_PASSWORD` AS pw FROM `$TABEL_USER` WHERE `$KOLOM_ID` = ? LIMIT 1");
                $stmt->bind_param("i", $NILAI_ID);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();

                if (!$row) {
                    $errors['umum'] = 'Akun pengguna tidak ditemukan.';
                } else {
                    $tersimpan = (string)$row['pw'];
                    // Hash modern; jika masih teks biasa (data lama) dibandingkan langsung
                    $cocok = password_get_info($tersimpan)['algo']
                        ? password_verify($lama, $tersimpan)
                        : hash_equals($tersimpan, $lama);

                    if (!$cocok) {
                        $errors['lama'] = 'Kata sandi saat ini salah.';
                    } else {
                        $hash = password_hash($baru, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE `$TABEL_USER` SET `$KOLOM_PASSWORD` = ? WHERE `$KOLOM_ID` = ?");
                        $stmt->bind_param("si", $hash, $NILAI_ID);
                        $stmt->execute();

                        session_regenerate_id(true);
                        $_SESSION['csrf'] = bin2hex(random_bytes(32));
                        $_SESSION['flash_akun'] = ['tipe' => 'success', 'pesan' => 'Kata sandi berhasil diubah. Gunakan kata sandi baru saat login berikutnya.'];
                        header("Location: ganti_password.php");
                        exit;
                    }
                }
            } catch (Throwable $e) {
                $errors['umum'] = 'Tabel pengguna belum sesuai. Periksa bagian KONFIGURASI di atas file ini.';
            }
        }
    }
}

$flash = $_SESSION['flash_akun'] ?? null;
unset($_SESSION['flash_akun']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ganti Kata Sandi - Karyon HCM Enterprise</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{min-height:100vh;display:flex;flex-direction:column;background:#f6f8fe;color:#0f172a;
  font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;font-size:12px;-webkit-font-smoothing:antialiased}
a{text-decoration:none}

/* ===== NAVBAR (sama dengan halaman lain) ===== */
.topbar{background:#fff;height:50px;padding:0 32px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 1px 0 #eaeef8}
.brand{display:flex;align-items:center;gap:9px;color:#0f172a}
.brand-logo{width:30px;height:30px;border-radius:8px;background:#1e56d6;color:#fff;display:grid;place-items:center;font-size:14px}
.brand b{display:block;font-size:13.5px;font-weight:700;line-height:1.1}
.brand small{display:block;font-size:7.5px;font-weight:700;letter-spacing:.7px;color:#1e56d6;line-height:1.2}
.nav-pill{display:flex;gap:2px;background:#eef2fb;padding:4px;border-radius:11px}
.nav-pill a{padding:7px 16px;border-radius:8px;font-size:11px;font-weight:500;color:#475569}
.nav-pill a.active{background:#fff;color:#1d4ed8;font-weight:700;box-shadow:0 0 0 2px #e3ebfb,0 1px 3px rgba(15,23,42,.1)}
.user-area{display:flex;align-items:center;gap:16px}
.user{display:flex;align-items:center;gap:9px}
.avatar{width:28px;height:28px;border-radius:50%;background:#1e56d6;color:#fff;font-size:12px;font-weight:700;display:grid;place-items:center}
.user b{display:block;font-size:10.5px;font-weight:700;line-height:1.25}
.user small{display:block;font-size:8.5px;color:#64748b;line-height:1.25}
.logout{font-size:10.5px;font-weight:500;color:#dc2626}
.logout .bi{margin-right:3px}

/* ===== KONTEN & FOOTER ===== */
.main-wrap{width:100%;max-width:1400px;margin:0 auto;padding:22px 32px 50px;flex:1}
.site-footer{background:#edf1fc;height:50px;padding:0 32px;display:flex;align-items:center;font-size:10px;color:#475569}
.site-footer b{font-weight:500;color:#1e293b}
.site-footer i{display:inline-block;width:4px;height:4px;border-radius:50%;background:#cbd5e1;margin:0 8px}

/* ===== JUDUL HALAMAN ===== */
.page-intro{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:19px}
.page-intro h1{font-size:25px;font-weight:800;letter-spacing:-.7px;line-height:1.2;margin:0 0 4px;color:#0f172a}
.page-intro p{margin:0;font-size:10.5px;color:#64748b}
.blue-tag{display:inline-flex;align-items:center;gap:6px;background:#fff;color:#1d4ed8;font-size:10px;font-weight:700;height:28px;padding:0 16px;border-radius:8px;box-shadow:0 1px 4px rgba(15,23,42,.08)}
.blue-tag:hover{background:#f3f6fe}

/* ===== KARTU ===== */
.emp-card{background:#fff;border:1px solid #edf0f6;border-radius:12px;padding:22px;min-width:0;box-shadow:0 3px 16px rgba(30,64,175,.05)}
.card-title{display:flex;align-items:center;gap:10px;margin:0 0 4px;font-size:13px;font-weight:700;color:#0f172a}
.card-title .ico{width:28px;height:28px;border-radius:8px;background:#dce6fb;color:#1e40af;display:grid;place-items:center;font-size:13px}
.card-sub{margin:0 0 18px 38px;font-size:9.5px;color:#64748b}
.status-pill{display:inline-block;background:#eff4ff;color:#1551a0;padding:5px 10px;border-radius:15px;font-size:9px;font-weight:600}
.status-pill.green{background:#d4fae9;color:#07865e}

/* ===== NOTIFIKASI ===== */
.notice{display:flex;align-items:center;gap:10px;border-radius:10px;padding:11px 14px;margin-bottom:16px;font-size:11px;font-weight:500}
.notice.success{background:#e3fbf0;color:#065f46}
.notice.danger{background:#fdecec;color:#991b1b}
.notice .bi{font-size:14px}
.notice button{margin-left:auto;border:0;background:transparent;color:inherit;font-size:14px;cursor:pointer;line-height:1}

/* ===== FORM ===== */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px 18px}
.field{display:flex;flex-direction:column;gap:6px;min-width:0}
.field.full{grid-column:1/-1}
.field label{font-size:9.5px;font-weight:700;letter-spacing:.3px;color:#475569}
.field label em{color:#dc2626;font-style:normal}
.input-wrap{position:relative;display:flex;align-items:center}
.input-wrap>.bi{position:absolute;left:13px;font-size:12px;color:#64748b;pointer-events:none}
.input-wrap input{width:100%;height:38px;border:1px solid #e3e9f6;background:#f4f7fd;border-radius:9px;padding:0 38px 0 36px;font-family:inherit;font-size:11px;color:#1e293b;outline:0;transition:.15s}
.input-wrap input::placeholder{color:#94a3b8}
.input-wrap input:focus{background:#fff;border-color:#0048c8;box-shadow:0 0 0 3px rgba(0,72,200,.12)}
.input-wrap input[readonly]{background:#eef1f8;color:#64748b;cursor:not-allowed}
.input-wrap.invalid input{border-color:#ef4444;background:#fff8f8}
.eye{position:absolute;right:6px;width:28px;height:28px;border:0;background:transparent;color:#64748b;border-radius:7px;cursor:pointer;display:grid;place-items:center;font-size:13px}
.eye:hover{background:#e6ecf9;color:#0048c8}
.hint{font-size:9px;color:#64748b}
.err{font-size:9px;color:#dc2626;font-weight:500}
.form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px;padding-top:18px;border-top:1px solid #edf0f8}
.btn-x,.btn-main{height:38px;border:0;border-radius:9px;padding:0 18px;font-family:inherit;font-size:11px;font-weight:700;display:inline-flex;align-items:center;gap:8px;cursor:pointer;white-space:nowrap}
.btn-x{background:#e3ebfb;color:#1d4ed8}
.btn-main{background:#0048c8;color:#fff;box-shadow:0 6px 14px rgba(0,72,200,.2)}
.btn-x:hover{filter:brightness(.97)}.btn-main:hover{filter:brightness(1.1)}

@media(max-width:900px){.form-grid{grid-template-columns:1fr}}
@media(max-width:700px){
  .topbar{padding:0 16px}.user div{display:none}
  .main-wrap{padding:20px 16px 40px}
  .form-actions{flex-direction:column-reverse}.form-actions>*{justify-content:center}
}


/* ===== KHUSUS HALAMAN GANTI KATA SANDI ===== */
.account-layout{display:grid;grid-template-columns:1fr 340px;gap:18px;align-items:start}
.pw-fields{display:grid;gap:16px;max-width:520px}
.strength{display:grid;grid-template-columns:repeat(4,1fr);gap:5px;margin-top:2px}
.strength i{height:5px;border-radius:3px;background:#e3e9f6;transition:.2s}
.strength.s1 i:nth-child(-n+1){background:#ef4444}
.strength.s2 i:nth-child(-n+2){background:#f59e0b}
.strength.s3 i:nth-child(-n+3){background:#3b82f6}
.strength.s4 i{background:#10b981}
.strength-text{font-size:9px;color:#64748b;margin-top:4px}
.rules{list-style:none;margin:0;padding:0;display:grid;gap:9px}
.rules li{display:flex;align-items:center;gap:9px;font-size:10px;color:#475569}
.rules .bi{font-size:13px;color:#b6bfd3;transition:.15s}
.rules li.ok{color:#065f46;font-weight:600}
.rules li.ok .bi{color:#10b981}
.tips{background:#f0f4ff;border-radius:10px;padding:14px;margin-top:18px;font-size:9.5px;line-height:1.7;color:#475569}
.tips b{display:flex;align-items:center;gap:7px;color:#24364e;font-size:10px;margin-bottom:6px}
.tips b .bi{color:#0751bd}
.match{font-size:9px;font-weight:500}
.match.ok{color:#07865e}.match.no{color:#dc2626}
@media(max-width:900px){.account-layout{grid-template-columns:1fr}}
</style>
</head>
<body>

<nav class="topbar">
  <a class="brand" href="dashboard.php">
    <span class="brand-logo"><i class="bi bi-buildings-fill"></i></span>
    <span><b>Karyon</b><small>HCM ENTERPRISE</small></span>
  </a>
  <div class="nav-pill">
    <a href="dashboard.php">Dashboard</a>
    <a href="absen.php">Absen</a>
    <a href="riwayat.php">Riwayat</a>
  </div>
  <div class="user-area">
    <div class="user">
      <span class="avatar"><?= h(strtoupper(mb_substr($nama, 0, 1))) ?></span>
      <div><b><?= h($nama) ?></b><small><?= h($nama_jabatan) ?> (NIP: <?= h($nip) ?>)</small></div>
    </div>
    <a class="logout" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i>Keluar</a>
  </div>
</nav>

<main class="main-wrap">

<header class="page-intro">
  <div>
    <h1>Ganti Kata Sandi</h1>
    <p>Gunakan kata sandi yang kuat dan jangan dibagikan kepada siapa pun.</p>
  </div>
  <a class="blue-tag" href="dashboard.php"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
</header>

<?php if ($flash): ?>
<div class="notice <?= h($flash['tipe']) ?>">
  <i class="bi bi-check-circle-fill"></i> <?= h($flash['pesan']) ?>
  <button type="button" onclick="this.parentNode.remove()" aria-label="Tutup">×</button>
</div>
<?php endif; ?>
<?php if (!empty($errors['umum'])): ?>
<div class="notice danger"><i class="bi bi-exclamation-circle-fill"></i> <?= h($errors['umum']) ?></div>
<?php endif; ?>

<section class="account-layout">

  <article class="emp-card">
    <h2 class="card-title"><span class="ico"><i class="bi bi-shield-lock"></i></span> Ubah Kata Sandi</h2>
    <p class="card-sub">Masukkan kata sandi saat ini, lalu tentukan kata sandi baru.</p>

    <form method="POST" id="formPw" novalidate autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

      <div class="pw-fields">

        <div class="field">
          <label for="password_lama">KATA SANDI SAAT INI <em>*</em></label>
          <div class="input-wrap <?= isset($errors['lama']) ? 'invalid' : '' ?>">
            <i class="bi bi-key"></i>
            <input type="password" id="password_lama" name="password_lama" placeholder="Masukkan kata sandi saat ini" autocomplete="current-password">
            <button type="button" class="eye" data-target="password_lama" aria-label="Tampilkan kata sandi"><i class="bi bi-eye"></i></button>
          </div>
          <?php if (isset($errors['lama'])): ?><span class="err"><?= h($errors['lama']) ?></span><?php endif; ?>
        </div>

        <div class="field">
          <label for="password_baru">KATA SANDI BARU <em>*</em></label>
          <div class="input-wrap <?= isset($errors['baru']) ? 'invalid' : '' ?>">
            <i class="bi bi-lock"></i>
            <input type="password" id="password_baru" name="password_baru" placeholder="Minimal 8 karakter" autocomplete="new-password">
            <button type="button" class="eye" data-target="password_baru" aria-label="Tampilkan kata sandi"><i class="bi bi-eye"></i></button>
          </div>
          <div class="strength" id="strength"><i></i><i></i><i></i><i></i></div>
          <span class="strength-text" id="strengthText">Kekuatan kata sandi</span>
          <?php if (isset($errors['baru'])): ?><span class="err"><?= h($errors['baru']) ?></span><?php endif; ?>
        </div>

        <div class="field">
          <label for="password_konfirmasi">ULANGI KATA SANDI BARU <em>*</em></label>
          <div class="input-wrap <?= isset($errors['konfirmasi']) ? 'invalid' : '' ?>">
            <i class="bi bi-lock-fill"></i>
            <input type="password" id="password_konfirmasi" name="password_konfirmasi" placeholder="Ketik ulang kata sandi baru" autocomplete="new-password">
            <button type="button" class="eye" data-target="password_konfirmasi" aria-label="Tampilkan kata sandi"><i class="bi bi-eye"></i></button>
          </div>
          <span class="match" id="matchText"></span>
          <?php if (isset($errors['konfirmasi'])): ?><span class="err"><?= h($errors['konfirmasi']) ?></span><?php endif; ?>
        </div>

      </div>

      <div class="form-actions">
        <a class="btn-x" href="dashboard.php"><i class="bi bi-x-lg"></i> Batal</a>
        <button class="btn-main" type="submit"><i class="bi bi-check2-circle"></i> Simpan Kata Sandi</button>
      </div>
    </form>
  </article>

  <aside class="emp-card">
    <h2 class="card-title"><span class="ico"><i class="bi bi-list-check"></i></span> Syarat Kata Sandi</h2>
    <p class="card-sub">Terpenuhi otomatis saat Anda mengetik.</p>
    <ul class="rules" id="rules">
      <li data-rule="len"><i class="bi bi-circle"></i> Minimal 8 karakter</li>
      <li data-rule="up"><i class="bi bi-circle"></i> Mengandung huruf besar (A–Z)</li>
      <li data-rule="low"><i class="bi bi-circle"></i> Mengandung huruf kecil (a–z)</li>
      <li data-rule="num"><i class="bi bi-circle"></i> Mengandung angka (0–9)</li>
    </ul>
    <div class="tips">
      <b><i class="bi bi-lightbulb"></i> Tips keamanan</b>
      Hindari tanggal lahir atau nama sendiri. Jangan memakai kata sandi yang sama dengan akun lain, dan jangan membagikannya kepada siapa pun, termasuk rekan kerja.
    </div>
  </aside>

</section>
</main>

<footer class="site-footer">
  <b>© 2026 Karyon HCM Enterprise. All rights reserved.</b><i></i><span>Sistem Informasi Manajemen Presensi &amp; Produktivitas</span>
</footer>

<script>
(function () {
  var baru = document.getElementById('password_baru');
  var konf = document.getElementById('password_konfirmasi');
  var bar  = document.getElementById('strength');
  var txt  = document.getElementById('strengthText');
  var mt   = document.getElementById('matchText');
  var rules = document.querySelectorAll('#rules li');
  var label = ['Kekuatan kata sandi', 'Lemah', 'Cukup', 'Baik', 'Kuat'];

  // Tampilkan / sembunyikan kata sandi
  document.querySelectorAll('.eye').forEach(function (b) {
    b.addEventListener('click', function () {
      var inp = document.getElementById(b.dataset.target);
      var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      b.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  });

  function cek() {
    var v = baru.value;
    var ok = {
      len: v.length >= 8,
      up: /[A-Z]/.test(v),
      low: /[a-z]/.test(v),
      num: /[0-9]/.test(v)
    };
    var skor = 0;
    rules.forEach(function (li) {
      var pass = ok[li.dataset.rule];
      li.classList.toggle('ok', pass);
      li.firstElementChild.className = pass ? 'bi bi-check-circle-fill' : 'bi bi-circle';
      if (pass) skor++;
    });
    if (v.length >= 12 && skor === 4) skor = 4;
    else if (skor === 4) skor = 3;
    bar.className = 'strength' + (v ? ' s' + Math.max(1, skor) : '');
    txt.textContent = v ? label[Math.max(1, skor)] : label[0];

    if (konf.value) {
      var cocok = konf.value === v;
      mt.textContent = cocok ? 'Kata sandi cocok' : 'Kata sandi belum cocok';
      mt.className = 'match ' + (cocok ? 'ok' : 'no');
    } else { mt.textContent = ''; mt.className = 'match'; }
  }
  baru.addEventListener('input', cek);
  konf.addEventListener('input', cek);
  cek();
})();
</script>
</body>
</html>