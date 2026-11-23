<?php
require_once '../../auth/cek_session.php';
require_once '../../includes/fungsi.php';
wajib_role('admin');

// ===== 1. Filter dan urutan =====
$q = trim($_GET['q'] ?? '');
$urut = $_GET['urut'] ?? 'nama';
// Daftar urutan yang diizinkan (whitelist) supaya aman dari input berbahaya
$pilihan_urut = [
    'nama'      => ['label' => 'Nama (A-Z)',          'sql' => 'j.nama_jabatan ASC'],
    'terbanyak' => ['label' => 'Karyawan Terbanyak',  'sql' => 'jumlah DESC, j.nama_jabatan ASC'],
    'terbaru'   => ['label' => 'Terbaru Ditambahkan', 'sql' => 'j.id DESC'],
];
if (!isset($pilihan_urut[$urut])) $urut = 'nama';
$halaman = max(1, (int)($_GET['page'] ?? 1));
$per_halaman = 10;

// ===== 2. Data kartu statistik =====
$total_jabatan  = $conn->query("SELECT COUNT(*) n FROM jabatan")->fetch_assoc()['n'];
$total_karyawan = $conn->query("SELECT COUNT(*) n FROM karyawan")->fetch_assoc()['n'];
$rata_rata = $total_jabatan > 0 ? round($total_karyawan / $total_jabatan, 1) : 0;

// Jabatan dengan karyawan terbanyak
$terbesar = $conn->query("
    SELECT j.nama_jabatan, COUNT(k.id) AS n
    FROM jabatan j LEFT JOIN karyawan k ON k.jabatan_id = j.id
    GROUP BY j.id ORDER BY n DESC, j.nama_jabatan ASC LIMIT 1
")->fetch_assoc();

// ===== 3. Data tabel =====
$where = "1=1"; $tipe = ""; $param = [];
// Pencarian sekarang mencocokkan nama ATAU kode jabatan
if ($q !== '') { $where .= " AND (j.nama_jabatan LIKE ? OR j.kode_jabatan LIKE ?)"; $tipe = "ss"; $param = ["%$q%", "%$q%"]; }

$total = query_aman($conn, "SELECT COUNT(*) n FROM jabatan j WHERE $where", $tipe, $param)->fetch_assoc()['n'];
$total_halaman = max(1, (int)ceil($total / $per_halaman));
$halaman = min($halaman, $total_halaman);
$offset = ($halaman - 1) * $per_halaman;

// COUNT(k.id) menghitung jumlah karyawan di tiap jabatan
$order = $pilihan_urut[$urut]['sql'];
$rows = query_aman($conn, "
    SELECT j.*, COUNT(k.id) AS jumlah
    FROM jabatan j LEFT JOIN karyawan k ON k.jabatan_id = j.id
    WHERE $where
    GROUP BY j.id
    ORDER BY $order
    LIMIT $per_halaman OFFSET $offset", $tipe, $param)->fetch_all(MYSQLI_ASSOC);

include '../../includes/header.php';
?>

<!-- ===== Judul ===== -->
<div class="k-head">
  <div>
    <div class="k-eyebrow">Organisasi &amp; Manajemen SDM</div>
    <h1 class="k-title">Data Jabatan</h1>
    <p class="k-lead">Daftar jabatan dan jumlah karyawan pada setiap posisi.</p>
  </div>
  <a href="tambah.php" class="k-btn-biru"><i class="bi bi-plus-circle"></i> Tambah Jabatan</a>
</div>

<!-- ===== 3 kartu statistik ===== -->
<div class="k-stats" style="grid-template-columns:repeat(3,1fr)">
  <div class="k-stat">
    <div class="k-stat-top">Total Jabatan <span class="k-stat-ico"><i class="bi bi-person-vcard"></i></span></div>
    <div class="k-stat-body"><div><span class="k-angka"><?= $total_jabatan ?></span><span class="k-satuan">posisi</span></div></div>
  </div>
  <div class="k-stat">
    <div class="k-stat-top">Jabatan Terbanyak <span class="k-stat-ico"><i class="bi bi-diagram-3"></i></span></div>
    <div class="k-stat-body"><div><span class="k-angka kecil"><?= h($terbesar && $terbesar['n'] > 0 ? $terbesar['nama_jabatan'] : '-') ?></span></div></div>
    <div class="k-sub">Berisi <b><?= $terbesar['n'] ?? 0 ?></b> karyawan</div>
  </div>
  <div class="k-stat">
    <div class="k-stat-top">Rata-rata per Jabatan <span class="k-stat-ico"><i class="bi bi-people"></i></span></div>
    <div class="k-stat-body"><div><span class="k-angka"><?= $rata_rata ?></span><span class="k-satuan">orang / jabatan</span></div></div>
  </div>
</div>

<?php tampil_flash(); ?>

<!-- ===== Tabel jabatan ===== -->
<div class="k-panel">
  <form method="GET" id="form-filter" class="k-filterbar" style="margin-top:24px">
    <label class="k-tool" style="flex:2"><i class="bi bi-search"></i>
      <input type="text" id="input-cari" name="q" value="<?= h($q) ?>" placeholder="Cari nama jabatan...">
    </label>
    <label class="k-tool"><i class="bi bi-sort-down"></i>
      <select name="urut" data-auto>
        <?php foreach ($pilihan_urut as $kunci => $p): ?>
          <option value="<?= $kunci ?>" <?= $urut === $kunci ? 'selected' : '' ?>><?= $p['label'] ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <a href="index.php" class="k-btn-reset" title="Reset"><i class="bi bi-arrow-repeat"></i></a>
  </form>

  <div class="k-table-wrap">
    <table class="k-table">
      <thead>
        <tr><th>No</th><th>Kode</th><th>Nama Jabatan</th><th>Jumlah Karyawan</th><th style="text-align:right">Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (count($rows) > 0): ?>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= $offset + $i + 1 ?></td>
              <!-- Kode jabatan diambil dari database (bisa diedit lewat form Edit) -->
              <td><span class="k-nip"><?= h($r['kode_jabatan']) ?></span></td>
              <td>
                <div class="k-orang">
                  <span class="k-avatar-s kotak"><?= h(strtoupper(substr($r['nama_jabatan'], 0, 1))) ?></span>
                  <b><?= h($r['nama_jabatan']) ?></b>
                </div>
              </td>
              <td><span class="k-count"><i class="bi bi-person"></i> <?= $r['jumlah'] ?> Orang</span></td>
              <td>
                <div class="k-aksi">
                  <a href="edit.php?id=<?= $r['id'] ?>" class="k-btn-edit"><i class="bi bi-pencil-square"></i> Edit</a>
                  <form action="hapus.php" method="POST" class="d-inline form-hapus">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <!-- Tombol hapus dinonaktifkan kalau jabatan masih dipakai karyawan -->
                    <button type="submit" class="k-btn-hapus" <?= $r['jumlah'] > 0 ? 'disabled title="Masih dipakai karyawan"' : '' ?>>
                      <i class="bi bi-trash"></i> Hapus
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data jabatan.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="k-panel-foot">
    <span>Menampilkan <b><?= $total > 0 ? $offset + 1 : 0 ?>-<?= min($offset + $per_halaman, $total) ?></b> dari <b><?= $total ?></b> jabatan</span>
    <?php render_paging($halaman, $total_halaman, ['q' => $q, 'urut' => $urut]); ?>
  </div>
</div>

<!-- ===== Kotak informasi ===== -->
<div class="k-info">
  <div>
    <b>Petunjuk Pengelolaan Jabatan</b>
    <p>Jabatan yang masih dipakai oleh karyawan tidak dapat dihapus. Pindahkan dulu karyawannya ke jabatan lain.</p>
  </div>
  <a href="../karyawan/index.php" class="k-btn-putih">Lihat Data Karyawan <i class="bi bi-arrow-right"></i></a>
</div>

<?php include '../../includes/footer.php'; ?>