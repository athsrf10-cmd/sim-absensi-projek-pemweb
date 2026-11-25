<?php
// Daftar status absensi yang valid (dipakai untuk validasi dan dropdown)
const DAFTAR_STATUS = ['Hadir', 'Izin', 'Sakit', 'Alpha', 'Cuti'];

// Jenis pengajuan yang bisa diajukan karyawan, dan status proses pengajuan
const JENIS_PENGAJUAN  = ['Izin', 'Sakit', 'Cuti'];
const STATUS_PENGAJUAN = ['Menunggu', 'Disetujui', 'Ditolak'];
const MAKS_HARI_PENGAJUAN = 60; // batas rentang tanggal satu pengajuan (hari kalender)

// Singkatan htmlspecialchars: mencegah kode berbahaya (XSS) tampil di halaman
function h($teks) {
    return htmlspecialchars($teks ?? '');
}

// Ambil inisial nama untuk avatar. "Athaa Syariif" -> "AS"
function inisial($nama) {
    $kata = preg_split('/\s+/', trim($nama));
    $hasil = strtoupper(substr($kata[0], 0, 1));
    if (count($kata) > 1) {
        $hasil .= strtoupper(substr($kata[1], 0, 1));
    }
    return $hasil;
}

// Cek terlambat: true jika jam masuk melewati batas (jam resmi + toleransi)
function terlambat($jam_masuk) {
    $batas = strtotime(JAM_MASUK) + (TOLERANSI_MENIT * 60);
    return strtotime($jam_masuk) > $batas;
}

// Hitung durasi kerja dari jam masuk dan jam pulang -> "09:08:28"
function durasi($masuk, $pulang) {
    if (!$masuk || !$pulang) return '-';
    $detik = strtotime($pulang) - strtotime($masuk);
    if ($detik < 0) return '-';
    return sprintf('%02d:%02d:%02d', intdiv($detik, 3600), intdiv($detik % 3600, 60), $detik % 60);
}

// Hitung persentase (aman dari pembagian dengan nol)
function persen($bagian, $total) {
    return $total > 0 ? round($bagian / $total * 100, 1) : 0;
}

// Pastikan teks berformat tanggal YYYY-MM-DD, kalau tidak pakai nilai default
function bersihkan_tanggal($teks, $default) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $teks) ? $teks : $default;
}

// Jalankan query dengan prepared statement. Kalau tidak ada parameter, bind dilewati
function query_aman($conn, $sql, $tipe = '', $param = []) {
    $stmt = $conn->prepare($sql);
    if ($tipe !== '') {
        $stmt->bind_param($tipe, ...$param); // ... memasukkan isi array sebagai argumen
    }
    $stmt->execute();
    return $stmt->get_result();
}

// Susun kondisi WHERE untuk rekap absensi. Dipakai halaman rekap DAN export CSV
// supaya hasil export selalu sama dengan yang tampil di tabel
function filter_rekap($dari, $sampai, $karyawan, $status) {
    $where = ["a.tanggal BETWEEN ? AND ?"];
    $tipe  = "ss";
    $param = [$dari, $sampai];
    if ($karyawan > 0) { $where[] = "a.karyawan_id = ?"; $tipe .= "i"; $param[] = $karyawan; }
    if ($status !== '') { $where[] = "a.status = ?";      $tipe .= "s"; $param[] = $status; }
    return [implode(" AND ", $where), $tipe, $param];
}

// Tampilkan pesan sukses/gagal dari session, lalu hapus supaya tidak muncul lagi
function tampil_flash() {
    if (!isset($_SESSION['pesan'])) return;
    echo '<div class="alert alert-' . h($_SESSION['pesan_tipe']) . ' alert-dismissible fade show">'
       . h($_SESSION['pesan'])
       . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    unset($_SESSION['pesan'], $_SESSION['pesan_tipe']);
}

// Cetak tombol paginasi. $params = filter yang harus ikut terbawa di link
function render_paging($halaman, $total_halaman, $params) {
    $link = function ($n) use ($params) {
        return '?' . http_build_query(array_merge($params, ['page' => $n]));
    };
    echo '<div class="k-paging">';

    // Tombol sebelumnya
    echo $halaman > 1
        ? '<a href="' . $link($halaman - 1) . '"><i class="bi bi-chevron-left"></i></a>'
        : '<span class="mati"><i class="bi bi-chevron-left"></i></span>';

    // Tampilkan 2 halaman di kiri-kanan halaman aktif, sisanya diringkas "..."
    $awal  = max(1, $halaman - 2);
    $akhir = min($total_halaman, $halaman + 2);
    if ($awal > 1) {
        echo '<a href="' . $link(1) . '">1</a>';
        if ($awal > 2) echo '<span class="mati">...</span>';
    }
    for ($i = $awal; $i <= $akhir; $i++) {
        echo $i == $halaman
            ? '<span class="aktif">' . $i . '</span>'
            : '<a href="' . $link($i) . '">' . $i . '</a>';
    }
    if ($akhir < $total_halaman) {
        if ($akhir < $total_halaman - 1) echo '<span class="mati">...</span>';
        echo '<a href="' . $link($total_halaman) . '">' . $total_halaman . '</a>';
    }

    // Tombol berikutnya
    echo $halaman < $total_halaman
        ? '<a href="' . $link($halaman + 1) . '"><i class="bi bi-chevron-right"></i></a>'
        : '<span class="mati"><i class="bi bi-chevron-right"></i></span>';
    echo '</div>';
}


// Hitung jumlah hari kerja (Senin-Jumat) antara dua tanggal (format YYYY-MM-DD).
// Tanggal awal dan akhir sama-sama ikut dihitung
function hari_kerja_antara($awal, $akhir) {
    $jumlah = 0;
    for ($t = strtotime($awal); $t <= strtotime($akhir); $t += 86400) {
        if ((int)date('N', $t) <= 5) $jumlah++;   // date('N'): 1 = Senin ... 7 = Minggu
    }
    return $jumlah;
}

// Teks jam kerja standar untuk ditampilkan, diambil dari JAM_MASUK dan JAM_PULANG di koneksi.php.
// Hasilnya HTML (memakai &ndash;), contoh: 08:00 &ndash; 17:00 WIB
function jam_kerja_teks() {
    return substr(JAM_MASUK, 0, 5) . ' &ndash; ' . substr(JAM_PULANG, 0, 5) . ' WIB';
}

// Format tanggal d-m-Y; kalau mulai = selesai tampil satu tanggal saja
function rentang_tanggal($mulai, $selesai) {
    $a = date('d-m-Y', strtotime($mulai));
    $b = date('d-m-Y', strtotime($selesai));
    return $a === $b ? $a : $a . ' s/d ' . $b;
}

// Kelas warna lencana status pengajuan: Menunggu / Disetujui / Ditolak
function kelas_status_pengajuan($status) {
    return 'pg-' . strtolower($status);
}

// Jumlah pengajuan yang menunggu keputusan admin (untuk lencana di menu)
function jumlah_pengajuan_menunggu($conn) {
    $r = $conn->query("SELECT COUNT(*) n FROM pengajuan WHERE status = 'Menunggu'");
    return $r ? (int)$r->fetch_assoc()['n'] : 0;
}
