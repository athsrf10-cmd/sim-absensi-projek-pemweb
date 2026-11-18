<?php
require_once '../../auth/cek_session.php';
require_once '../../includes/fungsi.php';
wajib_role('admin');

// ===== 1. Filter dari URL =====
$q              = trim($_GET['q'] ?? '');            // kata kunci pencarian
$jabatan_filter = (int)($_GET['jabatan'] ?? 0);      // 0 = semua jabatan
$halaman        = max(1, (int)($_GET['page'] ?? 1));
$per_halaman    = 10;

// ===== 2. Data kartu statistik =====
$total_karyawan = $conn->query("SELECT COUNT(*) n FROM karyawan")->fetch_assoc()['n'];
$total_jabatan  = $conn->query("SELECT COUNT(*) n FROM jabatan")->fetch_assoc()['n'];
$hadir          = $conn->query("SELECT COUNT(*) n FROM absensi WHERE tanggal = CURDATE() AND status = 'Hadir'")->fetch_assoc()['n'];
$izin_sakit     = $conn->query("SELECT COUNT(*) n FROM absensi WHERE tanggal = CURDATE() AND status IN ('Izin','Sakit')")->fetch_assoc()['n'];

// Daftar jabatan untuk dropdown filter
$daftar_jabatan = $conn->query("SELECT id, nama_jabatan FROM jabatan ORDER BY nama_jabatan");

// ===== 3. Susun kondisi WHERE sesuai filter =====
$where = ["1=1"]; $tipe = ""; $param = [];   // 1=1 supaya kondisi berikutnya tinggal ditambah "AND"
if ($q !== '') {
    $where[] = "(k.nama LIKE ? OR k.nip LIKE ? OR j.nama_jabatan LIKE ?)";
    $tipe .= "sss"; $like = "%$q%"; array_push($param, $like, $like, $like);
}
if ($jabatan_filter > 0) {
    $where[] = "k.jabatan_id = ?"; $tipe .= "i"; $param[] = $jabatan_filter;
}
$sql_where = implode(" AND ", $where);

// ===== 4. Hitung total (untuk paginasi) lalu ambil data halaman ini =====
$total = query_aman($conn, "
    SELECT COUNT(*) n FROM karyawan k
    LEFT JOIN jabatan j ON k.jabatan_id = j.id
    WHERE $sql_where", $tipe, $param)->fetch_assoc()['n'];

$total_halaman = max(1, (int)ceil($total / $per_halaman));
$halaman = min($halaman, $total_halaman);
$offset  = ($halaman - 1) * $per_halaman;

// Gabungkan jabatan, akun login, dan absensi HARI INI (untuk kolom status)
$rows = query_aman($conn, "
    SELECT k.*, j.nama_jabatan, u.username, a.status AS status_hari_ini
    FROM karyawan k
    LEFT JOIN jabatan j ON k.jabatan_id = j.id
    LEFT JOIN users u ON u.karyawan_id = k.id
    LEFT JOIN absensi a ON a.karyawan_id = k.id AND a.tanggal = CURDATE()
    WHERE $sql_where
    ORDER BY k.nama ASC
    LIMIT $per_halaman OFFSET $offset", $tipe, $param)->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
?>

<!-- ===== 4 kartu statistik ===== -->
<div class="k-stats" style="margin-top:0">
  <div class="k-stat">
    <div class="k-stat-top">Total Karyawan <span class="k-stat-ico"><i class="bi bi-people-fill"></i></span></div>
    <div class="k-stat-body"><div><span class="k-angka"><?= $total_karyawan ?></span><span class="k-satuan">orang</span></div></div>
    <div class="k-sub">Terdaftar di sistem</div>
  </div>
  <div class="k-stat">
    <div class="k-stat-top">Hadir Hari Ini <span class="k-stat-ico hijau"><i class="bi bi-person-check-fill"></i></span></div>
    <div class="k-stat-body"><div><span class="k-angka hijau"><?= $hadir ?></span><span class="k-satuan">orang</span></div></div>
    <div class="k-sub"><?= persen($hadir, $total_karyawan) ?>% dari total karyawan</div>
  </div>
  <div class="k-stat">
    <div class="k-stat-top">Izin / Sakit Hari Ini <span class="k-stat-ico"><i class="bi bi-calendar-x"></i></span></div>
    <div class="k-stat-body"><div><span class="k-angka"><?= $izin_sakit ?></span><span class="k-satuan">orang</span></div></div>
    <div class="k-sub">Berdasarkan absensi hari ini</div>
  </div>
  <div class="k-stat">
    <div class="k-stat-top">Total Jabatan <span class="k-stat-ico"><i class="bi bi-briefcase"></i></span></div>
    <div class="k-stat-body"><div><span class="k-angka"><?= $total_jabatan ?></span><span class="k-satuan">posisi</span></div></div>
    <div class="k-sub">Posisi yang tersedia</div>
  </div>
</div>

<?php tampil_flash(); ?>

<!-- ===== Judul + filter ===== -->
<div class="k-panel" style="margin-bottom:24px">
  <div class="k-panel-head">
    <div>
      <h1 class="k-title">Data Karyawan <span class="k-chip" style="background:#dfe7ff;color:#2a4a99">Pegawai</span></h1>
      <p class="k-lead">Manajemen data pegawai, NIP, jabatan, dan kontak dalam sistem Karyon.</p>
    </div>
    <a href="tambah.php" class="k-btn-biru"><i class="bi bi-plus-lg"></i> Tambah Karyawan</a>
  </div>

  <!-- Form filter: terkirim otomatis (diatur script.js lewat id form-filter) -->
  <form method="GET" id="form-filter" class="k-filterbar">
    <label class="k-tool" style="flex:2"><i class="bi bi-search"></i>
      <input type="text" id="input-cari" name="q" value="<?= h($q) ?>" placeholder="Cari nama / NIP / jabatan...">
    </label>
    <label class="k-tool">
      <select name="jabatan" data-auto>
        <option value="0">Semua Jabatan</option>
        <?php while ($j = $daftar_jabatan->fetch_assoc()): ?>
          <option value="<?= $j['id'] ?>" <?= $jabatan_filter == $j['id'] ? 'selected' : '' ?>><?= h($j['nama_jabatan']) ?></option>
        <?php endwhile; ?>
      </select>
    </label>
    <!-- Tombol reset: kembali ke halaman tanpa filter -->
    <a href="index.php" class="k-btn-reset" title="Reset filter"><i class="bi bi-funnel"></i></a>
  </form>
</div>

<!-- ===== Tabel karyawan ===== -->
<div class="k-panel">
  <div class="k-table-wrap">
    <table class="k-table">
      <thead>
        <tr>
          <th>No</th><th>NIP</th><th>Nama Karyawan</th><th>Jabatan</th>
          <th>No. HP &amp; Alamat</th><th>Absensi Hari Ini</th><th style="text-align:right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) > 0): ?>
          <?php foreach ($rows as $i => $r): ?>
            <?php
              // Status absensi hari ini; kalau belum ada data berarti "Belum Absen"
              $st = $r['status_hari_ini'] ?? 'Belum Absen';
              $kelas_st = $r['status_hari_ini'] ? strtolower($r['status_hari_ini']) : 'belum';
            ?>
            <tr>
              <td><?= $offset + $i + 1 ?></td>
              <td><span class="k-nip"><?= h($r['nip']) ?></span></td>
              <td>
                <div class="k-orang">
                  <span class="k-avatar-s"><?= h(inisial($r['nama'])) ?></span>
                  <div><b><?= h($r['nama']) ?></b><small>@<?= h($r['username'] ?? '-') ?></small></div>
                </div>
              </td>
              <td><span class="k-bdg"><?= h($r['nama_jabatan'] ?? '-') ?></span></td>
              <td>
                <div><i class="bi bi-telephone text-muted"></i> <?= h($r['no_hp'] ?: '-') ?></div>
                <div class="k-trunc" title="<?= h($r['alamat']) ?>"><?= h($r['alamat'] ?: '-') ?></div>
              </td>
              <td><span class="k-badge st-<?= $kelas_st ?>"><?= $st ?></span></td>
              <td>
                <div class="k-aksi">
                  <a href="edit.php?id=<?= $r['id'] ?>" class="k-btn-edit"><i class="bi bi-pencil-square"></i> Edit</a>
                  <!-- form-hapus: konfirmasi dikendalikan script.js -->
                  <form action="hapus.php" method="POST" class="d-inline form-hapus">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button type="submit" class="k-btn-hapus"><i class="bi bi-trash"></i> Hapus</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data karyawan yang cocok.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="k-panel-foot">
    <span>Menampilkan <b><?= $total > 0 ? $offset + 1 : 0 ?> - <?= min($offset + $per_halaman, $total) ?></b> dari <b><?= $total ?></b> karyawan</span>
    <?php render_paging($halaman, $total_halaman, ['q' => $q, 'jabatan' => $jabatan_filter]); ?>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>