<?php
require_once '../auth/cek_session.php';
wajib_role('karyawan');

date_default_timezone_set('Asia/Jakarta');

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$karyawan_id = (int)$_SESSION['karyawan_id'];

// ================= DATA KARYAWAN =================
$stmt = $conn->prepare("
    SELECT k.*, j.nama_jabatan
    FROM karyawan k
    LEFT JOIN jabatan j ON k.jabatan_id = j.id
    WHERE k.id = ?
");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$profil = $stmt->get_result()->fetch_assoc() ?: [];

// ================= PRESENSI HARI INI =================
$stmt = $conn->prepare("
    SELECT *
    FROM absensi
    WHERE karyawan_id = ? AND tanggal = CURDATE()
    LIMIT 1
");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$hari_ini = $stmt->get_result()->fetch_assoc();

// ================= TOTAL KEHADIRAN =================
$stmt = $conn->prepare("
    SELECT COUNT(*) AS n
    FROM absensi
    WHERE karyawan_id = ?
    AND status = 'Hadir'
    AND MONTH(tanggal) = MONTH(CURDATE())
    AND YEAR(tanggal) = YEAR(CURDATE())
");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$hadir = (int)$stmt->get_result()->fetch_assoc()['n'];

// ================= TOTAL JAM KERJA =================
$stmt = $conn->prepare("
    SELECT COALESCE(
        SUM(TIME_TO_SEC(TIMEDIFF(jam_pulang, jam_masuk))), 0
    ) AS total_detik
    FROM absensi
    WHERE karyawan_id = ?
    AND MONTH(tanggal) = MONTH(CURDATE())
    AND YEAR(tanggal) = YEAR(CURDATE())
    AND jam_pulang IS NOT NULL
");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$detik = (int)$stmt->get_result()->fetch_assoc()['total_detik'];

$total_jam = number_format($detik / 3600, 1);

// ================= AKTIVITAS TERBARU =================
$stmt = $conn->prepare("
    SELECT tanggal, jam_masuk, jam_pulang, status
    FROM absensi
    WHERE karyawan_id = ?
    ORDER BY tanggal DESC
    LIMIT 3
");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$terbaru = $stmt->get_result();

// ================= DATA TAMBAHAN =================
$nama = $profil['nama'] ?? $_SESSION['username'] ?? 'Karyawan';
$inisial = strtoupper(
    substr($nama, 0, 1) .
    (strpos($nama, ' ') !== false
        ? substr($nama, strpos($nama, ' ') + 1, 1)
        : '')
);

$nama_jabatan = $profil['nama_jabatan'] ?? 'Karyawan';
$nip = $profil['nip'] ?? '-';
$no_hp = $profil['no_hp'] ?? '-';

$target_hari = 22;
$persentase = min(100, round(($hadir / $target_hari) * 100));

$jam_pulang = $hari_ini['jam_pulang'] ?? null;

if (!$hari_ini) {
    $status_tombol = 'masuk';
    $teks_tombol = 'Presensi Masuk';
} elseif ($jam_pulang === null) {
    $status_tombol = 'pulang';
    $teks_tombol = 'Presensi Pulang';
} else {
    $status_tombol = 'selesai';
    $teks_tombol = 'Lihat Presensi';
}

// Hari dan bulan Indonesia
$hari_indonesia = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];

$bulan_indonesia = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

function tanggalIndonesia($tanggal, $hari_indonesia, $bulan_indonesia) {
    $waktu = strtotime($tanggal);
    return $hari_indonesia[date('l', $waktu)] . ', ' .
           date('d', $waktu) . ' ' .
           $bulan_indonesia[(int)date('n', $waktu)] . ' ' .
           date('Y', $waktu);
}

function tanggalSingkat($tanggal, $hari_indonesia, $bulan_indonesia) {
    $waktu = strtotime($tanggal);
    return $hari_indonesia[date('l', $waktu)] . ', ' .
           date('d', $waktu) . ' ' .
           substr($bulan_indonesia[(int)date('n', $waktu)], 0, 3) .
           ' ' . date('Y', $waktu);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard - Karyon HCM Enterprise</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{min-height:100vh;display:flex;flex-direction:column;background:#f6f8fe;color:#0f172a;
  font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;font-size:12px;-webkit-font-smoothing:antialiased}
a{text-decoration:none}

/* ===== NAVBAR (sama dengan absen & riwayat) ===== */
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

/* ===== AREA KONTEN (sama dengan absen & riwayat) ===== */
.main-wrap{width:100%;max-width:1400px;margin:0 auto;padding:22px 32px 50px;flex:1}

/* ===== FOOTER (sama dengan absen & riwayat) ===== */
.site-footer{background:#edf1fc;height:50px;padding:0 32px;display:flex;align-items:center;font-size:10px;color:#475569}
.site-footer b{font-weight:500;color:#1e293b}
.site-footer i{display:inline-block;width:4px;height:4px;border-radius:50%;background:#cbd5e1;margin:0 8px}

/* ========================================
   DASHBOARD KARYAWAN
======================================== */
.employee-dashboard{color:#17243a}
.employee-dashboard *{box-sizing:border-box}

/* WELCOME */
.emp-welcome{background:linear-gradient(110deg,#fff 65%,#f5f8ff);border:1px solid #edf0f7;border-radius:12px;padding:27px 28px;min-height:127px;display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:27px}
.emp-online{font-size:11px;color:#126c50;margin-bottom:12px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.emp-online i{font-size:7px}
.online-badge{background:#d6fae9;padding:5px 10px;border-radius:20px;font-weight:600}
.emp-welcome h1{color:#15243a;font-size:21px;font-weight:700;margin:0 0 8px}
.emp-welcome p{margin:0;font-size:12px;color:#64748b}
.emp-welcome p i{color:#1454b8;margin-right:5px}
.welcome-action{display:flex;align-items:center;gap:18px}
.welcome-right{text-align:right}
.welcome-clock{font-size:27px;font-weight:700;color:#14243b;white-space:nowrap;letter-spacing:.5px}
.welcome-clock small{font-size:15px;font-weight:500}
.welcome-status{font-size:10px;color:#64748b;margin-top:4px}
.welcome-status i{color:#07865e}
.emp-attendance-btn{border:0;border-radius:7px;color:#fff;padding:12px 18px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-width:125px;transition:.2s;white-space:nowrap}
.emp-attendance-btn:hover{color:#fff;transform:translateY(-2px)}
.btn-masuk{background:#0751bd}.btn-masuk:hover{background:#06429a}
.btn-pulang{background:#bd2029}.btn-pulang:hover{background:#a31922}
.btn-selesai{background:#64748b}

/* STATISTIK */
.emp-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:17px;margin-bottom:27px}
.emp-stat{background:#fff;border:1px solid #edf0f6;border-radius:11px;padding:17px 18px;min-height:89px;position:relative}
.emp-stat>small{color:#64748b;font-size:9px;font-weight:700;letter-spacing:.4px}
.emp-stat-main{display:flex;align-items:baseline;gap:7px;margin-top:6px}
.emp-stat-main strong{font-size:23px;line-height:1;color:#15243a}
.emp-stat-main span{color:#64748b;font-size:11px}
.emp-stat>p{font-size:9px;color:#426184;margin:5px 0 0}
.emp-stat>p.stat-green{color:#07865e}
.stat-icon{position:absolute;right:18px;top:25px;width:35px;height:35px;border-radius:10px;background:#eff4ff;color:#0751bd;display:flex;align-items:center;justify-content:center;font-size:17px}
.stat-circle{position:absolute;right:18px;top:23px;width:40px;height:40px;border-radius:50%;background:conic-gradient(#0751bd <?= $persentase ?>%,#e6edf8 <?= $persentase ?>%);display:flex;align-items:center;justify-content:center}
.stat-circle::before{content:"";position:absolute;width:31px;height:31px;background:#fff;border-radius:50%}
.stat-circle span{position:relative;z-index:1;font-size:9px;font-weight:700;color:#17243a}

/* KOLOM UTAMA */
.emp-columns{display:grid;grid-template-columns:32% 1fr;gap:18px;align-items:stretch}
.emp-card{background:#fff;border:1px solid #edf0f6;border-radius:11px;padding:19px;min-width:0}
.emp-card-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:18px}
.emp-card-head>b,.emp-card-head div>b{font-size:9px;color:#526174;letter-spacing:.5px}
.status-pill{background:#eff4ff;color:#1551a0;padding:5px 9px;border-radius:15px;font-size:9px;white-space:nowrap}
.profile-person{display:flex;align-items:center;gap:12px;margin-bottom:17px}
.profile-avatar{width:43px;height:43px;background:#0751bd;border-radius:50%;color:#fff;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;flex-shrink:0}
.profile-person b{font-size:12px;color:#17243a;display:block}
.profile-person small{font-size:10px;color:#64748b;display:block;margin-top:4px}
.profile-data{background:#f0f4ff;border-radius:8px;padding:13px 12px}
.profile-data>div{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:7px 0;font-size:9px}
.profile-data span{color:#64748b;flex-shrink:0}
.profile-data b{color:#24364e;text-align:right;overflow-wrap:anywhere}
.profile-data .blue-text{color:#0751bd}
.profile-buttons{display:flex;gap:7px;margin-top:16px}
.profile-buttons a{flex:1;text-align:center;background:#eff4ff;border:1px solid #e7edf9;color:#24364e;padding:9px 5px;border-radius:7px;font-size:10px;font-weight:600}
.profile-buttons a:hover{background:#e1eaff;color:#0751bd}

/* AKTIVITAS */
.activity-card{display:flex;flex-direction:column}
.activity-card .emp-card-head{border-bottom:1px solid #edf0f6;padding-bottom:13px;margin-bottom:12px}
.activity-card .emp-card-head p{margin:5px 0 0;font-size:12px;color:#17243a;font-weight:600}
.activity-card .emp-card-head a,.activity-foot a{font-size:10px;color:#0751bd;white-space:nowrap}
.activity-list{display:flex;flex-direction:column;gap:9px}
.activity-row{border:1px solid #edf0f6;border-radius:8px;padding:11px 12px;display:flex;align-items:center;gap:12px;min-height:55px}
.activity-row:first-child{background:#f0f4ff}
.activity-icon{width:32px;height:32px;background:#e4edff;color:#526174;border-radius:7px;display:flex;justify-content:center;align-items:center;flex-shrink:0;font-size:13px}
.activity-desc{flex:1;min-width:0}
.activity-desc b{display:block;font-size:10px;color:#26374c;margin-bottom:4px}
.activity-desc small{font-size:9px;color:#64748b;display:block;line-height:1.5}
.activity-badge{font-size:9px;border-radius:20px;padding:6px 10px;white-space:nowrap}
.activity-badge.waiting{color:#64748b;background:#e9eefc}
.activity-badge.done{color:#07865e;background:#d4fae9}
.activity-badge.working{color:#0751bd;background:#dce8ff}
.empty-state{color:#64748b;font-size:12px;text-align:center;padding:25px 10px}
.activity-foot{margin-top:auto;padding-top:15px;border-top:1px solid #edf0f6;display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:9px;color:#64748b}
.activity-foot i{color:#07865e;margin-right:4px}

/* RESPONSIVE */
@media(max-width:900px){.emp-columns{grid-template-columns:1fr}}
@media(max-width:700px){
  .topbar{padding:0 16px}.user div{display:none}
  .main-wrap{padding:20px 16px 40px}
}
@media(max-width:650px){
  .emp-welcome{align-items:flex-start;flex-direction:column;padding:20px}
  .welcome-action{width:100%;justify-content:space-between;flex-wrap:wrap}
  .welcome-clock{font-size:23px}
  .emp-stats{grid-template-columns:1fr;gap:10px}
  .emp-stat{min-height:90px}
  .activity-row{flex-wrap:wrap}
  .activity-badge{margin-left:44px}
  .activity-foot{flex-wrap:wrap}
}
@media(max-width:400px){
  .profile-buttons{flex-direction:column}
  .activity-card .emp-card-head{align-items:flex-start}
  .activity-card .emp-card-head a{font-size:9px}
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
    <a href="dashboard.php" class="active">Dashboard</a>
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
<div class="employee-dashboard">

    <!-- BAGIAN SAMBUTAN -->
    <section class="emp-welcome">
        <div>
            <div class="emp-online">
                <span class="online-badge"><i class="bi bi-circle-fill"></i> Sistem Terhubung • Waktu Real-Time</span>
                <span>• <?= tanggalIndonesia(date('Y-m-d'), $hari_indonesia, $bulan_indonesia) ?></span>
            </div>
            <h1>
                Selamat <?= (date('H') < 11 ? 'Pagi' : (date('H') < 15 ? 'Siang' : (date('H') < 18 ? 'Sore' : 'Malam'))) ?>,
                <?= h($nama) ?>
            </h1>
            <p><i class="bi bi-clock"></i> Jadwal Kerja: 08:00 – 17:00 WIB</p>
        </div>

        <div class="welcome-action">
            <div class="welcome-right">
                <div class="welcome-clock" id="jam-dashboard">--:--:-- <small>WIB</small></div>
                <div class="welcome-status">
                    <?php if ($hari_ini && $jam_pulang !== null): ?>
                        <i class="bi bi-check-circle-fill"></i> Presensi Selesai
                    <?php elseif ($hari_ini): ?>
                        <i class="bi bi-check-circle-fill"></i> Presensi Masuk: <?= h($hari_ini['jam_masuk']) ?> WIB
                    <?php else: ?>
                        <i class="bi bi-clock"></i> Status: Belum Presensi Masuk
                    <?php endif; ?>
                </div>
            </div>

            <a href="absen.php" class="emp-attendance-btn <?= $status_tombol === 'masuk' ? 'btn-masuk' : ($status_tombol === 'pulang' ? 'btn-pulang' : 'btn-selesai') ?>">
                <i class="bi <?= $status_tombol === 'masuk' ? 'bi-box-arrow-in-right' : ($status_tombol === 'pulang' ? 'bi-box-arrow-right' : 'bi-check-circle') ?>"></i>
                <?= $teks_tombol ?>
            </a>
        </div>
    </section>

    <!-- KARTU STATISTIK -->
    <section class="emp-stats">
        <article class="emp-stat">
            <small>KEHADIRAN BULAN INI</small>
            <div class="emp-stat-main"><strong><?= $hadir ?> / <?= $target_hari ?></strong><span>Hari</span></div>
            <p class="stat-green"><?= $hadir ?> dari <?= $target_hari ?> Hari Kerja (Tingkat Kehadiran <?= $persentase ?>%)</p>
            <div class="stat-circle"><span><?= $persentase ?>%</span></div>
        </article>

        <article class="emp-stat">
            <small>TOTAL JAM KERJA</small>
            <div class="emp-stat-main"><strong><?= $total_jam ?></strong><span>Jam</span></div>
            <p>+ Total Jam Kerja Terkumpul</p>
            <div class="stat-icon"><i class="bi bi-stopwatch"></i></div>
        </article>

        <article class="emp-stat">
            <small>SISA CUTI TAHUNAN</small>
            <div class="emp-stat-main"><strong>—</strong><span>Hari</span></div>
            <p>Data cuti belum tersedia pada sistem</p>
            <div class="stat-icon"><i class="bi bi-calendar2-check"></i></div>
        </article>
    </section>

    <!-- INFORMASI KARYAWAN DAN AKTIVITAS -->
    <section class="emp-columns">

        <article class="emp-card profile-card">
            <div class="emp-card-head">
                <b>INFORMASI KARYAWAN</b>
                <span class="status-pill">Karyawan Aktif</span>
            </div>

            <div class="profile-person">
                <div class="profile-avatar"><?= h($inisial) ?></div>
                <div>
                    <b><?= h($nama) ?></b>
                    <small><?= h($nama_jabatan) ?> • Departemen IT</small>
                </div>
            </div>

            <div class="profile-data">
                <div><span>NIP</span><b><?= h($nip) ?></b></div>
                <div><span>Nama Lengkap</span><b><?= h($nama) ?></b></div>
                <div><span>Jabatan</span><b><?= h($nama_jabatan) ?></b></div>
                <div><span>Alamat</span><b><?= h($profil['alamat'] ?? '-') ?></b></div>
                <div><span>No. Handphone</span><b><?= h($no_hp) ?></b></div>
                <div><span>Status Kerja</span><b class="blue-text">Tetap (Full-time)</b></div>
            </div>

            <div class="profile-buttons">
                <a href="profil.php"><i class="bi bi-pencil-square"></i> Edit Profil</a>
                <a href="ganti_password.php"><i class="bi bi-arrow-repeat"></i> Ganti Kata Sandi</a>
            </div>
        </article>

        <article class="emp-card activity-card">
            <div class="emp-card-head">
                <div>
                    <b>AKTIVITAS PRESENSI TERKINI</b>
                    <p>Ringkasan Presensi Terakhir</p>
                </div>
                <a href="riwayat.php">Lihat Riwayat Lengkap <i class="bi bi-arrow-right"></i></a>
            </div>

            <div class="activity-list">
                <?php if ($terbaru->num_rows > 0): ?>
                    <?php while ($r = $terbaru->fetch_assoc()): ?>
                        <?php
                        $tanggal_absen = date('Y-m-d', strtotime($r['tanggal']));
                        $is_today = ($tanggal_absen === date('Y-m-d'));

                        if ($r['jam_pulang'] !== null) {
                            $badge_text = 'Hadir Tepat Waktu';
                            $badge_class = 'done';
                        } elseif ($is_today) {
                            $badge_text = 'Aktif Bekerja';
                            $badge_class = 'working';
                        } else {
                            $badge_text = 'Menunggu Presensi';
                            $badge_class = 'waiting';
                        }
                        ?>
                        <div class="activity-row">
                            <span class="activity-icon"><i class="bi bi-calendar-check"></i></span>
                            <div class="activity-desc">
                                <b>
                                    <?= $is_today ? 'Hari Ini' : tanggalSingkat($r['tanggal'], $hari_indonesia, $bulan_indonesia) ?>
                                    <?= $is_today ? ' • ' . tanggalSingkat($r['tanggal'], $hari_indonesia, $bulan_indonesia) : '' ?>
                                </b>
                                <small>
                                    Jam Masuk: <?= h($r['jam_masuk'] ?? '--:--:--') ?> WIB
                                    &nbsp; • &nbsp;
                                    <?php if ($r['jam_pulang'] !== null): ?>
                                        Jam Pulang: <?= h($r['jam_pulang']) ?> WIB
                                    <?php else: ?>
                                        Status: <?= $is_today ? 'Sedang Bertugas' : 'Belum Presensi Pulang' ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <span class="activity-badge <?= $badge_class ?>"><?= $badge_text ?></span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="empty-state">Belum ada aktivitas presensi.</p>
                <?php endif; ?>
            </div>

            <div class="activity-foot">
                <span><i class="bi bi-shield-check"></i> Data presensi tercatat otomatis dan tersinkronisasi secara real-time</span>
                <a href="riwayat.php">Lihat Rekap Bulanan</a>
            </div>
        </article>

    </section>
</div>
</main>

<!-- FOOTER -->
<footer class="site-footer">
  <b>© 2026 Karyon HCM Enterprise. All rights reserved.</b><i></i><span>Sistem Informasi Manajemen &amp; Absensi</span>
</footer>

<!-- JAM DIGITAL (mengikuti waktu server, WIB) -->
<script>
(function () {
  var offset = <?= time() * 1000 ?> - Date.now();
  function updateDashboardClock() {
    var el = document.getElementById('jam-dashboard');
    if (!el) return;
    var t = new Date(Date.now() + offset).toLocaleTimeString('en-GB', {timeZone: 'Asia/Jakarta', hour12: false});
    el.innerHTML = t + ' <small>WIB</small>';
  }
  updateDashboardClock();
  setInterval(updateDashboardClock, 1000);
})();
</script>
</body>
</html>