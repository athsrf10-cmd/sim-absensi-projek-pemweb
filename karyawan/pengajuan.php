<?php
require_once '../auth/cek_session.php';
wajib_role('karyawan');

date_default_timezone_set('Asia/Jakarta');

$karyawan_id = (int)$_SESSION['karyawan_id'];
$hari_ini    = date('Y-m-d');

// Token CSRF (sama seperti di profil.php)
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

// Cek format tanggal YYYY-MM-DD yang benar-benar ada di kalender (mis. 2026-02-30 ditolak)
function tanggal_valid($t) {
    $d = DateTime::createFromFormat('Y-m-d', $t);
    return $d && $d->format('Y-m-d') === $t;
}

// Nilai form (diisi ulang kalau ada error)
$form = ['jenis' => 'Izin', 'mulai' => $hari_ini, 'selesai' => $hari_ini, 'alasan' => ''];

// ===== Proses form (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    $error = '';

    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $error = 'Sesi tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif ($aksi === 'batal') {
        // Karyawan hanya boleh membatalkan pengajuannya sendiri yang masih menunggu
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM pengajuan WHERE id = ? AND karyawan_id = ? AND status = 'Menunggu'");
        $stmt->bind_param("ii", $id, $karyawan_id);
        $stmt->execute();
        $_SESSION['pesan'] = $stmt->affected_rows > 0 ? 'Pengajuan berhasil dibatalkan.' : 'Pengajuan tidak ditemukan atau sudah diproses admin.';
        $_SESSION['pesan_tipe'] = $stmt->affected_rows > 0 ? 'success' : 'warning';
        header("Location: pengajuan.php");
        exit;
    } elseif ($aksi === 'ajukan') {
        $form['jenis']   = $_POST['jenis'] ?? '';
        $form['mulai']   = trim($_POST['mulai'] ?? '');
        $form['selesai'] = trim($_POST['selesai'] ?? '');
        $form['alasan']  = trim($_POST['alasan'] ?? '');

        if (!in_array($form['jenis'], JENIS_PENGAJUAN, true)) {
            $error = 'Jenis pengajuan tidak valid.';
        } elseif (!tanggal_valid($form['mulai']) || !tanggal_valid($form['selesai'])) {
            $error = 'Tanggal tidak valid.';
        } elseif ($form['selesai'] < $form['mulai']) {
            $error = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        } elseif ((strtotime($form['selesai']) - strtotime($form['mulai'])) / 86400 + 1 > MAKS_HARI_PENGAJUAN) {
            $error = 'Rentang pengajuan maksimal ' . MAKS_HARI_PENGAJUAN . ' hari.';
        } elseif ($form['jenis'] === 'Cuti' && $form['mulai'] < $hari_ini) {
            $error = 'Cuti harus diajukan mulai hari ini atau tanggal berikutnya.';
        } elseif ($form['jenis'] !== 'Cuti' && $form['mulai'] < date('Y-m-d', strtotime('-7 days'))) {
            $error = 'Izin/sakit hanya bisa diajukan maksimal 7 hari ke belakang.';
        } elseif (hari_kerja_antara($form['mulai'], $form['selesai']) < 1) {
            $error = 'Rentang tanggal hanya berisi Sabtu/Minggu, tidak ada hari kerja yang perlu diajukan.';
        } elseif ($form['jenis'] !== 'Sakit' && $form['alasan'] === '') {
            $error = 'Alasan wajib diisi untuk pengajuan ' . strtolower($form['jenis']) . '.';
        } elseif (mb_strlen($form['alasan']) > 255) {
            $error = 'Alasan maksimal 255 karakter.';
        } else {
            // Tolak kalau tanggalnya bertabrakan dengan pengajuan lain yang masih menunggu / sudah disetujui
            $stmt = $conn->prepare("
                SELECT COUNT(*) n FROM pengajuan
                WHERE karyawan_id = ? AND status IN ('Menunggu', 'Disetujui')
                  AND tanggal_mulai <= ? AND tanggal_selesai >= ?");
            $stmt->bind_param("iss", $karyawan_id, $form['selesai'], $form['mulai']);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()['n'] > 0) {
                $error = 'Tanggal tersebut bertabrakan dengan pengajuan Anda yang lain (menunggu atau sudah disetujui).';
            }
        }

        if ($error === '') {
            $alasan = $form['alasan'] !== '' ? $form['alasan'] : null;
            $stmt = $conn->prepare("INSERT INTO pengajuan (karyawan_id, jenis, tanggal_mulai, tanggal_selesai, alasan) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $karyawan_id, $form['jenis'], $form['mulai'], $form['selesai'], $alasan);
            $stmt->execute();
            $_SESSION['pesan'] = 'Pengajuan ' . strtolower($form['jenis']) . ' berhasil dikirim. Menunggu persetujuan admin.';
            $_SESSION['pesan_tipe'] = 'success';
            header("Location: pengajuan.php");
            exit;
        }
    }
    if ($error !== '') { $_SESSION['pesan'] = $error; $_SESSION['pesan_tipe'] = 'danger'; }
}

// ===== Data pengajuan milik karyawan ini =====
$filter = $_GET['status'] ?? '';
if (!in_array($filter, STATUS_PENGAJUAN, true)) $filter = '';

// Ringkasan jumlah per status
$hitung = array_fill_keys(STATUS_PENGAJUAN, 0);
$stmt = $conn->prepare("SELECT status, COUNT(*) n FROM pengajuan WHERE karyawan_id = ? GROUP BY status");
$stmt->bind_param("i", $karyawan_id);
$stmt->execute();
foreach ($stmt->get_result() as $r) { $hitung[$r['status']] = (int)$r['n']; }
$total_semua = array_sum($hitung);

$total = $filter === '' ? $total_semua : $hitung[$filter];
$per_page = 5;
$pages  = max(1, (int)ceil($total / $per_page));
$page   = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
$offset = ($page - 1) * $per_page;

$sql = "SELECT * FROM pengajuan WHERE karyawan_id = ?" . ($filter !== '' ? " AND status = ?" : "")
     . " ORDER BY dibuat_pada DESC, id DESC LIMIT $per_page OFFSET $offset";
$stmt = $conn->prepare($sql);
if ($filter !== '') $stmt->bind_param("is", $karyawan_id, $filter);
else                $stmt->bind_param("i", $karyawan_id);
$stmt->execute();
$daftar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$dari   = $total ? $offset + 1 : 0;
$sampai = $offset + count($daftar);
$base_q = ['status' => $filter];
function link_hal($base_q, $p) { return '?' . http_build_query($base_q + ['page' => $p]); }
$p_awal  = max(1, min($page - 2, $pages - 4));
$p_akhir = min($pages, $p_awal + 4);

// ===== Data pengguna untuk navbar =====
$nama = $_SESSION['username'] ?? 'Karyawan'; $jabatan = 'Karyawan'; $nip = $karyawan_id;
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
<title>Pengajuan Izin &amp; Cuti - Karyon HCM Enterprise</title>
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
.ket-sub{display:block;margin-top:3px;font-size:8.5px;font-weight:400;color:#64748b;white-space:normal;line-height:1.35}
.ket-sub.merah{color:#b42318}
.activity-badge.pg-menunggu{background:#fdf0d5;color:#9a6700}
.activity-badge.pg-disetujui{background:#6ee7b7;color:#065f46}
.activity-badge.pg-ditolak{background:#fde2e2;color:#b42318}
.pengajuan-table td{white-space:normal;height:auto;padding:11px 19px;vertical-align:top}
.pengajuan-table td:last-child,.pengajuan-table th:last-child{text-align:left}
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

/* ===== KHUSUS HALAMAN PENGAJUAN ===== */
.pg-layout{display:grid;grid-template-columns:340px 1fr;gap:16px;align-items:start}
.form-card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 3px 16px rgba(30,64,175,.05)}
.form-card h2{font-size:12.5px;font-weight:700;margin:0 0 14px;display:flex;align-items:center;gap:8px}
.fld{margin-bottom:12px}
.fld label{display:block;font-size:8.5px;font-weight:700;letter-spacing:.5px;color:#475569;text-transform:uppercase;margin-bottom:5px}
.fld label span{font-weight:500;text-transform:none;letter-spacing:0;color:#64748b}
.fld input,.fld select,.fld textarea{width:100%;border:0;border-radius:8px;background:#eef2fb;padding:0 12px;height:34px;font-family:inherit;font-size:10.5px;color:#334155;outline:0}
.fld textarea{height:auto;min-height:74px;padding:9px 12px;resize:vertical}
.fld input:focus,.fld select:focus,.fld textarea:focus{box-shadow:0 0 0 2px #c9d8f8}
.fld-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.hint{font-size:9px;color:#64748b;line-height:1.5;margin:2px 0 14px}
.btn-kirim{width:100%;justify-content:center}

.alert{padding:11px 14px;border-radius:10px;font-size:11px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.alert-success{background:#d4fae9;color:#065f46}
.alert-danger{background:#fde2e2;color:#b42318}
.alert-warning{background:#fdf0d5;color:#9a6700}
.alert button{border:0;background:transparent;font-size:16px;line-height:1;cursor:pointer;color:inherit}

.pg-table{table-layout:auto}
.pg-table td{white-space:normal;height:auto;padding:12px 14px;vertical-align:top}
.pg-table th{padding:0 14px}
.pg-table td:last-child,.pg-table th:last-child{text-align:center}
.activity-badge.pg-menunggu{background:#fdf0d5;color:#9a6700}
.activity-badge.pg-disetujui{background:#6ee7b7;color:#065f46}
.activity-badge.pg-ditolak{background:#fde2e2;color:#b42318}
.sub{display:block;margin-top:5px;font-size:9px;color:#64748b;line-height:1.45;font-weight:400}
.sub.merah{color:#b42318}
.btn-batal{border:0;border-radius:6px;background:#fde2e2;color:#b42318;font-family:inherit;font-size:9px;font-weight:700;padding:5px 10px;cursor:pointer}
.btn-batal:hover{background:#fbcfcf}
.filter-link{display:flex;gap:6px;flex-wrap:wrap}
.filter-link a{font-size:9px;font-weight:600;color:#475569;background:#eef2fb;padding:4px 10px;border-radius:999px}
.filter-link a.on{background:#0048c8;color:#fff}
@media(max-width:1000px){.pg-layout{grid-template-columns:1fr}.history-table-card{overflow-x:auto}.pg-table{min-width:640px}}
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
    <a href="pengajuan.php" class="active">Pengajuan Izin &amp; Cuti</a>
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
<div class="alert alert-<?= h($_SESSION['pesan_tipe']) ?>">
  <span><?= h($_SESSION['pesan']) ?></span>
  <button type="button" onclick="this.parentNode.remove()">&times;</button>
</div>
<?php unset($_SESSION['pesan'], $_SESSION['pesan_tipe']); endif; ?>

<header class="page-intro">
  <div>
    <h1>Pengajuan Izin &amp; Cuti</h1>
    <p>Ajukan izin, sakit, atau cuti. Admin akan menyetujui atau menolak, dan hasilnya tampil di riwayat Anda.</p>
  </div>
  <div class="intro-tags">
    <span class="green-tag"><i class="bi bi-circle-fill"></i> SESI LOGIN AKTIF</span>
    <a class="blue-tag" href="pengajuan.php"><i class="bi bi-arrow-repeat"></i> Segarkan Data</a>
  </div>
</header>

<section class="history-stats">
  <article>
    <small>Total Pengajuan</small>
    <span class="stat-icon"><i class="bi bi-envelope-paper"></i></span>
    <div class="stat-value"><b><?= $total_semua ?></b><span>Pengajuan</span></div>
    <div class="stat-foot"><span>Seluruh riwayat pengajuan Anda</span></div>
  </article>
  <article>
    <small>Menunggu</small>
    <span class="stat-icon"><i class="bi bi-hourglass-split"></i></span>
    <div class="stat-value"><b><?= $hitung['Menunggu'] ?></b><span>Pengajuan</span></div>
    <div class="stat-foot"><span>Belum diputuskan admin</span></div>
  </article>
  <article>
    <small>Disetujui</small>
    <span class="stat-icon"><i class="bi bi-check2-circle"></i></span>
    <div class="stat-value"><b><?= $hitung['Disetujui'] ?></b><span>Pengajuan</span></div>
    <div class="stat-foot"><span class="txt-green"><i class="bi bi-check-circle"></i>Tercatat di riwayat</span></div>
  </article>
  <article>
    <small>Ditolak</small>
    <span class="stat-icon"><i class="bi bi-x-circle"></i></span>
    <div class="stat-value"><b><?= $hitung['Ditolak'] ?></b><span>Pengajuan</span></div>
    <div class="stat-foot"><span>Lihat alasan di tabel</span></div>
  </article>
</section>

<div class="pg-layout">

  <!-- ===== Form pengajuan ===== -->
  <section class="form-card">
    <h2><i class="bi bi-circle-fill blue-dot"></i> Buat Pengajuan Baru</h2>
    <form method="POST" id="form-pengajuan">
      <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
      <input type="hidden" name="aksi" value="ajukan">

      <div class="fld">
        <label>Jenis Pengajuan</label>
        <select name="jenis" id="jenis">
          <?php foreach (JENIS_PENGAJUAN as $j): ?>
            <option value="<?= $j ?>" <?= $form['jenis'] === $j ? 'selected' : '' ?>><?= $j ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="fld-row">
        <div class="fld">
          <label>Tanggal Mulai</label>
          <input type="date" name="mulai" id="mulai" value="<?= h($form['mulai']) ?>" required>
        </div>
        <div class="fld">
          <label>Tanggal Selesai</label>
          <input type="date" name="selesai" id="selesai" value="<?= h($form['selesai']) ?>" required>
        </div>
      </div>

      <div class="fld">
        <label>Alasan <span id="ket-alasan">(wajib)</span></label>
        <textarea name="alasan" id="alasan" maxlength="255" placeholder="Contoh: keperluan keluarga"><?= h($form['alasan']) ?></textarea>
      </div>

      <p class="hint">Hanya hari kerja (Senin–Jumat) yang dicatat. Izin/sakit boleh diajukan sampai 7 hari ke belakang, cuti harus mulai hari ini atau sesudahnya.</p>
      <button type="submit" class="btn-main btn-kirim"><i class="bi bi-send-fill"></i> Kirim Pengajuan</button>
    </form>
  </section>

  <!-- ===== Riwayat pengajuan ===== -->
  <section class="history-table-card">
    <div class="emp-card-head">
      <b><i class="bi bi-circle-fill blue-dot"></i> Riwayat Pengajuan Saya</b>
      <span class="filter-link">
        <a href="?status=" class="<?= $filter === '' ? 'on' : '' ?>">Semua</a>
        <?php foreach (STATUS_PENGAJUAN as $s): ?>
          <a href="?status=<?= $s ?>" class="<?= $filter === $s ? 'on' : '' ?>"><?= $s ?></a>
        <?php endforeach; ?>
      </span>
    </div>
    <table class="history-table pg-table">
      <thead><tr><th>Jenis</th><th>Tanggal</th><th>Alasan</th><th>Diajukan</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php if ($daftar): foreach ($daftar as $r): ?>
        <tr>
          <td><span class="activity-badge st-<?= strtolower($r['jenis']) ?>"><?= h($r['jenis']) ?></span></td>
          <td><b><?= h(rentang_tanggal($r['tanggal_mulai'], $r['tanggal_selesai'])) ?></b></td>
          <td><?= $r['alasan'] !== null && $r['alasan'] !== '' ? h($r['alasan']) : '—' ?></td>
          <td><?= date('d-m-Y H:i', strtotime($r['dibuat_pada'])) ?></td>
          <td>
            <span class="activity-badge <?= kelas_status_pengajuan($r['status']) ?>"><?= h($r['status']) ?></span>
            <?php if ($r['status'] === 'Ditolak'): ?>
              <span class="sub merah">Alasan penolakan: <?= $r['catatan_admin'] ? h($r['catatan_admin']) : 'tidak dicantumkan' ?></span>
            <?php elseif ($r['status'] === 'Disetujui' && $r['catatan_admin']): ?>
              <span class="sub">Catatan admin: <?= h($r['catatan_admin']) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($r['status'] === 'Menunggu'): ?>
              <form method="POST" onsubmit="return confirm('Batalkan pengajuan ini?')">
                <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>">
                <input type="hidden" name="aksi" value="batal">
                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn-batal">Batalkan</button>
              </form>
            <?php else: ?>—<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="6" class="empty-state">Belum ada pengajuan<?= $filter !== '' ? ' dengan status ' . h($filter) : '' ?>.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    <div class="history-foot">
      <span>Menampilkan <b><?= $dari ?> - <?= $sampai ?></b> dari <b><?= $total ?></b> pengajuan</span>
      <nav class="history-pager">
        <a class="<?= $page <= 1 ? 'off' : '' ?>" href="<?= h(link_hal($base_q, max(1, $page - 1))) ?>"><i class="bi bi-chevron-left"></i></a>
        <?php for ($p = $p_awal; $p <= $p_akhir; $p++): ?>
          <a class="<?= $p === $page ? 'on' : '' ?>" href="<?= h(link_hal($base_q, $p)) ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a class="<?= $page >= $pages ? 'off' : '' ?>" href="<?= h(link_hal($base_q, min($pages, $page + 1))) ?>"><i class="bi bi-chevron-right"></i></a>
      </nav>
    </div>
  </section>

</div>
</main>

<footer class="site-footer">
  <b>© 2026 Karyon HCM Enterprise. All rights reserved.</b><i></i><span>Sistem Informasi Manajemen &amp; Absensi</span>
</footer>

<script>
// Atur form sesuai jenis: alasan opsional untuk Sakit, batas tanggal minimal, tanggal selesai mengikuti tanggal mulai
(function () {
  var jenis = document.getElementById('jenis'), mulai = document.getElementById('mulai'),
      selesai = document.getElementById('selesai'), alasan = document.getElementById('alasan'),
      ket = document.getElementById('ket-alasan');
  var hariIni = '<?= $hari_ini ?>', tujuhLalu = '<?= date('Y-m-d', strtotime('-7 days')) ?>';

  function atur() {
    var sakit = jenis.value === 'Sakit';
    alasan.required = !sakit;
    ket.textContent = sakit ? '(opsional)' : '(wajib)';
    mulai.min = jenis.value === 'Cuti' ? hariIni : tujuhLalu;
    selesai.min = mulai.value || mulai.min;
  }
  jenis.addEventListener('change', atur);
  mulai.addEventListener('change', function () {
    if (selesai.value < mulai.value) selesai.value = mulai.value;
    atur();
  });
  atur();
})();
</script>
</body>
</html>
