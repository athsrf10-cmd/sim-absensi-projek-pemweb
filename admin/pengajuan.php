<?php
require_once '../auth/cek_session.php';
require_once '../includes/fungsi.php';
wajib_role('admin');

// ===== 1. Ambil dan bersihkan filter =====
$status   = in_array($_GET['status'] ?? '', STATUS_PENGAJUAN, true) ? $_GET['status'] : '';
$jenis    = in_array($_GET['jenis'] ?? '', JENIS_PENGAJUAN, true) ? $_GET['jenis'] : '';
$karyawan = (int)($_GET['karyawan'] ?? 0);
$halaman  = max(1, (int)($_GET['page'] ?? 1));
$per_halaman = 10;

$where = ["1=1"]; $tipe = ""; $param = [];
if ($status !== '')  { $where[] = "p.status = ?";      $tipe .= "s"; $param[] = $status; }
if ($jenis !== '')   { $where[] = "p.jenis = ?";       $tipe .= "s"; $param[] = $jenis; }
if ($karyawan > 0)   { $where[] = "p.karyawan_id = ?"; $tipe .= "i"; $param[] = $karyawan; }
$sql_where = implode(" AND ", $where);

// ===== 2. Ringkasan jumlah per status (seluruh data, tidak terpengaruh filter) =====
$hitung = array_fill_keys(STATUS_PENGAJUAN, 0);
foreach ($conn->query("SELECT status, COUNT(*) n FROM pengajuan GROUP BY status") as $r) {
    $hitung[$r['status']] = (int)$r['n'];
}
$total_semua = array_sum($hitung);

// ===== 3. Paginasi =====
$total = query_aman($conn, "SELECT COUNT(*) n FROM pengajuan p WHERE $sql_where", $tipe, $param)->fetch_assoc()['n'];
$total_halaman = max(1, (int)ceil($total / $per_halaman));
$halaman = min($halaman, $total_halaman);
$offset  = ($halaman - 1) * $per_halaman;

// ===== 4. Data tabel: yang masih menunggu ditampilkan paling atas =====
$rows = query_aman($conn, "
    SELECT p.*, k.nip, k.nama, j.nama_jabatan
    FROM pengajuan p
    JOIN karyawan k ON p.karyawan_id = k.id
    LEFT JOIN jabatan j ON k.jabatan_id = j.id
    WHERE $sql_where
    ORDER BY FIELD(p.status, 'Menunggu', 'Disetujui', 'Ditolak'), p.dibuat_pada DESC, p.id DESC
    LIMIT $per_halaman OFFSET $offset", $tipe, $param)->fetch_all(MYSQLI_ASSOC);

$daftar_karyawan = $conn->query("SELECT id, nip, nama FROM karyawan ORDER BY nama");
$filter_url = ['status' => $status, 'jenis' => $jenis, 'karyawan' => $karyawan];

$kartu = [
    'Menunggu'  => ['ikon' => 'bi-hourglass-split', 'warna' => 'sakit'],
    'Disetujui' => ['ikon' => 'bi-check2-circle',   'warna' => 'hadir'],
    'Ditolak'   => ['ikon' => 'bi-x-circle',        'warna' => 'alpha'],
];

include '../includes/header.php';
?>

<!-- ===== Judul ===== -->
<div class="k-head">
  <div>
    <div class="k-eyebrow">Modul Persetujuan</div>
    <h1 class="k-title">Pengajuan Izin &amp; Cuti</h1>
    <p class="k-lead">Setujui atau tolak pengajuan izin, sakit, dan cuti dari karyawan. Yang disetujui otomatis masuk rekap absensi.</p>
  </div>
</div>

<?php tampil_flash(); ?>

<!-- ===== Kartu ringkasan ===== -->
<div class="k-stats" style="grid-template-columns:repeat(4,1fr)">
  <div class="k-stat">
    <div class="k-stat-top">TOTAL PENGAJUAN <span class="k-stat-ico"><i class="bi bi-envelope-paper"></i></span></div>
    <div class="k-stat-body"><span class="k-angka"><?= $total_semua ?></span><span class="k-tag">semua status</span></div>
  </div>
  <?php foreach ($kartu as $nama_status => $k): ?>
    <div class="k-stat">
      <div class="k-stat-top"><?= strtoupper($nama_status) ?> <span class="k-stat-ico"><i class="bi <?= $k['ikon'] ?>"></i></span></div>
      <div class="k-stat-body">
        <span class="k-angka"><?= $hitung[$nama_status] ?></span>
        <span class="k-tag"><?= persen($hitung[$nama_status], $total_semua) ?>%</span>
      </div>
      <div class="k-prog"><span class="p-<?= $k['warna'] ?>" style="width:<?= persen($hitung[$nama_status], $total_semua) ?>%"></span></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- ===== Filter ===== -->
<form method="GET" class="k-card-filter" style="margin-top:0">
  <div class="row g-3 align-items-end">
    <div class="col-md-3 k-field">
      <label>Status</label>
      <label class="k-tool">
        <select name="status" onchange="this.form.submit()">
          <option value="">Semua Status</option>
          <?php foreach (STATUS_PENGAJUAN as $s): ?>
            <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $s ?> (<?= $hitung[$s] ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="col-md-3 k-field">
      <label>Jenis</label>
      <label class="k-tool">
        <select name="jenis" onchange="this.form.submit()">
          <option value="">Semua Jenis</option>
          <?php foreach (JENIS_PENGAJUAN as $j): ?>
            <option value="<?= $j ?>" <?= $jenis === $j ? 'selected' : '' ?>><?= $j ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="col-md-4 k-field">
      <label>Karyawan</label>
      <label class="k-tool"><i class="bi bi-person"></i>
        <select name="karyawan" onchange="this.form.submit()">
          <option value="0">Semua Karyawan</option>
          <?php while ($k = $daftar_karyawan->fetch_assoc()): ?>
            <option value="<?= $k['id'] ?>" <?= $karyawan == $k['id'] ? 'selected' : '' ?>><?= h($k['nama']) ?> (<?= h($k['nip']) ?>)</option>
          <?php endwhile; ?>
        </select>
      </label>
    </div>
    <div class="col-md-2">
      <a href="pengajuan.php" class="k-btn-putih w-100 justify-content-center">Reset</a>
    </div>
  </div>
</form>

<!-- ===== Tabel pengajuan ===== -->
<div class="k-panel">
  <div class="k-panel-head">
    <div>
      <h2 class="k-panel-title">Daftar Pengajuan</h2>
      <p class="k-panel-sub">Pengajuan yang menunggu keputusan ditampilkan paling atas.</p>
    </div>
  </div>

  <div class="k-table-wrap">
    <table class="k-table">
      <thead>
        <tr>
          <th>No</th><th>Karyawan</th><th>Jenis</th><th>Tanggal</th><th>Alasan</th><th>Diajukan</th><th>Status</th>
          <th style="text-align:right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) > 0): ?>
          <?php foreach ($rows as $i => $r): ?>
            <?php $hari = hari_kerja_antara($r['tanggal_mulai'], $r['tanggal_selesai']); ?>
            <tr>
              <td><?= $offset + $i + 1 ?></td>
              <td>
                <div class="k-orang">
                  <span class="k-avatar-s"><?= h(inisial($r['nama'])) ?></span>
                  <div><b><?= h($r['nama']) ?></b><small><?= h($r['nip']) ?> &middot; <?= h($r['nama_jabatan'] ?? '-') ?></small></div>
                </div>
              </td>
              <td><span class="k-badge st-<?= strtolower($r['jenis']) ?>"><?= h($r['jenis']) ?></span></td>
              <td>
                <b><?= h(rentang_tanggal($r['tanggal_mulai'], $r['tanggal_selesai'])) ?></b>
                <span class="k-alasan"><?= $hari ?> hari kerja</span>
              </td>
              <td><span class="k-alasan" style="margin-top:0;font-size:.8rem;color:inherit"><?= $r['alasan'] ? h($r['alasan']) : '-' ?></span></td>
              <td><?= date('d-m-Y H:i', strtotime($r['dibuat_pada'])) ?></td>
              <td>
                <span class="k-badge <?= kelas_status_pengajuan($r['status']) ?>"><?= h($r['status']) ?></span>
                <?php if ($r['status'] === 'Ditolak' && $r['catatan_admin']): ?>
                  <span class="k-alasan">Alasan: <?= h($r['catatan_admin']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <div class="k-aksi">
                  <?php if ($r['status'] === 'Menunggu'): ?>
                    <!-- Setujui: langsung diproses setelah konfirmasi -->
                    <form action="pengajuan_proses.php" method="POST" class="d-inline form-hapus"
                          data-konfirmasi="Setujui <?= h(strtolower($r['jenis'])) ?> <?= h($r['nama']) ?> (<?= h(rentang_tanggal($r['tanggal_mulai'], $r['tanggal_selesai'])) ?>)? Hari kerja pada rentang itu akan tercatat di rekap absensi.">
                      <input type="hidden" name="aksi" value="setujui">
                      <input type="hidden" name="id" value="<?= $r['id'] ?>">
                      <input type="hidden" name="balik" value="<?= h(http_build_query($filter_url + ['page' => $halaman])) ?>">
                      <button type="submit" class="k-aksi-btn hijau" title="Setujui"><i class="bi bi-check-lg"></i></button>
                    </form>
                    <!-- Tolak: membuka kotak untuk mengisi alasan penolakan -->
                    <button type="button" class="k-aksi-btn merah btn-tolak" title="Tolak"
                            data-bs-toggle="modal" data-bs-target="#modal-tolak"
                            data-id="<?= $r['id'] ?>"
                            data-info="<?= h($r['nama'] . ' - ' . $r['jenis'] . ' ' . rentang_tanggal($r['tanggal_mulai'], $r['tanggal_selesai'])) ?>">
                      <i class="bi bi-x-lg"></i>
                    </button>
                  <?php endif; ?>
                  <!-- Hapus riwayat: pengajuan + absensi bentukannya (riwayat karyawan ikut hilang) -->
                  <form action="pengajuan_proses.php" method="POST" class="d-inline form-hapus"
                        data-konfirmasi="Hapus riwayat <?= h(strtolower($r['jenis'])) ?> <?= h($r['nama']) ?> (<?= h(rentang_tanggal($r['tanggal_mulai'], $r['tanggal_selesai'])) ?>)? Riwayat di halaman karyawan dan rekap absensi juga ikut terhapus dan tidak bisa dikembalikan.">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <input type="hidden" name="balik" value="<?= h(http_build_query($filter_url + ['page' => $halaman])) ?>">
                    <button type="submit" class="k-aksi-btn merah" title="Hapus riwayat"><i class="bi bi-trash"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada pengajuan pada filter ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="k-panel-foot">
    <span>Menampilkan <b><?= $total > 0 ? $offset + 1 : 0 ?> - <?= min($offset + $per_halaman, $total) ?></b> dari <b><?= $total ?></b> pengajuan</span>
    <?php render_paging($halaman, $total_halaman, $filter_url); ?>
  </div>
</div>

<!-- ===== Kotak alasan penolakan ===== -->
<div class="modal fade" id="modal-tolak" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form action="pengajuan_proses.php" method="POST" class="modal-content" style="border-radius:16px">
      <input type="hidden" name="aksi" value="tolak">
      <input type="hidden" name="id" id="tolak-id">
      <input type="hidden" name="balik" value="<?= h(http_build_query($filter_url + ['page' => $halaman])) ?>">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Tolak Pengajuan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-3" id="tolak-info"></p>
        <label class="form-label fw-semibold">Alasan penolakan</label>
        <textarea name="catatan" class="form-control" rows="3" maxlength="255" required
                  placeholder="Alasan ini akan tampil di riwayat karyawan"></textarea>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <button type="submit" class="btn btn-danger">Tolak Pengajuan</button>
      </div>
    </form>
  </div>
</div>

<script>
  // Isi kotak penolakan sesuai tombol "Tolak" yang diklik
  document.getElementById('modal-tolak').addEventListener('show.bs.modal', function (e) {
    var tombol = e.relatedTarget;
    document.getElementById('tolak-id').value = tombol.dataset.id;
    document.getElementById('tolak-info').textContent = tombol.dataset.info;
    this.querySelector('textarea').value = '';
  });
</script>

<?php include '../includes/footer.php'; ?>
