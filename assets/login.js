document.addEventListener('DOMContentLoaded', function () {

  // ===== 1. Tombol mata: tampil/sembunyikan password =====
  var inputPass = document.getElementById('password');
  var tombolMata = document.getElementById('toggle-pass');

  tombolMata.addEventListener('click', function () {
    var tampil = inputPass.type === 'password';          // sekarang tersembunyi?
    inputPass.type = tampil ? 'text' : 'password';       // ganti jenis input
    // ganti ikon mata sesuai kondisi
    tombolMata.innerHTML = tampil ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
  });

  // ===== 2. Kamus terjemahan ID / EN =====
  // Kunci sama dengan atribut data-i18n (teks) dan data-i18n-ph (placeholder) di login.php
  var teks = {
    id: {
      status: 'Sistem Online Aktif', judul: 'Masuk ke Akun',
      subjudul: 'Sistem Informasi Manajemen dan Absensi',
      error: 'Username atau password salah.',
      label_user: 'Username', label_pass: 'Password', lupa: 'Lupa Sandi?',
      ingat: 'Ingat saya', tombol: 'Masuk ke Sistem',
      aman: 'Kata sandi tersimpan terenkripsi', bantuan_hrd: 'Bantuan HRD',
      hak_cipta: 'Seluruh hak cipta dilindungi.',
      f_bantuan: 'Bantuan', f_privasi: 'Kebijakan Privasi', f_syarat: 'Syarat & Ketentuan',
      ph_user: 'Masukkan username', ph_pass: 'Masukkan kata sandi'
    },
    en: {
      status: 'System Online', judul: 'Sign In',
      subjudul: 'Management and Attendance Information System',
      error: 'Incorrect username or password.',
      label_user: 'Username', label_pass: 'Password', lupa: 'Forgot password?',
      ingat: 'Remember me', tombol: 'Sign In to System',
      aman: 'Password is stored encrypted', bantuan_hrd: 'HR Help',
      hak_cipta: 'All rights reserved.',
      f_bantuan: 'Help', f_privasi: 'Privacy Policy', f_syarat: 'Terms & Conditions',
      ph_user: 'Enter username', ph_pass: 'Enter password'
    }
  };

  // Fungsi: terapkan bahasa ke seluruh elemen yang punya atribut data-i18n
  function setBahasa(bahasa) {
    document.querySelectorAll('[data-i18n]').forEach(function (el) {
      el.textContent = teks[bahasa][el.dataset.i18n];
    });
    document.querySelectorAll('[data-i18n-ph]').forEach(function (el) {
      el.placeholder = teks[bahasa][el.dataset.i18nPh];
    });
    // tandai tombol bahasa yang sedang aktif
    document.querySelectorAll('.k-lang button').forEach(function (tb) {
      tb.classList.toggle('active', tb.dataset.lang === bahasa);
    });
    localStorage.setItem('bahasa', bahasa); // ingat pilihan bahasa di browser
  }

  document.querySelectorAll('.k-lang button').forEach(function (tb) {
    tb.addEventListener('click', function () { setBahasa(tb.dataset.lang); });
  });

  // Saat halaman dibuka, pakai bahasa terakhir yang dipilih (default: Indonesia)
  setBahasa(localStorage.getItem('bahasa') || 'id');

  // ===== 3. Tautan "Lupa Sandi?" dan "Bantuan HRD" =====
  // Sistem belum punya fitur reset password, jadi arahkan ke admin
  function infoAdmin(e) {
    e.preventDefault(); // cegah tautan berpindah halaman
    alert(localStorage.getItem('bahasa') === 'en'
      ? 'Please contact the HR admin to reset your password.'
      : 'Silakan hubungi admin HRD untuk mereset password Anda.');
  }
  document.getElementById('lupa-sandi').addEventListener('click', infoAdmin);
  document.getElementById('bantuan-hrd').addEventListener('click', infoAdmin);
});