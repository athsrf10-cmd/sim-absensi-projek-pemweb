<?php
require_once '../auth/cek_session.php';
wajib_role('karyawan');

date_default_timezone_set('Asia/Jakarta');

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$karyawan_id = (int)$_SESSION['karyawan_id'];

// Token CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

// ---------- Data karyawan ----------
$stmt = $conn->prepare("
    SELECT k.*, j.nama_jabatan
    FROM karyawan k
    LEFT JOIN jabatan j ON k.jabatan_id = j.id
    WHERE k.id = ?
");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$profil = $stmt->get_result()->fetch_assoc() ?: [];

$nama_jabatan = $profil['nama_jabatan'] ?? 'Karyawan';
$nip          = $profil['nip'] ?? '-';

// Nilai form (diisi ulang kalau ada error)
$val = [
    'nama'   => $profil['nama']   ?? '',
    'no_hp'  => $profil['no_hp']  ?? '',
    'alamat' => $profil['alamat'] ?? '',
];
$errors = [];

// ---------- Proses simpan ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errors['umum'] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba lagi.';
    } else {
        $val['nama']   = trim($_POST['nama']   ?? '');
        $val['no_hp']  = trim($_POST['no_hp']  ?? '');
        $val['alamat'] = trim($_POST['alamat'] ?? '');

        if ($val['nama'] === '')                         $errors['nama'] = 'Nama lengkap wajib diisi.';
        elseif (mb_strlen($val['nama']) > 100)           $errors['nama'] = 'Nama maksimal 100 karakter.';

        // kolom no_hp di database: varchar(15)
        if ($val['no_hp'] === '')                        $errors['no_hp'] = 'Nomor handphone wajib diisi.';
        elseif (!preg_match('/^(?=.{8,15}$)\+?[0-9]+$/', $val['no_hp'])) $errors['no_hp'] = 'Gunakan 8–15 karakter angka, boleh diawali tanda +.';

        if (mb_strlen($val['alamat']) > 500)             $errors['alamat'] = 'Alamat maksimal 500 karakter.';

        if (!$errors) {
            $alamat_simpan = $val['alamat'] === '' ? null : $val['alamat'];
            $stmt = $conn->prepare("UPDATE karyawan SET nama = ?, no_hp = ?, alamat = ? WHERE id = ?");
            $stmt->bind_param("sssi", $val['nama'], $val['no_hp'], $alamat_simpan, $karyawan_id);
            $stmt->execute();

            if (isset($_SESSION['nama'])) $_SESSION['nama'] = $val['nama'];
            $_SESSION['flash_akun'] = ['tipe' => 'success', 'pesan' => 'Profil Anda berhasil diperbarui.'];
            header("Location: profil.php");
            exit;
        }
    }
}

// Flash message
$flash = $_SESSION['flash_akun'] ?? null;
unset($_SESSION['flash_akun']);

// ---------- Data tampilan ----------
$nama_tampil = $profil['nama'] ?? $_SESSION['username'] ?? 'Karyawan';
$inisial = strtoupper(
    substr($nama_tampil, 0, 1) .
    (strpos($nama_tampil, ' ') !== false ? substr($nama_tampil, strpos($nama_tampil, ' ') + 1, 1) : '')
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Profil - Karyon HCM Enterprise</title>
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


/* ===== KHUSUS HALAMAN EDIT PROFIL ===== */
.input-wrap.area{align-items:flex-start}
.input-wrap.area>.bi{top:13px}
.input-wrap textarea{width:100%;border:1px solid #e3e9f6;background:#f4f7fd;border-radius:9px;padding:11px 14px 11px 36px;font-family:inherit;font-size:11px;color:#1e293b;outline:0;resize:vertical;min-height:78px;line-height:1.6;transition:.15s}
.input-wrap textarea::placeholder{color:#94a3b8}
.input-wrap textarea:focus{background:#fff;border-color:#0048c8;box-shadow:0 0 0 3px rgba(0,72,200,.12)}
.input-wrap.invalid textarea{border-color:#ef4444;background:#fff8f8}
.account-layout{display:grid;grid-template-columns:320px 1fr;gap:18px;align-items:start}
.profile-hero{text-align:center}
.profile-hero .big-avatar{width:68px;height:68px;border-radius:50%;background:#0751bd;color:#fff;display:grid;place-items:center;font-size:22px;font-weight:700;margin:4px auto 12px;box-shadow:0 0 0 5px #e3ebfb}
.profile-hero b{display:block;font-size:14px;font-weight:700;color:#17243a}
.profile-hero small{display:block;font-size:10px;color:#64748b;margin:4px 0 12px}
.info-list{background:#f0f4ff;border-radius:10px;padding:8px 14px;margin-top:18px;text-align:left}
.info-list div{display:flex;justify-content:space-between;gap:10px;padding:9px 0;font-size:10px;border-bottom:1px solid #e1e8f8}
.info-list div:last-child{border-bottom:0}
.info-list span{color:#64748b}
.info-list b{color:#24364e;text-align:right;overflow-wrap:anywhere}
.side-link{display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;background:#eff4ff;border:1px solid #e7edf9;color:#24364e;padding:10px;border-radius:9px;font-size:10.5px;font-weight:600}
.side-link:hover{background:#e1eaff;color:#0751bd}
.section-label{grid-column:1/-1;font-size:9px;font-weight:700;letter-spacing:.6px;color:#526174;text-transform:uppercase;margin-top:6px;padding-top:16px;border-top:1px solid #edf0f8}
.section-label:first-child{margin-top:0;padding-top:0;border-top:0}
.lock-note{display:flex;gap:8px;align-items:flex-start;background:#f0f4ff;border-radius:9px;padding:10px 12px;font-size:9.5px;color:#475569;line-height:1.6;grid-column:1/-1}
.lock-note .bi{color:#0751bd;margin-top:2px}
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
      <span class="avatar"><?= h(strtoupper(mb_substr($nama_tampil, 0, 1))) ?></span>
      <div><b><?= h($nama_tampil) ?></b><small><?= h($nama_jabatan) ?> (NIP: <?= h($nip) ?>)</small></div>
    </div>
    <a class="logout" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i>Keluar</a>
  </div>
</nav>

<main class="main-wrap">

<header class="page-intro">
  <div>
    <h1>Edit Profil</h1>
    <p>Perbarui data diri dan informasi kontak Anda agar tetap akurat.</p>
  </div>
  <a class="blue-tag" href="dashboard.php"><i class="bi bi-arrow-left"></i> Kembali ke Dashboard</a>
</header>

<?php if ($flash): ?>
<div class="notice <?= h($flash['tipe']) ?>">
  <i class="bi <?= $flash['tipe'] === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill' ?>"></i>
  <?= h($flash['pesan']) ?>
  <button type="button" onclick="this.parentNode.remove()" aria-label="Tutup">×</button>
</div>
<?php endif; ?>
<?php if (!empty($errors['umum'])): ?>
<div class="notice danger"><i class="bi bi-exclamation-circle-fill"></i> <?= h($errors['umum']) ?></div>
<?php endif; ?>

<section class="account-layout">

  <!-- Ringkasan -->
  <aside class="emp-card profile-hero">
    <div class="big-avatar"><?= h($inisial) ?></div>
    <b><?= h($nama_tampil) ?></b>
    <small><?= h($nama_jabatan) ?></small>
    <span class="status-pill green">Karyawan Aktif</span>

    <div class="info-list">
      <div><span>NIP</span><b><?= h($nip) ?></b></div>
      <div><span>Jabatan</span><b><?= h($nama_jabatan) ?></b></div>
      <div><span>Status Kerja</span><b>Tetap (Full-time)</b></div>
    </div>

    <a class="side-link" href="ganti_password.php"><i class="bi bi-shield-lock"></i> Ganti Kata Sandi</a>
  </aside>

  <!-- Form -->
  <article class="emp-card">
    <h2 class="card-title"><span class="ico"><i class="bi bi-person-lines-fill"></i></span> Data Pribadi &amp; Kontak</h2>
    <p class="card-sub">Kolom bertanda <span style="color:#dc2626">*</span> wajib diisi.</p>

    <form method="POST" novalidate>
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">

      <div class="form-grid">

        <div class="field">
          <label for="nama">NAMA LENGKAP <em>*</em></label>
          <div class="input-wrap <?= isset($errors['nama']) ? 'invalid' : '' ?>">
            <i class="bi bi-person"></i>
            <input type="text" id="nama" name="nama" value="<?= h($val['nama']) ?>" maxlength="100" placeholder="Masukkan nama lengkap" autocomplete="name">
          </div>
          <?php if (isset($errors['nama'])): ?><span class="err"><?= h($errors['nama']) ?></span><?php endif; ?>
        </div>

        <div class="field">
          <label for="no_hp">NO. HANDPHONE <em>*</em></label>
          <div class="input-wrap <?= isset($errors['no_hp']) ? 'invalid' : '' ?>">
            <i class="bi bi-telephone"></i>
            <input type="tel" id="no_hp" name="no_hp" value="<?= h($val['no_hp']) ?>" maxlength="15" placeholder="081234567890" inputmode="tel" autocomplete="tel">
          </div>
          <?php if (isset($errors['no_hp'])): ?><span class="err"><?= h($errors['no_hp']) ?></span><?php else: ?><span class="hint">Contoh: 081234567890 (maks. 15 karakter)</span><?php endif; ?>
        </div>

        <div class="field full">
          <label for="alamat">ALAMAT</label>
          <div class="input-wrap area <?= isset($errors['alamat']) ? 'invalid' : '' ?>">
            <i class="bi bi-geo-alt"></i>
            <textarea id="alamat" name="alamat" rows="3" maxlength="500" placeholder="Masukkan alamat tempat tinggal" autocomplete="street-address"><?= h($val['alamat']) ?></textarea>
          </div>
          <?php if (isset($errors['alamat'])): ?><span class="err"><?= h($errors['alamat']) ?></span><?php else: ?><span class="hint">Opsional.</span><?php endif; ?>
        </div>

        <div class="section-label">Data Kepegawaian</div>

        <div class="field">
          <label for="nip">NIP</label>
          <div class="input-wrap"><i class="bi bi-lock"></i><input type="text" id="nip" value="<?= h($nip) ?>" readonly></div>
        </div>

        <div class="field">
          <label for="jabatan">JABATAN</label>
          <div class="input-wrap"><i class="bi bi-lock"></i><input type="text" id="jabatan" value="<?= h($nama_jabatan) ?>" readonly></div>
        </div>

        <div class="lock-note"><i class="bi bi-info-circle"></i><span>NIP dan jabatan dikelola oleh HR dan tidak dapat diubah dari halaman ini. Hubungi tim HR Ops bila ada data yang perlu dikoreksi.</span></div>
      </div>

      <div class="form-actions">
        <a class="btn-x" href="dashboard.php"><i class="bi bi-x-lg"></i> Batal</a>
        <button class="btn-main" type="submit"><i class="bi bi-check2-circle"></i> Simpan Perubahan</button>
      </div>
    </form>
  </article>

</section>
</main>

<footer class="site-footer">
  <b>© 2026 Karyon HCM Enterprise. All rights reserved.</b><i></i><span>Sistem Informasi Manajemen Presensi &amp; Produktivitas</span>
</footer>

</body>
</html>