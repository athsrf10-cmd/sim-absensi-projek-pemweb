<?php
require_once '../auth/cek_session.php';
wajib_role('karyawan');

date_default_timezone_set('Asia/Jakarta');

if (!function_exists('h')) {
    function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$karyawan_id = (int)$_SESSION['karyawan_id'];

$nama_hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$nama_bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

// ---------- Parameter filter ----------
$bulan = $_GET['bulan'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) $bulan = date('Y-m');
$cari          = trim($_GET['cari'] ?? '');
$status_filter = $_GET['status'] ?? '';
// Pilihan filter: status kehadiran + status dari admin (Izin/Sakit/Alpha, dan Cuti kalau sudah ada di DAFTAR_STATUS)
$daftar_filter = array_merge(['Hadir', 'Tepat Waktu', 'Terlambat'], array_values(array_diff(DAFTAR_STATUS, ['Hadir'])));
if (!in_array($status_filter, array_merge([''], $daftar_filter), true)) $status_filter = '';

$awal  = $bulan . '-01';
$akhir = date('Y-m-t', strtotime($awal));
$label_bulan = $nama_bulan[(int)substr($bulan, 5, 2)] . ' ' . substr($bulan, 0, 4);

// ---------- Ambil data bulan ini ----------
$stmt = $conn->prepare("SELECT * FROM absensi WHERE karyawan_id = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal DESC");
$stmt->bind_param('iss', $karyawan_id, $awal, $akhir);
$stmt->execute();
$res = $stmt->get_result();

$semua = [];
$sekarang = time();
$hari_ini = date('Y-m-d');
while ($r = $res->fetch_assoc()) {
    $ts_masuk  = $r['jam_masuk']  ? strtotime($r['tanggal'] . ' ' . $r['jam_masuk'])  : null;
    $ts_pulang = $r['jam_pulang'] ? strtotime($r['tanggal'] . ' ' . $r['jam_pulang']) : null;
    // terlambat() dari includes/fungsi.php: aturan sama dengan sisi admin (jam masuk + toleransi)
    $r['terlambat'] = $ts_masuk && terlambat($r['jam_masuk']);
    $r['durasi'] = null;
    if ($ts_masuk && $ts_pulang)                      $r['durasi'] = max(0, $ts_pulang - $ts_masuk);
    elseif ($ts_masuk && $r['tanggal'] === $hari_ini) $r['durasi'] = max(0, $sekarang - $ts_masuk);
    $r['lembur'] = $ts_pulang ? max(0, $ts_pulang - strtotime($r['tanggal'] . ' ' . JAM_PULANG)) : 0;
    // Izin/Sakit/Alpha/Cuti dari admin ditampilkan apa adanya, bukan dianggap "Hadir"
    $r['status_label'] = $r['status'] !== 'Hadir' ? $r['status']
        : (!$ts_pulang ? 'Hadir' : ($r['terlambat'] ? 'Terlambat' : 'Tepat Waktu'));
    $r['hari_nama'] = $nama_hari[(int)date('w', strtotime($r['tanggal']))];
    $semua[] = $r;
}

// ---------- Statistik ----------
// Hanya baris berstatus Hadir yang dihitung sebagai kehadiran (sebelumnya semua baris ikut terhitung)
$total_hadir = count(array_filter($semua, function ($r) { return $r['status'] === 'Hadir'; }));
$hari_kerja = 0;
for ($t = strtotime($awal); $t <= strtotime($akhir); $t += 86400) {
    if ((int)date('N', $t) <= 5) $hari_kerja++;
}
$persen = $hari_kerja ? min(100, (int)round($total_hadir / $hari_kerja * 100)) : 0;
$detik_kerja = 0; $detik_lembur = 0; $terlambat = 0;
foreach ($semua as $r) {
    if ($r['jam_pulang']) $detik_kerja += (int)$r['durasi'];
    $detik_lembur += $r['lembur'];
    if ($r['terlambat']) $terlambat++;
}
$jam_kerja = number_format($detik_kerja / 3600, 1, '.', '');
$jam_lembur = number_format($detik_lembur / 3600, 1, '.', '');

// ---------- Filter pencarian & status ----------
$rows = array_values(array_filter($semua, function ($r) use ($cari, $status_filter) {
    if ($status_filter !== '' && $r['status_label'] !== $status_filter) return false;
    if ($cari !== '') {
        $hay = mb_strtolower($r['tanggal'] . ' ' . date('d-m-Y', strtotime($r['tanggal'])) . ' ' . $r['hari_nama'] . ' ' . $r['status_label'] . ' ' . ($r['status'] ?? ''));
        if (mb_strpos($hay, mb_strtolower($cari)) === false) return false;
    }
    return true;
}));

function fmt_durasi($d) { return $d === null ? '—' : sprintf('%02dj %02dm', intdiv($d, 3600), intdiv($d % 3600, 60)); }

// ---------- Ekspor Excel (CSV) ----------
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="riwayat-kehadiran-' . $bulan . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['No', 'Tanggal', 'Hari', 'Jam Masuk', 'Jam Pulang', 'Durasi Kerja', 'Status']);
    foreach ($rows as $i => $r) {
        fputcsv($out, [$i + 1, date('d-m-Y', strtotime($r['tanggal'])), $r['hari_nama'], $r['jam_masuk'] ?? '', $r['jam_pulang'] ?? '', fmt_durasi($r['durasi']), $r['status_label']]);
    }
    fclose($out);
    exit;
}

// ---------- Pagination ----------
$per_page = 4;
$total = count($rows);
$pages = max(1, (int)ceil($total / $per_page));
$page  = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $per_page;
$tampil = array_slice($rows, $offset, $per_page);
$dari = $total ? $offset + 1 : 0;
$sampai = $offset + count($tampil);

$base_q = ['bulan' => $bulan, 'cari' => $cari, 'status' => $status_filter];
function link_hal($base_q, $p) { return '?' . http_build_query($base_q + ['page' => $p]); }
$url_excel = '?' . http_build_query($base_q + ['export' => 'csv']);
$p_awal = max(1, min($page - 2, $pages - 4));
$p_akhir = min($pages, $p_awal + 4);

// ---------- Pilihan bulan ----------
$opsi_bulan = [];
for ($i = 0; $i < 12; $i++) {
    $ts = mktime(0, 0, 0, (int)date('n') - $i, 1, (int)date('Y'));
    $opsi_bulan[date('Y-m', $ts)] = $nama_bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}
if (!isset($opsi_bulan[$bulan])) $opsi_bulan[$bulan] = $label_bulan;

// ---------- Data pengguna untuk navbar ----------
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Riwayat &amp; Log Kehadiran - Karyon HCM Enterprise</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{min-height:100vh;display:flex;flex-direction:column;background:#f6f8fe;color:#0f172a;
  font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;font-size:12px;-webkit-font-smoothing:antialiased}
a{text-decoration:none}

/* ===== NAVBAR ===== */
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

/* ===== KONTEN ===== */
.main-wrap{width:100%;max-width:1400px;margin:0 auto;padding:22px 32px 50px;flex:1}

.page-intro{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:19px}
.page-intro h1{font-size:25px;font-weight:800;letter-spacing:-.7px;line-height:1.2;margin:0 0 4px;color:#0f172a}
.page-intro p{margin:0;font-size:10.5px;color:#64748b}
.intro-tags{display:flex;gap:8px;align-items:center}
.green-tag,.blue-tag{display:inline-flex;align-items:center;gap:6px;height:26px;padding:0 12px;border-radius:8px;font-size:9px;font-weight:700;white-space:nowrap}
.green-tag{background:#eef1fa;color:#0f6b3a;letter-spacing:.3px}
.green-tag .bi{font-size:6px}
.blue-tag{background:#fff;color:#1d4ed8;font-size:10px;height:28px;padding:0 16px;box-shadow:0 1px 4px rgba(15,23,42,.08)}

/* ===== STATISTIK ===== */
.history-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:13px;margin-bottom:26px}
.history-stats article{background:#fff;border-radius:12px;height:121px;padding:17px 15px 15px 16px;position:relative;display:flex;flex-direction:column;box-shadow:0 3px 16px rgba(30,64,175,.05)}
.history-stats small{font-size:8.5px;font-weight:700;letter-spacing:.6px;color:#475569;text-transform:uppercase}
.stat-value{display:flex;align-items:baseline;gap:5px;margin-top:9px}
.stat-value b{font-size:25px;font-weight:800;letter-spacing:-.5px;line-height:1.1}
.stat-value span{font-size:11px;color:#475569}
.stat-icon{position:absolute;top:17px;right:15px;width:32px;height:32px;border-radius:8px;background:#dfe8fb;color:#1d4ed8;display:grid;place-items:center;font-size:13px}
.stat-foot{margin-top:auto;display:flex;justify-content:space-between;align-items:center;font-size:8.5px;color:#334155}
.pill-green,.pill-blue{display:inline-flex;align-items:center;gap:5px;height:17px;padding:0 8px;border-radius:999px;font-size:8.5px;font-weight:700}
.pill-green{background:#6ee7b7;color:#065f46}
.pill-blue{background:#dde7fb;color:#1d4ed8}
.txt-green{color:#0f7a45;font-weight:600}
.txt-blue{color:#1d4ed8;font-weight:700}
.txt-green .bi{margin-right:4px}

/* ===== FILTER ===== */
.history-tools{background:#fff;border-radius:12px;padding:12px;margin-bottom:19px;box-shadow:0 3px 16px rgba(30,64,175,.05)}
.history-tools form{display:flex;gap:11px;align-items:center}
.ctl{position:relative;display:flex;align-items:center;height:34px;border-radius:8px;background:#eef2fb;color:#334155;font-size:10px}
.ctl>.bi{position:absolute;left:12px;font-size:11px;color:#475569;pointer-events:none}
.ctl .chev{left:auto;right:10px;font-size:8px}
.ctl input,.ctl select{width:100%;height:100%;border:0;background:transparent;font-family:inherit;font-size:10px;color:#334155;outline:0;padding:0 26px 0 34px;appearance:none;-webkit-appearance:none;border-radius:8px}
.ctl select{font-weight:500;cursor:pointer}
.ctl input::placeholder{color:#64748b}
.ctl.search{flex:1 1 292px}
.ctl.month{flex:0 0 151px}
.ctl.stat{flex:0 0 177px}
.btn-x,.btn-main{height:34px;border:0;border-radius:8px;padding:0 15px;font-family:inherit;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:8px;cursor:pointer;white-space:nowrap}
.btn-x{background:#d9e4fa;color:#1d4ed8}
.btn-main{background:#0048c8;color:#fff}
.btn-x:hover{filter:brightness(.97)}.btn-main:hover{filter:brightness(1.1)}

/* ===== TABEL ===== */
.history-table-card{background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 3px 16px rgba(30,64,175,.05)}
.emp-card-head{display:flex;justify-content:space-between;align-items:center;height:45px;padding:0 19px}
.emp-card-head b{font-size:11.5px;font-weight:700;display:flex;align-items:center;gap:9px}
.blue-dot{font-size:8px;color:#0048c8}
.history-worktime{font-size:8.5px;color:#334155;display:flex;align-items:center;gap:6px}
.history-worktime .bi{font-size:5px;color:#0f6b3a}
.history-table{width:100%;border-collapse:collapse;table-layout:fixed}
.history-table th{background:#f1f5fd;height:34px;padding:0 19px;font-size:8.5px;font-weight:700;letter-spacing:.5px;color:#475569;text-align:left;text-transform:uppercase}
.history-table td{height:44px;padding:0 19px;font-size:10px;color:#1e293b;border-bottom:1px solid #f1f4fa;white-space:nowrap}
.history-table tbody tr:last-child td{border-bottom:0}
.history-table td:nth-child(2){font-weight:600}
.history-table th:last-child,.history-table td:last-child{text-align:center}
.day-pill{display:inline-block;background:#dfe6f8;color:#475569;font-size:8.5px;font-weight:500;padding:3px 8px;border-radius:4px}
.time-cell .bi{margin-right:7px;font-size:11px}
.time-cell.in .bi{color:#0f7a45}
.time-cell.out .bi{color:#334155}
.still{display:inline-block;background:#eef1f9;color:#64748b;font-style:italic;font-size:9.5px;padding:3px 8px;border-radius:4px}
.activity-badge{display:inline-flex;align-items:center;gap:5px;height:20px;padding:0 10px;border-radius:999px;font-size:8.5px;font-weight:700;background:#6ee7b7;color:#065f46}
.activity-badge.late{background:#fde68a;color:#92400e}
.activity-badge.st-izin{background:#dfe9ff;color:#1d4ed8}
.activity-badge.st-sakit{background:#fdf0d5;color:#9a6700}
.activity-badge.st-alpha{background:#fde2e2;color:#b42318}
.activity-badge.st-cuti{background:#ede4ff;color:#6d28d9}
.activity-badge .dot{font-size:5px}
.empty-state{text-align:center!important;color:#64748b;height:90px!important}

.history-foot{display:flex;justify-content:space-between;align-items:center;height:50px;padding:0 19px;font-size:9px;color:#475569}
.history-foot b{color:#0f172a;font-weight:700}
.history-pager{display:flex;align-items:center;gap:6px}
.history-pager a,.history-pager span{width:24px;height:24px;border-radius:6px;display:grid;place-items:center;font-size:10px;font-weight:500;color:#334155}
.history-pager a:hover{background:#eef2fb}
.history-pager .on{background:#0048c8;color:#fff;font-weight:700}
.history-pager .off{color:#b6bfd3;pointer-events:none}
.history-pager .bi{font-size:9px}

/* ===== FOOTER ===== */
.site-footer{background:#edf1fc;height:50px;padding:0 32px;display:flex;align-items:center;font-size:10px;color:#475569}
.site-footer b{font-weight:500;color:#1e293b}
.site-footer i{display:inline-block;width:4px;height:4px;border-radius:50%;background:#cbd5e1;margin:0 8px}

@media(max-width:1000px){
  .history-stats{grid-template-columns:repeat(2,1fr)}
  .history-tools form{flex-wrap:wrap}
  .history-table-card{overflow-x:auto}
  .history-table{min-width:760px}
}
@media(max-width:700px){
  .topbar{padding:0 16px}.user div{display:none}
  .main-wrap{padding:20px 16px 40px}
  .history-stats{grid-template-columns:1fr}
}
@media print{
  .topbar,.history-tools,.site-footer,.history-pager,.intro-tags{display:none!important}
  body{background:#fff}.main-wrap{padding:0}
}
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
    <a href="riwayat.php" class="active">Riwayat</a>
    <a href="pengajuan.php">Pengajuan Izin &amp; Cuti</a>
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

<header class="page-intro">
  <div>
    <h1>Riwayat &amp; Log Kehadiran</h1>
    <p>Catatan rekap kehadiran kerja Anda yang tercatat otomatis oleh sistem.</p>
  </div>
  <div class="intro-tags">
    <span class="green-tag"><i class="bi bi-circle-fill"></i> DATA TERKINI</span>
    <a class="blue-tag" href="riwayat.php"><i class="bi bi-arrow-repeat"></i> Segarkan Data</a>
  </div>
</header>

<section class="history-stats">
  <article>
    <small>Total Kehadiran</small>
    <span class="stat-icon"><i class="bi bi-calendar3"></i></span>
    <div class="stat-value"><b><?= $total_hadir ?></b><span>/ <?= $hari_kerja ?> Hari</span></div>
    <div class="stat-foot"><span class="pill-green"><?= $persen ?>% Tingkat Kehadiran</span><span><?= $bulan === date('Y-m') ? 'Bulan Ini' : h($label_bulan) ?></span></div>
  </article>
  <article>
    <small>Total Jam Kerja</small>
    <span class="stat-icon"><i class="bi bi-clock"></i></span>
    <div class="stat-value"><b><?= h($jam_kerja) ?></b><span>Jam</span></div>
    <div class="stat-foot"><span class="txt-green"><i class="bi bi-graph-up-arrow"></i>+<?= h($jam_lembur) ?> jam lembur tercatat</span></div>
  </article>
  <article>
    <small>Total Keterlambatan</small>
    <span class="stat-icon"><i class="bi bi-alarm"></i></span>
    <div class="stat-value"><b><?= $terlambat ?></b><span>Hari</span></div>
    <div class="stat-foot">
      <?php if ($terlambat === 0): ?>
        <span class="pill-blue"><i class="bi bi-check-circle"></i> Kehadiran Tepat Waktu</span><span class="txt-green">Sempurna</span>
      <?php else: ?>
        <span class="pill-blue"><i class="bi bi-exclamation-circle"></i> Perlu Diperhatikan</span><span></span>
      <?php endif; ?>
    </div>
  </article>
  <article>
    <small>Sisa Cuti Tahunan</small>
    <span class="stat-icon"><i class="bi bi-umbrella"></i></span>
    <div class="stat-value"><b>&mdash;</b><span>Hari</span></div>
    <div class="stat-foot"><span>Data cuti belum tersedia pada sistem</span></div>
  </article>
</section>

<section class="history-tools">
  <form method="GET" id="formFilter">
    <label class="ctl search"><i class="bi bi-search"></i><input name="cari" value="<?= h($cari) ?>" placeholder="Cari berdasarkan tanggal atau keterangan..."></label>
    <label class="ctl month"><i class="bi bi-calendar3"></i>
      <select name="bulan" onchange="this.form.submit()">
        <?php foreach ($opsi_bulan as $v => $l): ?><option value="<?= h($v) ?>" <?= $v === $bulan ? 'selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?>
      </select><i class="bi bi-chevron-down chev"></i>
    </label>
    <label class="ctl stat"><i class="bi bi-list-ul"></i>
      <select name="status" onchange="this.form.submit()">
        <option value="">Semua Status Kehadiran</option>
        <?php foreach ($daftar_filter as $s): ?><option value="<?= h($s) ?>" <?= $s === $status_filter ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?>
      </select><i class="bi bi-chevron-down chev"></i>
    </label>
    <a class="btn-x" href="<?= h($url_excel) ?>"><i class="bi bi-table"></i> Ekspor ke Excel</a>
    <button class="btn-main" type="button" onclick="window.print()"><i class="bi bi-printer-fill"></i> Unduh Rekap (PDF)</button>
  </form>
</section>

<section class="history-table-card">
  <div class="emp-card-head">
    <b><i class="bi bi-circle-fill blue-dot"></i> Daftar Kehadiran Harian</b>
    <span class="history-worktime"><i class="bi bi-circle-fill"></i> Jam Kerja : <?= jam_kerja_teks() ?></span>
  </div>
  <table class="history-table">
    <colgroup><col style="width:4.8%"><col style="width:13.1%"><col style="width:11.2%"><col style="width:13.7%"><col style="width:22.3%"><col style="width:14%"><col style="width:20.9%"></colgroup>
    <thead><tr><th>No</th><th>Tanggal</th><th>Hari</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Durasi Kerja</th><th>Status Kehadiran</th></tr></thead>
    <tbody>
    <?php if ($tampil): $no = $offset + 1; foreach ($tampil as $r): ?>
      <tr>
        <td><?= $no++ ?></td>
        <td><?= date('d-m-Y', strtotime($r['tanggal'])) ?></td>
        <td><span class="day-pill"><?= h($r['hari_nama']) ?></span></td>
        <td><span class="time-cell in"><i class="bi bi-box-arrow-in-right"></i><?= h($r['jam_masuk'] ?? '—') ?></span></td>
        <td>
          <?php if ($r['jam_pulang']): ?>
            <span class="time-cell out"><i class="bi bi-box-arrow-left"></i><?= h($r['jam_pulang']) ?></span>
          <?php elseif ($r['status'] !== 'Hadir'): ?>
            <span class="still">—</span>
          <?php else: ?>
            <span class="still">--:-- (Masih Bekerja)</span>
          <?php endif; ?>
        </td>
        <td><b><?= fmt_durasi($r['durasi']) ?></b></td>
        <td>
          <?php if ($r['status'] !== 'Hadir'): ?>
            <!-- Status dari admin (Izin/Sakit/Alpha/Cuti) -->
            <span class="activity-badge st-<?= strtolower($r['status']) ?>"><?= h($r['status']) ?></span>
          <?php elseif ($r['status_label'] === 'Hadir'): ?>
            <span class="activity-badge"><i class="bi bi-circle-fill dot"></i> Hadir</span>
          <?php elseif ($r['status_label'] === 'Tepat Waktu'): ?>
            <span class="activity-badge"><i class="bi bi-check-circle"></i> Tepat Waktu</span>
          <?php else: ?>
            <span class="activity-badge late"><i class="bi bi-exclamation-circle"></i> Terlambat</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; else: ?>
      <tr><td colspan="7" class="empty-state">Belum ada data kehadiran untuk periode ini.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  <div class="history-foot">
    <span>Menampilkan <b><?= $dari ?> - <?= $sampai ?></b> dari <b><?= $total ?></b> rekaman</span>
    <nav class="history-pager">
      <a class="<?= $page <= 1 ? 'off' : '' ?>" href="<?= h(link_hal($base_q, 1)) ?>"><i class="bi bi-chevron-double-left"></i></a>
      <a class="<?= $page <= 1 ? 'off' : '' ?>" href="<?= h(link_hal($base_q, max(1, $page - 1))) ?>"><i class="bi bi-chevron-left"></i></a>
      <?php for ($p = $p_awal; $p <= $p_akhir; $p++): ?>
        <a class="<?= $p === $page ? 'on' : '' ?>" href="<?= h(link_hal($base_q, $p)) ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a class="<?= $page >= $pages ? 'off' : '' ?>" href="<?= h(link_hal($base_q, min($pages, $page + 1))) ?>"><i class="bi bi-chevron-right"></i></a>
      <a class="<?= $page >= $pages ? 'off' : '' ?>" href="<?= h(link_hal($base_q, $pages)) ?>"><i class="bi bi-chevron-double-right"></i></a>
    </nav>
  </div>
</section>

</main>

<footer class="site-footer">
  <b>© 2026 Karyon HCM Enterprise. All rights reserved.</b><i></i><span>Sistem Informasi Manajemen &amp; Absensi</span>
</footer>

</body>
</html>