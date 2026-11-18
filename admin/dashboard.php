<?php
require_once '../auth/cek_session.php';
require_once '../includes/fungsi.php';
wajib_role('admin');

// ===== 1. Ambil filter dari URL (method GET) =====
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) $tanggal = date('Y-m-d'); // validasi format
$q      = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
if (!in_array($status, ['Hadir', 'Izin', 'Sakit', 'Alpha'])) $status = '';
$halaman     = max(1, (int)($_GET['page'] ?? 1));
$per_halaman = 5; // jumlah baris per halaman

// ===== 2. Data untuk 4 kartu statistik (selalu data HARI INI) =====
$total_karyawan = $conn->query("SELECT COUNT(*) n FROM karyawan")->fetch_assoc()['n'];
$total_jabatan  = $conn->query("SELECT COUNT(*) n FROM jabatan")->fetch_assoc()['n'];
$jabatan_terpakai = $conn->query("SELECT COUNT(DISTINCT jabatan_id) n FROM karyawan")->fetch_assoc()['n'];
$hadir_hari_ini = $conn->query("SELECT COUNT(*) n FROM absensi WHERE tanggal = CURDATE() AND status = 'Hadir'")->fetch_assoc()['n'];
$tercatat_hari_ini = $conn->query("SELECT COUNT(*) n FROM absensi WHERE tanggal = CURDATE()")->fetch_assoc()['n'];
$belum_absen = max($total_karyawan - $tercatat_hari_ini, 0);
$persen_hadir = $total_karyawan > 0 ? round($hadir_hari_ini / $total_karyawan * 100) : 0;

// ===== 3. Jumlah per status pada tanggal yang dipilih (untuk isi dropdown) =====
$hitung = ['Hadir' => 0, 'Izin' => 0, 'Sakit' => 0, 'Alpha' => 0];
$stmt = $conn->prepare("SELECT status, COUNT(*) n FROM absensi WHERE tanggal = ? GROUP BY status");
$stmt->bind_param("s", $tanggal);
$stmt->execute();
foreach ($stmt->get_result() as $r) { $hitung[$r['status']] = $r['n']; }
$total_tanggal = array_sum($hitung);

// ===== 4. Susun kondisi WHERE secara dinamis sesuai filter =====
$where = ["a.tanggal = ?"]; $tipe = "s"; $param = [$tanggal];
if ($status !== '') { $where[] = "a.status = ?"; $tipe .= "s"; $param[] = $status; }
if ($q !== '') {
    $where[] = "(k.nama LIKE ? OR k.nip LIKE ?)";
    $tipe .= "ss"; $like = "%$q%"; $param[] = $like; $param[] = $like;
}
$sql_where = implode(" AND ", $where);

// Hitung total baris (untuk paginasi)
$stmt = $conn->prepare("SELECT COUNT(*) n FROM absensi a JOIN karyawan k ON a.karyawan_id = k.id WHERE $sql_where");
$stmt->bind_param($tipe, ...$param);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['n'];

$total_halaman = max(1, (int)ceil($total / $per_halaman));
$halaman = min($halaman, $total_halaman);
$offset  = ($halaman - 1) * $per_halaman;

// Ambil baris untuk halaman yang sedang dibuka
$stmt = $conn->prepare("
    SELECT a.*, k.nip, k.nama, j.nama_jabatan
    FROM absensi a
    JOIN karyawan k ON a.karyawan_id = k.id
    LEFT JOIN jabatan j ON k.jabatan_id = j.id
    WHERE $sql_where
    ORDER BY a.jam_masuk DESC
    LIMIT $per_halaman OFFSET $offset
");
$stmt->bind_param($tipe, ...$param);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fungsi membuat link paginasi dengan mempertahankan filter
function link_halaman($n) {
    global $q, $tanggal, $status;
    return '?' . http_build_query(['q' => $q, 'tanggal' => $tanggal, 'status' => $status, 'page' => $n]);
}

include '../includes/header.php';
?>

<!-- ===== Judul + tombol aksi ===== -->
<div class="k-head">
  <div>
    <h1 class="k-title">Dashboard Admin
      <!-- Halaman memuat ulang data otomatis tiap 60 detik (diatur di script.js) -->
      <span class="k-chip" id="auto-refresh"><span class="k-dot"></span>Auto-refresh 60 dtk</span>
    </h1>
    <div class="k-meta">
      Selamat datang kembali, <b><?= h(ucfirst($_SESSION['username'])) ?></b>.
      &nbsp;<i class="bi bi-calendar3"></i> <span id="k-tanggal"><?= date('d-m-Y') ?></span>
      &nbsp;<span class="k-mono" id="k-jam-now"></span>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="absensi_manual.php?tanggal=<?= h($tanggal) ?>" class="k-btn-biru"><i class="bi bi-plus-circle"></i> Catat Absensi Manual</a>
  </div>
</div>

<!-- Pesan sukses setelah mencatat absensi manual -->
<?php if (isset($_SESSION['pesan'])): ?>
  <div class="alert alert-<?= $_SESSION['pesan_tipe'] ?> alert-dismissible fade show mt-3">
    <?= $_SESSION['pesan'] ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php unset($_SESSION['pesan'], $_SESSION['pesan_tipe']); ?>
<?php endif; ?>

<!-- ===== 4 kartu statistik ===== -->
<div class="k-stats">
  <div class="k-stat">
    <div class="k-stat-top">Total Karyawan <span class="k-stat-ico"><i class="bi bi-people-fill"></i></span></div>
    <div class="k-stat-body">
      <div><span class="k-angka"><?= $total_karyawan ?></span><span class="k-satuan">orang</span></div>
      <span class="k-tag">Terdaftar</span>
    </div>
  </div>
  <div class="k-stat">
    <div class="k-stat-top">Total Jabatan <span class="k-stat-ico"><i class="bi bi-briefcase"></i></span></div>
    <div class="k-stat-body">
      <div><span class="k-angka"><?= $total_jabatan ?></span><span class="k-satuan">posisi</span></div>
      <span class="k-tag"><?= $jabatan_terpakai ?> terpakai</span>
    </div>
  </div>
  <div class="k-stat">
    <div class="k-stat-top">Hadir Hari Ini <span class="k-stat-ico hijau"><i class="bi bi-person-check-fill"></i></span></div>
    <div class="k-stat-body">
      <div><span class="k-angka hijau"><?= $hadir_hari_ini ?></span><span class="k-satuan">/ <?= $total_karyawan ?> karyawan</span></div>
      <span class="k-tag hijau"><?= $persen_hadir ?>% rate</span>
    </div>
  </div>
  <div class="k-stat">
    <div class="k-stat-top">Belum Absen <span class="k-stat-ico"><i class="bi bi-clock"></i></span></div>
    <div class="k-stat-body">
      <div><span class="k-angka"><?= $belum_absen ?></span><span class="k-satuan">karyawan</span></div>
      <span class="k-tag">Belum check-in</span>
    </div>
  </div>
</div>

<!-- ===== Panel tabel absensi ===== -->
<div class="k-panel">
  <div class="k-panel-head">
    <div>
      <h2 class="k-panel-title">Absensi Hari Ini</h2>
      <p class="k-panel-sub">Catatan kehadiran karyawan pada tanggal yang dipilih</p>
    </div>

    <!-- Form filter: otomatis terkirim saat nilai diubah (diatur di script.js) -->
    <form method="GET" id="form-filter" class="k-tools">
      <label class="k-tool"><i class="bi bi-search"></i>
        <input type="text" id="input-cari" name="q" value="<?= h($q) ?>" placeholder="Cari NIP atau Nama...">
      </label>
      <label class="k-tool"><i class="bi bi-calendar3"></i>
        <input type="date" name="tanggal" value="<?= h($tanggal) ?>" data-auto>
      </label>
      <label class="k-tool">
        <select name="status" data-auto>
          <option value="">Semua (<?= $total_tanggal ?>)</option>
          <?php foreach ($hitung as $nama_status => $jumlah): ?>
            <option value="<?= $nama_status ?>" <?= $status === $nama_status ? 'selected' : '' ?>>
              <?= $nama_status ?> (<?= $jumlah ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    </form>
  </div>

  <div class="k-table-wrap">
    <table class="k-table">
      <thead>
        <tr>
          <th>NIP</th><th>Nama Karyawan</th><th>Jam Masuk</th><th>Jam Pulang</th>
          <th>Status Kehadiran</th><th style="text-align:right">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($rows) > 0): ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><span class="k-nip"><?= h($r['nip']) ?></span></td>

              <!-- Avatar inisial + nama + jabatan -->
              <td>
                <div class="k-orang">
                  <span class="k-avatar-s"><?= h(inisial($r['nama'])) ?></span>
                  <div><b><?= h($r['nama']) ?></b><small><?= h($r['nama_jabatan'] ?? '-') ?></small></div>
                </div>
              </td>

              <!-- Jam masuk + keterangan: dot HIJAU = tepat waktu, dot MERAH = terlambat -->
              <td>
                <div class="k-jam"><?= $r['jam_masuk'] ?? '-' ?></div>
                <?php if ($r['jam_masuk']): $telat = terlambat($r['jam_masuk']); ?>
                  <div class="k-ket <?= $telat ? 'telat' : 'tepat' ?>">
                    <span class="dot"></span><?= $telat ? 'Terlambat' : 'Tepat Waktu' ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- Jam pulang + keterangan: sudah pulang / sedang bekerja -->
              <td>
                <div class="k-jam"><?= $r['jam_pulang'] ?? '-' ?></div>
                <?php if ($r['jam_pulang']): ?>
                  <div class="k-ket netral">Sudah Pulang</div>
                <?php elseif ($r['jam_masuk']): ?>
                  <div class="k-ket netral">Sedang Bekerja</div>
                <?php endif; ?>
              </td>

              <!-- Warna lencana mengikuti status: st-hadir / st-izin / st-sakit / st-alpha -->
              <td><span class="k-badge st-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>

              <td>
                <div class="k-aksi">
                  <!-- Ikon mata: lihat rekap karyawan ini -->
                  <a class="k-aksi-btn" title="Lihat rekap"
                     href="rekap_absensi.php?karyawan=<?= $r['karyawan_id'] ?>">
                    <i class="bi bi-eye"></i>
                  </a>
                  <!-- Ikon titik tiga: menu tambahan -->
                  <div class="dropdown">
                    <button class="k-aksi-btn" data-bs-toggle="dropdown"><i class="bi bi-three-dots-vertical"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                      <li><a class="dropdown-item" href="absensi_manual.php?karyawan=<?= $r['karyawan_id'] ?>&tanggal=<?= h($tanggal) ?>">Ubah absensi</a></li>
                      <li><a class="dropdown-item" href="karyawan/edit.php?id=<?= $r['karyawan_id'] ?>">Edit data karyawan</a></li>
                    </ul>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data absensi pada filter ini.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Footer panel: keterangan jumlah + paginasi -->
  <div class="k-panel-foot">
    <span>
      Menampilkan <b><?= $total > 0 ? $offset + 1 : 0 ?>-<?= min($offset + $per_halaman, $total) ?></b>
      dari <b><?= $total ?></b> data absensi
    </span>
    <div class="k-paging">
      <?php if ($halaman > 1): ?><a href="<?= link_halaman($halaman - 1) ?>">Sebelumnya</a>
      <?php else: ?><span class="mati">Sebelumnya</span><?php endif; ?>

      <?php for ($i = 1; $i <= $total_halaman; $i++): ?>
        <?php if ($i == $halaman): ?><span class="aktif"><?= $i ?></span>
        <?php else: ?><a href="<?= link_halaman($i) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>

      <?php if ($halaman < $total_halaman): ?><a href="<?= link_halaman($halaman + 1) ?>">Berikutnya</a>
      <?php else: ?><span class="mati">Berikutnya</span><?php endif; ?>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>