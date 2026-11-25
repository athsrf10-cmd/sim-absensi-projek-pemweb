<?php
// Hanya role karyawan yang boleh membuka halaman ini
require_once '../auth/cek_session.php';
wajib_role('karyawan');

date_default_timezone_set('Asia/Jakarta');

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// id karyawan diambil dari session (disimpan saat login), bukan dari form,
// supaya karyawan tidak bisa mengabsenkan orang lain
$karyawan_id = (int)$_SESSION['karyawan_id'];

// Cek apakah hari ini sudah ada data absensi untuk karyawan ini
$stmt = $conn->prepare("SELECT * FROM absensi WHERE karyawan_id = ? AND tanggal = CURDATE()");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$hari_ini = $stmt->get_result()->fetch_assoc();

// Proses saat tombol absen ditekan (form dikirim lewat POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'masuk' && !$hari_ini) {
        $stmt = $conn->prepare("INSERT INTO absensi (karyawan_id, tanggal, jam_masuk, status) VALUES (?, CURDATE(), CURTIME(), 'Hadir')");
        $stmt->bind_param("i", $karyawan_id);
        $stmt->execute();
        $_SESSION['pesan'] = 'Absen masuk berhasil dicatat.';
        $_SESSION['pesan_tipe'] = 'success';

    } elseif ($aksi === 'pulang' && $hari_ini && $hari_ini['status'] === 'Hadir' && $hari_ini['jam_pulang'] === null) {
        $stmt = $conn->prepare("UPDATE absensi SET jam_pulang = CURTIME() WHERE id = ?");
        $stmt->bind_param("i", $hari_ini['id']);
        $stmt->execute();
        $_SESSION['pesan'] = 'Absen pulang berhasil dicatat.';
        $_SESSION['pesan_tipe'] = 'success';

    } else {
        $_SESSION['pesan'] = 'Aksi tidak valid atau sudah dilakukan hari ini.';
        $_SESSION['pesan_tipe'] = 'warning';
    }

    // Redirect supaya halaman tidak mengirim ulang form saat di-refresh (pola PRG)
    header("Location: absen.php");
    exit;
}

// ---------- Data pengguna untuk navbar ----------
// Sesuaikan nama key session / kolom tabel dengan project Anda.
$nama    = $_SESSION['nama'] ?? $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'Karyawan';
$jabatan = $_SESSION['jabatan'] ?? 'Karyawan';
$nip     = $_SESSION['nip'] ?? $karyawan_id;
// Ambil nama, NIP, dan nama jabatan lewat JOIN (sama seperti dashboard.php).
// Kolom "jabatan" tidak ada di tabel karyawan (adanya jabatan_id), dan NIP tidak disimpan di session
$q = $conn->prepare("SELECT k.nama, k.nip, j.nama_jabatan
                     FROM karyawan k LEFT JOIN jabatan j ON k.jabatan_id = j.id
                     WHERE k.id = ?");
$q->bind_param("i", $karyawan_id);
$q->execute();
if ($k = $q->get_result()->fetch_assoc()) {
    $nama    = $k['nama'];
    $jabatan = $k['nama_jabatan'] ?? 'Karyawan';
    $nip     = $k['nip'];
}

// ---------- Data tampilan ----------
$hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tanggal_teks = $hari[(int)date('w')] . ', ' . date('d') . ' ' . $bulan[(int)date('n')] . ' ' . date('Y');

// Baris absensi hari ini bisa berupa Izin/Sakit/Alpha/Cuti yang dicatat admin (jam masuk kosong).
// Itu BUKAN absen masuk, jadi tombol absen tidak boleh ditampilkan
$bukan_hadir  = $hari_ini && $hari_ini['status'] !== 'Hadir';
$sudah_masuk  = $hari_ini && !$bukan_hadir;
$sudah_pulang = $sudah_masuk && $hari_ini['jam_pulang'] !== null;
// Pakai terlambat() dari includes/fungsi.php supaya aturannya SAMA dengan sisi admin
// (jam masuk + toleransi, diatur di config/koneksi.php)
$tepat_waktu  = $sudah_masuk && !terlambat($hari_ini['jam_masuk']);

$durasi = '00j 00m';
if ($sudah_masuk) {
    $akhir   = $sudah_pulang ? strtotime($hari_ini['tanggal'].' '.$hari_ini['jam_pulang']) : time();
    $selisih = max(0, $akhir - strtotime($hari_ini['tanggal'].' '.$hari_ini['jam_masuk']));
    $durasi  = sprintf('%02dj %02dm', intdiv($selisih, 3600), intdiv($selisih % 3600, 60));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Presensi Mandiri - Karyon HCM Enterprise</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{min-height:100vh;display:flex;flex-direction:column;background:#f6f8fe;color:#0f172a;
  font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;font-size:12px;-webkit-font-smoothing:antialiased}
a{text-decoration:none}

/* ===== NAVBAR ===== */
.topbar{background:#fff;height:50px;padding:0 32px;display:flex;align-items:center;justify-content:space-between;
  box-shadow:0 1px 0 #eaeef8}
.brand{display:flex;align-items:center;gap:9px;color:#0f172a}
.brand-logo{width:30px;height:30px;border-radius:8px;background:#1e56d6;color:#fff;display:grid;place-items:center;font-size:14px}
.brand b{display:block;font-size:13.5px;font-weight:700;line-height:1.1}
.brand small{display:block;font-size:7.5px;font-weight:700;letter-spacing:.7px;color:#1e56d6;line-height:1.2}
.nav-pill{display:flex;gap:2px;background:#eef2fb;padding:4px;border-radius:11px}
.nav-pill a{padding:7px 16px;border-radius:8px;font-size:11px;font-weight:500;color:#475569}
.nav-pill a.active{background:#fff;color:#1d4ed8;font-weight:700;box-shadow:0 1px 3px rgba(15,23,42,.1)}
.user-area{display:flex;align-items:center;gap:16px}
.user{display:flex;align-items:center;gap:9px}
.avatar{width:28px;height:28px;border-radius:50%;background:#1e56d6;color:#fff;font-size:12px;font-weight:700;display:grid;place-items:center}
.user b{display:block;font-size:10.5px;font-weight:700;line-height:1.25}
.user small{display:block;font-size:8.5px;color:#64748b;line-height:1.25}
.logout{font-size:10.5px;font-weight:500;color:#dc2626}
.logout .bi{margin-right:3px}

/* ===== KONTEN ===== */
.main-wrap{width:100%;max-width:1400px;margin:0 auto;padding:22px 32px 50px;flex:1}

.page-intro{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:32px}
.page-intro h1{font-size:29px;font-weight:800;letter-spacing:-.8px;line-height:1.15;margin:0 0 8px;color:#0f172a}
.page-intro p{margin:0;font-size:11.5px;line-height:1.65;color:#64748b}
.intro-tags{display:flex;gap:8px}
.green-tag,.blue-tag{display:inline-flex;align-items:center;gap:6px;height:25px;padding:0 12px;border-radius:999px;font-size:10px;font-weight:600;white-space:nowrap}
.green-tag{background:#fff;color:#0f6b3a;box-shadow:0 1px 3px rgba(15,23,42,.07)}
.green-tag .bi{font-size:7px;color:#0f6b3a}
.blue-tag{background:#e8eefb;color:#1d4ed8}

.attendance-layout{display:grid;grid-template-columns:1.43fr 1fr;gap:26px;align-items:stretch}
.attendance-main,.attendance-guide{background:#fff;border-radius:16px;padding:32px;box-shadow:0 4px 22px rgba(30,64,175,.05)}
.attendance-main{text-align:center;background:radial-gradient(circle at 100% 0,#f1f5ff 0,#fff 38%)}

/* status */
.attendance-status{display:flex;align-items:center;gap:10px;background:#f1f5fd;border-radius:10px;padding:0 14px;height:53px;text-align:left}
.activity-icon{width:26px;height:26px;border-radius:7px;background:#dce6fb;color:#1d4ed8;display:grid;place-items:center;font-size:12px;flex:none}
.attendance-status small{display:block;font-size:8.5px;font-weight:600;letter-spacing:.5px;color:#64748b;text-transform:uppercase;line-height:1.4}
.attendance-status b{display:block;font-size:10.5px;font-weight:700;color:#0f172a;line-height:1.4}
.activity-badge{margin-left:auto;font-size:9px;font-weight:700;height:20px;line-height:20px;padding:0 10px;border-radius:999px;white-space:nowrap}
.activity-badge.waiting{background:#dde2f4;color:#475569}
.activity-badge.done{background:#6ee7b7;color:#065f46}
.activity-badge.late{background:#fde68a;color:#92400e}

/* jam */
.server-time-label{display:inline-flex;align-items:center;gap:5px;margin-top:33px;height:19px;padding:0 10px;border-radius:999px;background:#e6eefc;color:#1d4ed8;font-size:9px;font-weight:600}
.attendance-clock{font-size:44px;line-height:1.15;font-weight:800;letter-spacing:-1px;margin-top:9px;color:#0f172a}
.attendance-clock small{font-size:13px;letter-spacing:0;font-weight:700;color:#1d4ed8;margin-left:3px}
.attendance-date{font-size:11.5px;color:#64748b;margin-top:2px}
.sync-note{font-size:9px;color:#475569;margin:38px 0 31px}
.sync-note.big{font-size:10.5px;color:#1e293b}

/* tombol */
.attendance-button{width:100%;height:45px;border:0;border-radius:10px;color:#fff;font-family:inherit;font-size:12.5px;font-weight:700;background:#0048c8;
  display:flex;align-items:center;justify-content:center;gap:9px;cursor:pointer;box-shadow:0 6px 14px rgba(0,72,200,.22)}
.attendance-button.pulang{background:#b91c1c;box-shadow:0 6px 14px rgba(185,28,28,.22)}
.attendance-button:hover{filter:brightness(1.08)}
.attendance-button .bi{font-size:16px}
.button-help{font-size:9px;color:#475569;margin:11px 0 28px}
.attendance-finished{background:#ecfdf5;color:#065f46;border-radius:10px;padding:16px;font-size:12px;line-height:1.7;font-weight:600;margin:0 0 28px}

/* tile bawah */
.attendance-bottom{display:grid;grid-template-columns:1fr 1fr;gap:13px;text-align:left}
.attendance-bottom>div{display:flex;align-items:center;gap:11px;background:#f1f5fd;border-radius:9px;padding:0 14px;height:48px}
.attendance-bottom>div>.bi{font-size:15px;color:#1d4ed8;width:20px;text-align:center}
.attendance-bottom .sync-ico{width:26px;height:26px;border-radius:50%;background:#6ee7b7;color:#047857;display:grid;place-items:center;font-size:12px;flex:none}
.attendance-bottom small{display:block;font-size:8.5px;color:#64748b;line-height:1.35}
.attendance-bottom b{display:block;font-size:10.5px;font-weight:700;color:#0f172a;line-height:1.35}
.attendance-bottom b.ok{color:#0f7a45}

/* panel kanan */
.attendance-guide h3{display:flex;align-items:center;gap:9px;font-size:13px;font-weight:700;margin:0;color:#0f172a}
.attendance-guide h3 .bi{width:28px;height:28px;border-radius:8px;background:#dce6fb;color:#1e40af;display:grid;place-items:center;font-size:13px;flex:none}
.attendance-guide .sub{font-size:8.5px;color:#64748b;margin:1px 0 18px 37px}
.guide-item{display:flex;gap:11px;background:#f1f5fd;border-radius:9px;padding:11px 14px;margin-bottom:8px}
.guide-item>.bi{color:#1d4ed8;font-size:13px;margin-top:1px;width:16px;flex:none}
.guide-item small{display:block;font-size:8.5px;color:#64748b;margin-bottom:1px}
.guide-item b{display:block;font-size:10.5px;font-weight:700;color:#0f172a;line-height:1.4}
.guide-item span{display:block;font-size:10px;line-height:1.6;color:#334155}
.guide-item b+span{font-size:8.5px;color:#64748b;line-height:1.5}
.guide-item .zona{color:#0f7a45;font-size:8.5px;font-weight:600}
.guide-item .zona::before{content:"";display:inline-block;width:4px;height:4px;border-radius:50%;background:#0f6b3a;margin-right:5px;vertical-align:1px}
.guide-foot{display:flex;justify-content:space-between;align-items:center;border-top:1px solid #edf0f8;margin-top:28px;padding-top:12px;font-size:8.5px}
.guide-foot span{color:#0f7a45;font-weight:600}
.guide-foot small{color:#475569;font-size:8.5px}

/* footer */
.site-footer{background:#edf1fc;height:50px;padding:0 32px;display:flex;align-items:center;font-size:10px;color:#475569}
.site-footer b{font-weight:500;color:#1e293b}
.site-footer i{display:inline-block;width:4px;height:4px;border-radius:50%;background:#cbd5e1;margin:0 8px}

.alert{font-size:12px;border-radius:10px;margin-bottom:20px}

@media(max-width:900px){
  .attendance-layout{grid-template-columns:1fr}
  .topbar{padding:0 16px}
  .user div{display:none}
  .main-wrap{padding:20px 16px 40px}
  .intro-tags{flex-wrap:wrap}
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav class="topbar">
  <a class="brand" href="dashboard.php">
    <span class="brand-logo"><i class="bi bi-buildings-fill"></i></span>
    <span><b>Karyon</b><small>HCM ENTERPRISE</small></span>
  </a>
  <div class="nav-pill">
    <a href="dashboard.php">Dashboard</a>
    <a href="absen.php" class="active">Absen</a>
    <a href="riwayat.php">Riwayat</a>
  </div>
  <div class="user-area">
    <div class="user">
      <span class="avatar"><?= h(strtoupper(mb_substr($nama, 0, 1))) ?></span>
      <div><b><?= h($nama) ?></b><small><?= h($jabatan) ?> (NIP: <?= h($nip) ?>)</small></div>
    </div>
    <a class="logout" href="../auth/logout.php"><i class="bi bi-box-arrow-right"></i>Keluar</a>
  </div>
</nav>

<main class="main-wrap">

<?php if (isset($_SESSION['pesan'])): ?>
<div class="alert alert-<?= h($_SESSION['pesan_tipe']) ?> alert-dismissible fade show">
  <?= h($_SESSION['pesan']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['pesan'], $_SESSION['pesan_tipe']); endif; ?>

<header class="page-intro">
  <div>
    <h1>Presensi Mandiri</h1>
    <p>Silakan lakukan pencatatan kehadiran harian. Waktu masuk dan pulang Anda tercatat otomatis<br>berdasarkan jam server aplikasi.</p>
  </div>
  <div class="intro-tags">
    <span class="green-tag"><i class="bi bi-circle-fill"></i> Sesi login aktif</span>
    <span class="blue-tag"><i class="bi bi-clock"></i> Jam Kerja: <?= jam_kerja_teks() ?></span>
  </div>
</header>

<?php if ($bukan_hadir): ?>
  <!-- Hari ini sudah dicatat admin sebagai Izin/Sakit/Alpha/Cuti, jadi tidak ada tombol absen -->
  <div class="alert alert-info">
    Hari ini tercatat <b><?= h($hari_ini['status']) ?></b> oleh admin, jadi Anda tidak perlu melakukan absen.
  </div>
<?php else: ?>
<section class="attendance-layout">
  <article class="attendance-main">

    <div class="attendance-status">
      <span class="activity-icon"><i class="bi bi-clock"></i></span>
      <div>
        <small><?= $sudah_masuk ? 'Status Presensi Masuk' : 'Status Presensi' ?></small>
        <b>
          <?php if (!$sudah_masuk): ?>Belum Melakukan Presensi Masuk<?php
          elseif (!$sudah_pulang): ?>Tercatat Masuk: <?= h($hari_ini['jam_masuk']) ?> WIB<?php
          else: ?>Presensi Hari Ini Selesai<?php endif; ?>
        </b>
      </div>
      <?php if (!$sudah_masuk): ?>
        <span class="activity-badge waiting">Menunggu Presensi</span>
      <?php elseif (!$sudah_pulang): ?>
        <span class="activity-badge <?= $tepat_waktu ? 'done' : 'late' ?>"><?= $tepat_waktu ? 'Tepat Waktu' : 'Terlambat' ?></span>
      <?php else: ?>
        <span class="activity-badge done">Selesai</span>
      <?php endif; ?>
    </div>

    <div class="server-time-label"><i class="bi bi-stopwatch"></i> Waktu Server Resmi (WIB)</div>
    <div class="attendance-clock" id="jam">--:--:-- <small>WIB</small></div>
    <div class="attendance-date"><?= h($tanggal_teks) ?></div>
    <?php if (!$sudah_masuk): ?>
      <p class="sync-note">Waktu mengikuti jam server aplikasi</p>
    <?php else: ?>
      <p class="sync-note big">Waktu Server Resmi (WIB)</p>
    <?php endif; ?>

    <?php if (!$sudah_masuk): ?>
      <form method="POST">
        <input type="hidden" name="aksi" value="masuk">
        <button class="attendance-button" type="submit"><i class="bi bi-box-arrow-in-right"></i> Absen Masuk Sekarang</button>
      </form>
      <p class="button-help">Klik tombol di atas untuk mencatat waktu kehadiran masuk kerja Anda hari ini.</p>
    <?php elseif (!$sudah_pulang): ?>
      <form method="POST">
        <input type="hidden" name="aksi" value="pulang">
        <button class="attendance-button pulang" type="submit"><i class="bi bi-box-arrow-right"></i> Absen Pulang Sekarang</button>
      </form>
      <p class="button-help">Klik tombol di atas untuk mencatat waktu kepulangan kerja Anda hari ini.</p>
    <?php else: ?>
      <div class="attendance-finished">
        <i class="bi bi-check-circle-fill"></i> Presensi hari ini telah selesai.<br>
        Masuk <?= h($hari_ini['jam_masuk']) ?> WIB • Pulang <?= h($hari_ini['jam_pulang']) ?> WIB
      </div>
    <?php endif; ?>

    <div class="attendance-bottom">
      <?php if (!$sudah_masuk): ?>
        <div><i class="bi bi-alarm"></i><span><small>Jadwal Masuk</small><b><?= substr(JAM_MASUK, 0, 5) ?> WIB (toleransi <?= TOLERANSI_MENIT ?> menit)</b></span></div>
      <?php else: ?>
        <div><i class="bi bi-circle-half"></i><span><small>Durasi Berjalan</small><b><?= h($durasi) ?></b></span></div>
      <?php endif; ?>
      <div>
        <span class="sync-ico"><i class="bi bi-cloud-check"></i></span>
        <!-- Teks mengikuti kondisi nyata: ada data absensi hari ini atau belum -->
        <span><small>Status Data</small><b class="<?= $sudah_masuk ? 'ok' : '' ?>"><?= $sudah_masuk ? 'Tersimpan di sistem' : 'Belum ada data hari ini' ?></b></span>
      </div>
    </div>
  </article>

  <aside class="attendance-guide">
    <h3><i class="bi bi-shield-check"></i> Informasi &amp; Panduan Kerja</h3>
    <div class="sub"><?= $sudah_masuk ? 'Ketentuan presensi dan ringkasan kerja hari ini' : 'Informasi jadwal, lokasi, dan ketentuan presensi kerja harian.' ?></div>

    <div class="guide-item"><i class="bi bi-clock"></i><div><small>Jadwal Kerja Aktif</small><b><?= jam_kerja_teks() ?></b><span>Senin - Jumat (Hari Kerja Reguler)</span></div></div>
    <!-- Toleransi diambil dari JAM_MASUK dan TOLERANSI_MENIT di config/koneksi.php -->
    <div class="guide-item"><i class="bi bi-hourglass-split"></i><div><small>Toleransi Keterlambatan</small><b><?= TOLERANSI_MENIT ?> Menit</b><span>Masuk setelah <?= date('H:i', strtotime(JAM_MASUK) + TOLERANSI_MENIT * 60) ?> WIB dihitung terlambat</span></div></div>
    <div class="guide-item"><i class="bi bi-info-circle"></i><div><small>Ketentuan Jam Pulang</small><span>Absen pulang dicatat saat tombol ditekan. Jangan lupa absen pulang agar durasi kerja Anda tercatat.</span></div></div>
    <div class="guide-item"><i class="bi bi-question-circle"></i><div><small>Bantuan &amp; Kendala</small><span>Jika mengalami kendala presensi, hubungi admin HRD.</span></div></div>
    
    <div class="guide-foot"><span><i class="bi bi-check-circle"></i> Status: <?= $sudah_masuk ? 'Presensi Hari Ini Tercatat' : 'Belum Ada Presensi Hari Ini' ?></span>
  </aside>
</section>
<?php endif; ?>

</main>

<footer class="site-footer">
  <b>© 2026 Karyon HCM Enterprise. All rights reserved.</b><i></i><span>Sistem Informasi Manajemen &amp; Absensi</span>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Jam mengikuti waktu server (selisih jam server dan jam perangkat), zona WIB
(function () {
  var offset = <?= time() * 1000 ?> - Date.now();
  function updateClock() {
    var el = document.getElementById('jam');
    if (!el) return;
    var t = new Date(Date.now() + offset).toLocaleTimeString('en-GB', {timeZone: 'Asia/Jakarta', hour12: false});
    el.innerHTML = t + ' <small>WIB</small>';
  }
  updateClock();
  setInterval(updateClock, 1000);
})();
</script>
</body>
</html>