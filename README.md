# Karyon — Sistem Manajemen dan Absensi Karyawan

Aplikasi web untuk mengelola data karyawan dan mencatat kehadiran harian. Dibangun dengan **PHP native (MySQLi)**, **MariaDB/MySQL**, dan **Bootstrap 5**. Ada dua peran pengguna: **Admin (HR)** yang mengelola data dan memantau absensi, serta **Karyawan** yang absen, melihat riwayat, dan mengajukan izin, sakit, atau cuti.

## Fitur

### Admin
- **Dashboard**: ringkasan jumlah karyawan, jabatan, kehadiran hari ini, dan daftar absensi per tanggal (dengan pencarian, filter status, dan auto-refresh).
- **Manajemen Karyawan**: tambah, ubah, hapus karyawan beserta akun login-nya.
- **Manajemen Jabatan**: tambah, ubah, hapus jabatan (kode jabatan unik).
- **Rekap Absensi**: laporan per periode dengan filter tanggal, karyawan, dan status; ringkasan jumlah per status; durasi kerja; keterlambatan; ekspor CSV; cetak.
- **Catat Absensi Manual**: input atau koreksi absensi karyawan pada tanggal tertentu.
- **Pengajuan Izin & Cuti**: menyetujui atau menolak pengajuan karyawan, serta menghapus riwayat pengajuan.

### Karyawan
- **Dashboard**: sapaan, status absen hari ini, dan aktivitas presensi terbaru.
- **Absen**: absen masuk dan pulang (waktu diambil dari server).
- **Riwayat**: log kehadiran per bulan dengan statistik, pencarian, filter status, ekspor CSV, dan cetak. Menampilkan juga keterangan izin/cuti dan daftar pengajuan beserta status serta alasan penolakannya.
- **Pengajuan Izin & Cuti**: mengajukan izin, sakit, atau cuti dan memantau statusnya.
- **Profil** dan **Ganti Password**.

## Alur Sistem

### 1. Login dan Hak Akses

1. Pengguna membuka alamat aplikasi. `index.php` mengarahkan ke halaman login jika belum masuk, atau ke dashboard sesuai peran jika sudah.
2. Pengguna mengisi username dan password di `auth/login.php`. Data dikirim ke `auth/proses_login.php`.
3. Sistem mencari username di tabel `users`, lalu mencocokkan password dengan hash-nya (`password_verify`).
4. Jika cocok, sesi dibuat ulang (`session_regenerate_id`) dan menyimpan `user_id`, `username`, `role`, serta `karyawan_id`. Admin diarahkan ke `admin/dashboard.php`, karyawan ke `karyawan/dashboard.php`.
5. Jika salah, pengguna kembali ke halaman login dengan pesan gagal.
6. Setiap halaman memanggil `wajib_role()`. Pengguna yang belum login dikembalikan ke login, dan pengguna yang membuka halaman milik peran lain dialihkan ke dashboard miliknya sendiri. Karyawan tidak bisa membuka halaman admin, begitu juga sebaliknya.
7. Tombol **Keluar** (`auth/logout.php`) menghapus sesi lalu kembali ke halaman login.

### 2. Absensi Harian Karyawan

1. Karyawan membuka menu **Absen**. Identitas karyawan diambil dari sesi login, bukan dari form, sehingga tidak bisa mengabsenkan orang lain.
2. Tombol **Absen Masuk** mencatat jam masuk memakai waktu server (WIB) dengan status *Hadir*. Absen masuk hanya bisa sekali per hari.
3. Absen masuk setelah jam masuk + toleransi (lihat Pengaturan Jam Kerja) ditandai **Terlambat**. Kalau tidak, ditandai **Tepat Waktu**.
4. Setelah absen masuk, tombol **Absen Pulang** aktif dan mencatat jam pulang. Durasi kerja dihitung dari jam masuk sampai jam pulang.
5. Jika pada hari itu sudah ada status *Izin*, *Sakit*, *Alpha*, atau *Cuti* (dari pengajuan yang disetujui atau dicatat admin), tombol absen tidak ditampilkan dan karyawan tidak perlu absen.
6. Hasilnya langsung terlihat di **Dashboard** karyawan (aktivitas terbaru) dan **Riwayat** karyawan, serta di **Dashboard** dan **Rekap Absensi** admin.

### 3. Pengelolaan Data oleh Admin

**Jabatan**
1. Admin membuka **Jabatan**, lalu menambah jabatan dengan kode dan nama. Kode otomatis huruf besar dan harus unik (huruf, angka, strip; 2-20 karakter).
2. Jabatan bisa diubah. Jabatan yang masih dipakai karyawan tidak bisa dihapus.

**Karyawan**
1. Admin membuka **Karyawan** dan menambah karyawan: NIP, nama, jabatan, no. HP, alamat, serta username dan password untuk akun login. Satu kali simpan membuat data karyawan sekaligus akun berperan *karyawan*.
2. NIP dan username harus unik. No. HP harus 8-15 digit angka. Password minimal 6 karakter dan disimpan dalam bentuk hash.
3. Saat mengubah data, kolom password boleh dikosongkan jika tidak ingin diganti.
4. Menghapus karyawan ikut menghapus akun login, seluruh absensi, dan seluruh pengajuannya.

### 4. Pemantauan dan Rekap Absensi

1. **Dashboard admin** menampilkan jumlah karyawan, jabatan, dan kehadiran, serta daftar absensi per tanggal dengan pencarian dan filter status. Halaman menyegarkan diri otomatis tiap 60 detik.
2. **Rekap Absensi** menampilkan data per periode. Admin memfilter berdasarkan tanggal, karyawan, dan status. Kartu di atas tabel meringkas jumlah per status beserta rasionya.
3. Dari hasil filter, admin bisa **Export Rekap** ke CSV atau **Cetak Rekap** (semua baris tanpa paginasi).
4. Tiap baris punya tombol **ubah** (membuka form absensi manual) dan **hapus**. Menghapus absensi juga menghapus riwayatnya di halaman karyawan.
5. Secara bawaan periode rekap berakhir di hari ini. Untuk melihat izin atau cuti yang sudah disetujui di hari mendatang, perpanjang tanggal "Sampai".

### 5. Catat Absensi Manual

1. Admin membuka form ini lewat tombol **Catat Absensi Manual** di Dashboard, menu **Ubah absensi** pada baris dashboard, atau tombol ubah di Rekap Absensi, lalu memilih karyawan dan tanggal.
2. Admin mengisi status (*Hadir*, *Izin*, *Sakit*, *Alpha*, atau *Cuti*). Jam masuk dan pulang hanya dipakai untuk *Hadir*. Keterangan hanya dipakai untuk *Izin* dan *Cuti*.
3. Jika karyawan sudah punya absensi di tanggal itu, datanya diperbarui, bukan digandakan (satu karyawan hanya punya satu baris per tanggal).

### 6. Pengajuan Izin, Sakit & Cuti

1. Karyawan membuka menu **Pengajuan Izin & Cuti**, memilih jenis (Izin / Sakit / Cuti), rentang tanggal, dan alasan, lalu mengirim. Status awal: **Menunggu**. Pengajuan yang masih menunggu bisa dibatalkan oleh karyawan.
2. Admin membuka menu **Pengajuan Izin & Cuti** (angka merah di menu = jumlah yang menunggu), lalu memilih:
   - **Setujui** — setiap hari kerja (Senin–Jumat) pada rentang tanggal tercatat otomatis di rekap absensi dengan status Izin / Sakit / Cuti. Izin dan Cuti membawa keterangan alasannya; Sakit tanpa keterangan. Hari yang sudah berstatus *Hadir* tidak ditimpa.
   - **Tolak** — admin wajib mengisi alasan penolakan, yang tampil di halaman karyawan.
3. Hasilnya terlihat di:
   - **Riwayat karyawan**: baris absensi dengan status dan keterangan (jika disetujui), serta tabel pengajuan dengan status dan alasan penolakan (jika ditolak).
   - **Rekap absensi admin**: status Izin / Sakit / Cuti beserta keterangannya.
4. Admin dapat **menghapus riwayat** pengajuan. Baris absensi yang terbentuk dari pengajuan itu ikut terhapus, sehingga riwayat di halaman karyawan dan rekap admin ikut hilang.

Aturan pengajuan (bisa diubah di `includes/fungsi.php` dan `karyawan/pengajuan.php`):
- Cuti harus dimulai hari ini atau sesudahnya; izin dan sakit boleh sampai 7 hari ke belakang.
- Satu pengajuan maksimal 60 hari kalender (`MAKS_HARI_PENGAJUAN`).
- Alasan wajib untuk Izin dan Cuti, opsional untuk Sakit.
- Tanggal tidak boleh bertabrakan dengan pengajuan lain yang berstatus Menunggu atau Disetujui.
- Baris absensi yang kemudian diubah lewat *Catat Absensi Manual* dilepas dari pengajuan asalnya, jadi tidak ikut terhapus saat pengajuan dihapus.

### 7. Profil dan Ganti Password Karyawan

1. Di **Profil**, karyawan dapat mengubah nama, no. HP, dan alamat. NIP dan jabatan hanya bisa diubah admin.
2. Di **Ganti Kata Sandi**, karyawan mengisi kata sandi saat ini, kata sandi baru, dan konfirmasi. Kata sandi baru minimal 8 karakter, memuat huruf besar, huruf kecil, dan angka, serta tidak boleh sama dengan yang lama.
3. Kedua form dilindungi token CSRF.

## Teknologi

| Bagian | Teknologi |
|---|---|
| Bahasa | PHP 8+ (MySQLi, prepared statement) |
| Database | MariaDB / MySQL (`sim_absensi`) |
| Antarmuka | Bootstrap 5.3, Bootstrap Icons, font Inter dan Plus Jakarta Sans (via CDN) |
| Server lokal | XAMPP (Apache + MySQL) |

> Bootstrap dan font dimuat dari CDN, jadi tampilan lengkap membutuhkan koneksi internet.

## Instalasi

1. Salin folder proyek ke `htdocs`, sehingga alamatnya `C:\xampp\htdocs\sistem-absensi`.
2. Jalankan **Apache** dan **MySQL** di XAMPP.
3. Buka phpMyAdmin, buat database **`sim_absensi`**, lalu **Import** file `sim_absensi.sql`.
4. **Fitur pengajuan**: jika database Anda dibuat dari `sim_absensi.sql` versi lama, jalankan `database/migrasi_pengajuan.sql` **satu kali** (tab SQL di phpMyAdmin). Skrip ini menambah tabel `pengajuan`, status `Cuti`, serta kolom `keterangan` dan `pengajuan_id` pada tabel `absensi`.
5. Cek `config/koneksi.php` dan sesuaikan bila perlu:
   ```php
   define('BASE_URL', '/sistem-absensi/');                    // sesuaikan dengan nama folder
   $conn = new mysqli("localhost", "root", "", "sim_absensi"); // host, user, password, nama database
   ```
6. Buka `http://localhost/sistem-absensi/` dan login.

### Akun

Data awal berisi akun `admin` (role admin) dan akun karyawan `athaa`, `aziel`, `budi`, `messi`. Password disimpan dalam bentuk hash bcrypt, sehingga tidak bisa dibaca dari database. Untuk mengganti password sebuah akun, buat hash baru:

```bash
php -r "echo password_hash('PasswordBaru', PASSWORD_DEFAULT);"
```

lalu tempel hasilnya ke kolom `password` tabel `users` di phpMyAdmin.

## Pengaturan Jam Kerja

Ubah di `config/koneksi.php`:

```php
define('JAM_MASUK', '08:00:00');   // jam masuk resmi
define('TOLERANSI_MENIT', 15);     // toleransi keterlambatan (menit)
define('JAM_PULANG', '17:00:00');  // jam pulang resmi
```

Absen masuk setelah jam masuk + toleransi dihitung **terlambat**. Zona waktu PHP dan MySQL diatur ke WIB (UTC+7).

## Struktur Folder

```
sistem-absensi/
├── index.php                      # pengarah: ke login / dashboard sesuai peran
├── README.md
├── database/
│   └── sim_absensi.sql     
│
├── config/
│   └── koneksi.php                # koneksi database, BASE_URL, aturan jam kerja
│
├── auth/
│   ├── login.php                  # halaman login
│   ├── proses_login.php           # verifikasi username dan password
│   ├── logout.php                 # hapus sesi lalu kembali ke login
│   └── cek_session.php            # wajib_login() dan wajib_role()
│
├── includes/
│   ├── header.php                 # navbar admin (dipakai halaman admin)
│   ├── footer.php                 # footer dan pemuat script
│   └── fungsi.php                 # fungsi bantu dan konstanta status
│
├── assets/
│   ├── style.css                  # gaya dasar
│   ├── karyon.css                 # gaya utama aplikasi (komponen k-*)
│   ├── login.css                  # gaya halaman login
│   ├── login.js                   # perilaku halaman login
│   └── script.js                  # konfirmasi hapus, jam berjalan, filter otomatis
│
├── admin/
│   ├── dashboard.php              # ringkasan dan absensi per tanggal
│   ├── rekap_absensi.php          # rekap per periode, filter, cetak
│   ├── export_csv.php             # ekspor absensi ke CSV
│   ├── absensi_manual.php         # catat atau koreksi absensi
│   ├── hapus_absensi.php          # hapus satu baris absensi
│   ├── pengajuan.php              # daftar pengajuan: setujui / tolak / hapus
│   ├── pengajuan_proses.php       # proses aksi pengajuan
│   ├── karyawan/
│   │   ├── index.php              # daftar karyawan
│   │   ├── tambah.php             # tambah karyawan dan akun login
│   │   ├── edit.php               # ubah data karyawan
│   │   └── hapus.php              # hapus karyawan beserta akun dan riwayatnya
│   └── jabatan/
│       ├── index.php              # daftar jabatan
│       ├── tambah.php             # tambah jabatan
│       ├── edit.php               # ubah jabatan
│       └── hapus.php              # hapus jabatan
│
└── karyawan/
    ├── dashboard.php              # ringkasan dan aktivitas terbaru
    ├── absen.php                  # absen masuk dan pulang
    ├── riwayat.php                # log kehadiran dan daftar pengajuan
    ├── pengajuan.php              # form dan riwayat pengajuan izin & cuti
    ├── profil.php                 # data diri karyawan
    └── ganti_password.php         # ubah password
```

Catatan: halaman karyawan memuat tampilannya sendiri (CSS di dalam file), sedangkan halaman admin memakai `includes/header.php`, `includes/footer.php`, dan `assets/karyon.css`.

## Struktur Database

| Tabel | Isi |
|---|---|
| `users` | Akun login: `username`, `password` (hash), `role` (`admin`/`karyawan`), `karyawan_id` |
| `karyawan` | `nip` (unik), `nama`, `jabatan_id`, `no_hp`, `alamat` |
| `jabatan` | `kode_jabatan` (unik), `nama_jabatan` |
| `absensi` | Satu baris per karyawan per tanggal (unik): `jam_masuk`, `jam_pulang`, `status` (`Hadir`/`Izin`/`Sakit`/`Alpha`/`Cuti`), `keterangan`, `pengajuan_id` |
| `pengajuan` | `karyawan_id`, `jenis` (`Izin`/`Sakit`/`Cuti`), `tanggal_mulai`, `tanggal_selesai`, `alasan`, `status` (`Menunggu`/`Disetujui`/`Ditolak`), `catatan_admin`, `dibuat_pada`, `diproses_pada` |

Relasi: `users.karyawan_id`, `absensi.karyawan_id`, dan `pengajuan.karyawan_id` mengacu ke `karyawan.id`; `karyawan.jabatan_id` ke `jabatan.id`; `absensi.pengajuan_id` ke `pengajuan.id` (`ON DELETE CASCADE`).

## Catatan Keamanan

- Password memakai `password_hash` / `password_verify`.
- Semua query yang membawa input pengguna memakai prepared statement; keluaran HTML di-escape dengan `h()`.
- Halaman dilindungi per peran lewat `wajib_role()`; aksi hapus dan proses hanya menerima metode POST.
- Form pengajuan karyawan dilindungi token CSRF.
- Sebelum dipakai di server publik: ganti password bawaan, gunakan user database khusus (bukan `root` tanpa password), dan aktifkan HTTPS.