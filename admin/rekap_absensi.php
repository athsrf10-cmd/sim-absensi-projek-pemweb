<?php
require_once '../auth/cek_session.php';
require_once '../includes/fungsi.php';
wajib_role('admin');

// ===== 1. Ambil dan bersihkan filter =====
$dari   = bersihkan_tanggal($_GET['dari'] ?? '', date('Y-m-01'));   // default: tanggal 1 bulan ini
$sampai = bersihkan_tanggal($_GET['sampai'] ?? '', date('Y-m-d'));  // default: hari ini
if ($dari > $sampai) { [$dari, $sampai] = [$sampai, $dari]; }      // kalau terbalik, ditukar
$karyawan = (int)($_GET['karyawan'] ?? 0);
$status   = in_array($_GET['status'] ?? '', DAFTAR_STATUS) ? $_GET['status'] : '';
$per      = (int)($_GET['per'] ?? 10);
if (!in_array($per, [10, 25, 50])) $per = 10;
$halaman  = max(1, (int)($_GET['page'] ?? 1));
$cetak    = isset($_GET['cetak']);   // mode cetak: tampilkan semua baris tanpa paginasi

list($sql_where, $tipe, $param) = filter_rekap($dari, $sampai, $karyawan, $status);

// ===== 2. Ringkasan jumlah per status (dari SELURUH hasil filter) =====
$hitung = array_fill_keys(DAFTAR_STATUS, 0);
foreach (query_aman($conn, "SELECT a.status, COUNT(*) n FROM absensi a WHERE $sql_where GROUP BY a.status", $tipe, $param) as $r) {
    $hitung[$r['status']] = $r['n'];
}
$total = array_sum($hitung);

// ===== 3. Paginasi =====
$per_halaman = $cetak ? max($total, 1) : $per;
$total_halaman = max(1, (int)ceil($total / $per_halaman));
$halaman = min($halaman, $total_halaman);
$offset = ($halaman - 1) * $per_halaman;

// ===== 4. Data tabel =====
$rows = query_aman($conn, "
    SELECT a.*, k.nip, k.nama, j.nama_jabatan, u.username
    FROM absensi a
    JOIN karyawan k ON a.karyawan_id = k.id
    LEFT JOIN jabatan j ON k.jabatan_id = j.id
    LEFT JOIN users u ON u.karyawan_id = k.id
    WHERE $sql_where
    ORDER BY a.tanggal DESC, k.nama ASC
    LIMIT $per_halaman OFFSET $offset", $tipe, $param)->fetch_all(MYSQLI_ASSOC);

$daftar_karyawan = $conn->query("SELECT id, nip, nama FROM karyawan ORDER BY nama");

// Filter yang ikut terbawa di link export, cetak, dan paginasi
$filter_url = ['dari' => $dari, 'sampai' => $sampai, 'karyawan' => $karyawan, 'status' => $status, 'per' => $per];

// Pengaturan tiap kartu statistik: ikon dan nama kelas warna
$kartu = [
    'Hadir' => ['ikon' => 'bi-check-circle',    'warna' => 'hadir'],
    'Izin'  => ['ikon' => 'bi-journal-check',   'warna' => 'izin'],
    'Sakit' => ['ikon' => 'bi-bandaid',         'warna' => 'sakit'],
    'Alpha' => ['ikon' => 'bi-x-circle',        'warna' => 'alpha'],
    'Cuti'  => ['ikon' => 'bi-umbrella',        'warna' => 'cuti'],
];

include '../includes/header.php';
?>

<!-- ===== Judul + tombol ===== -->
<div class="k-head no-print-keep">
  <div>
    <div class="k-eyebrow">Modul Presensi &amp; Rekapitulasi</div>
    <h1 class="k-title">Rekap Absensi</h1>
    <p class="k-lead">Laporan kehadiran karyawan, jam kerja, dan durasi kerja per periode.</p>
  </div>
  <div class="d-flex gap-2 no-print">
    <!-- Export CSV mengikuti filter yang sedang dipilih -->
    <a href="export_csv.php?<?= http_build_query($filter_url) ?>" class="k-btn-putih"><i class="bi bi-download"></i> Export Rekap</a>
    <!-- Cetak: membuka tab baru berisi semua data, lalu otomatis memunculkan dialog cetak -->
    <a href="?<?= http_build_query($filter_url + ['cetak' => 1]) ?>" target="_blank" class="k-btn-putih"><i class="bi bi-printer"></i> Cetak Rekap</a>
  </div>
</div>
<!-- Pesan sukses/gagal setelah menghapus absensi -->
<?php tampil_flash(); ?>

<!-- ===== Kartu filter ===== -->
<form method="GET" id="form-rekap" class="k-card-filter no-print">
  <div class="row g-3 align-items-end">
    <div class="col-md-3 k-field">
      <label>Dari Tanggal</label>
      <label class="k-tool"><i class="bi bi-calendar3"></i><input type="date" name="dari" value="<?= h($dari) ?>"></label>
    </div>
    <div class="col-md-3 k-field">
      <label>Sampai Tanggal</label>
      <label class="k-tool"><i class="bi bi-calendar-check"></i><input type="date" name="sampai" value="<?= h($sampai) ?>"></label>
    </div>
    <div class="col-md-3 k-field">
      <label>Karyawan</label>
      <label class="k-tool"><i class="bi bi-person"></i>
        <select name="karyawan">
          <option value="0">Semua Karyawan</option>
          <?php while ($k = $daftar_karyawan->fetch_assoc()): ?>
            <option value="<?= $k['id'] ?>" <?= $karyawan == $k['id'] ? 'selected' : '' ?>><?= h($k['nama']) ?> (<?= h($k['nip']) ?>)</option>
          <?php endwhile; ?>
        </select>
      </label>
    </div>
    <div class="col-md-2 k-field">
      <label>Status</label>
      <label class="k-tool">
        <select name="status">
          <option value="">Semua</option>
          <?php foreach (DAFTAR_STATUS as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="col-md-1">
      <button type="submit" class="k-btn-biru w-100 justify-content-center">Filter</button>
    </div>
  </div>
</form>

<!-- Keterangan periode (ikut tercetak sebagai judul laporan) -->
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
  <span class="k-chip2"><i class="bi bi-calendar-range"></i> Periode: <?= date('d-m-Y', strtotime($dari)) ?> s/d <?= date('d-m-Y', strtotime($sampai)) ?></span>
  <span class="k-chip2"><i class="bi bi-database"></i> Total data: <?= $total ?> rekaman</span>
  <span class="text-muted small ms-auto">Format waktu: WIB (UTC+7)</span>
</div>

<!-- ===== 4 kartu ringkasan status ===== -->
<div class="k-stats lima" style="margin-top:0">
  <?php foreach ($kartu as $nama_status => $k): ?>
    <div class="k-stat">
      <div class="k-stat-top"><?= strtoupper($nama_status) ?> <span class="k-stat-ico"><i class="bi <?= $k['ikon'] ?>"></i></span></div>
      <div class="k-stat-body">
        <span class="k-angka"><?= $hitung[$nama_status] ?></span>
        <span class="k-tag"><?= persen($hitung[$nama_status], $total) ?>% rasio</span>
      </div>
      <!-- Garis progres: lebarnya sesuai persentase -->
      <div class="k-prog"><span class="p-<?= $k['warna'] ?>" style="width:<?= persen($hitung[$nama_status], $total) ?>%"></span></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===== Tabel rekap ===== -->
<div class="k-panel">
  <div class="k-panel-head">
    <h2 class="k-panel-title">Data Lembar Presensi</h2>
    <!-- Pilihan jumlah baris per halaman; atribut form="..." membuatnya ikut terkirim bersama filter -->
    <label class="k-tool no-print">Tampilkan:
      <select name="per" form="form-rekap" onchange="this.form.submit()">
        <?php foreach ([10, 25, 50] as $p): ?>
          <option value="<?= $p ?>" <?= $per == $p ? 'selected' : '' ?>><?= $p ?> Baris</option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <div class="k-table-wrap">
    <table class="k-table">
      <thead>
        <tr>
          <th>No</th><th>Tanggal</th><th>NIP</th><th>Nama Karyawan</th><th>Jabatan</th>
          <th>Jam Masuk</th><th>Jam Pulang</th><th>Durasi Kerja</th><th>Status</th><th class="no-print" style="text-align:right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) > 0): ?>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= $offset + $i + 1 ?></td>
              <td><b><?= date('d-m-Y', strtotime($r['tanggal'])) ?></b></td>
              <td><span class="k-nip"><?= h($r['nip']) ?></span></td>
              <td>
                <div class="k-orang">
                  <span class="k-avatar-s"><?= h(inisial($r['nama'])) ?></span>
                  <div><b><?= h($r['nama']) ?></b><small>@<?= h($r['username'] ?? '-') ?></small></div>
                </div>
              </td>
              <td><span class="k-bdg"><?= h($r['nama_jabatan'] ?? '-') ?></span></td>
              <td>
                <div class="k-jam"><?= $r['jam_masuk'] ?? '--:--:--' ?></div>
                <?php if ($r['jam_masuk']): $telat = terlambat($r['jam_masuk']); ?>
                  <!-- Dot hijau = tepat waktu, dot merah = terlambat -->
                  <div class="k-ket <?= $telat ? 'telat' : 'tepat' ?>"><span class="dot"></span><?= $telat ? 'Terlambat' : 'Tepat Waktu' ?></div>
                <?php endif; ?>
              </td>
              <td class="k-jam"><?= $r['jam_pulang'] ?? '--:--:--' ?></td>
              <td class="k-jam"><?= durasi($r['jam_masuk'], $r['jam_pulang']) ?></td>
              <td>
                <span class="k-badge st-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span>
                <?php if (!empty($r['keterangan'])): ?>
                  <!-- Alasan izin/cuti dari pengajuan karyawan -->
                  <span class="k-alasan"><?= h($r['keterangan']) ?></span>
                <?php endif; ?>
              </td>
              <td class="no-print">
                <div class="k-aksi">
                  <!-- Ikon pensil: ubah absensi tanggal tersebut lewat form manual -->
                    <a class="k-aksi-btn" title="Ubah absensi"
                     href="absensi_manual.php?karyawan=<?= $r['karyawan_id'] ?>&tanggal=<?= h($r['tanggal']) ?>"><i class="bi bi-pencil"></i></a>
                     <!-- Ikon tempat sampah: hapus absensi.
                      class form-hapus: konfirmasi ditangani script.js.
                      data-konfirmasi: teks yang muncul di kotak konfirmasi -->
                      <form action="hapus_absensi.php" method="POST" class="d-inline form-hapus"
                      data-konfirmasi="Hapus absensi <?= h($r['nama']) ?> tanggal <?= date('d-m-Y', strtotime($r['tanggal'])) ?>? Riwayat di halaman karyawan juga ikut terhapus dan tidak bisa dikembalikan.">
                      <input type="hidden" name="id" value="<?= $r['id'] ?>">
                      <!-- balik: filter dan halaman saat ini, supaya setelah hapus kembali ke tampilan yang sama -->
                       <input type="hidden" name="balik" value="<?= h(http_build_query($filter_url + ['page' => $halaman])) ?>">
                       <button type="submit" class="k-aksi-btn merah" title="Hapus absensi"><i class="bi bi-trash"></i></button>
                      </form>
                    </div>
                 </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="10" class="text-center text-muted py-4">Tidak ada data pada filter ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="k-panel-foot">
    <span>Menampilkan <b><?= $total > 0 ? $offset + 1 : 0 ?> - <?= min($offset + $per_halaman, $total) ?></b> dari <b><?= $total ?></b> rekaman absensi</span>
    <?php render_paging($halaman, $total_halaman, $filter_url); ?>
  </div>
</div>

<!-- ===== Aturan jam kerja (diambil dari pengaturan di koneksi.php) ===== -->
<div class="k-info" style="max-width:520px">
  <div>
    <b><i class="bi bi-clock"></i> Aturan Jam Kerja</b>
    <p>Jam masuk standar <b style="display:inline"><?= substr(JAM_MASUK, 0, 5) ?> WIB</b>, toleransi <?= TOLERANSI_MENIT ?> menit.
       Masuk setelah <?= date('H:i', strtotime(JAM_MASUK) + TOLERANSI_MENIT * 60) ?> dihitung terlambat.</p>
  </div>
</div>

<?php if ($cetak): ?>
<script>window.onload = function () { window.print(); };  // mode cetak: dialog cetak langsung muncul</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>