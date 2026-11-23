document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.form-hapus').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!confirm('Yakin ingin menghapus data ini?')) {
        e.preventDefault();
      }
    });
  });
});

// Pencarian tabel secara langsung (tanpa reload halaman)
document.addEventListener('DOMContentLoaded', function () {
  var kotakCari = document.getElementById('cari');
  if (!kotakCari) return; // halaman ini tidak punya kotak pencarian, hentikan

  kotakCari.addEventListener('input', function () {
    var kata = kotakCari.value.toLowerCase();
    // Cek setiap baris tabel: tampilkan jika ada teks yang cocok, sembunyikan jika tidak
    document.querySelectorAll('#tabel-data tbody tr').forEach(function (baris) {
      baris.style.display = baris.textContent.toLowerCase().includes(kata) ? '' : 'none';
    });
  });
});

// Jam berjalan untuk halaman absen
document.addEventListener('DOMContentLoaded', function () {
  var elemenJam = document.getElementById('jam');
  if (!elemenJam) return; // halaman ini tidak punya elemen jam, hentikan

  function perbaruiJam() {
    var sekarang = new Date();
    // toLocaleTimeString memformat jam sesuai pengaturan Indonesia (jam:menit:detik)
    elemenJam.textContent = sekarang.toLocaleTimeString('id-ID');
  }

  perbaruiJam();                  // tampilkan langsung saat halaman dibuka
  setInterval(perbaruiJam, 1000); // lalu perbarui setiap 1 detik
});

// ===== Dashboard admin: tanggal/jam real-time, filter otomatis, auto-refresh =====
document.addEventListener('DOMContentLoaded', function () {

  // 1) Tanggal dan jam berjalan (mengikuti jam perangkat)
  var elTanggal = document.getElementById('k-tanggal');
  var elJam = document.getElementById('k-jam-now');
  if (elTanggal && elJam) {
    function tick() {
      var s = new Date();
      elTanggal.textContent = s.toLocaleDateString('id-ID',
        { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
      // format id-ID memakai titik (19.05.29), diganti titik dua
      elJam.textContent = s.toLocaleTimeString('id-ID', { hour12: false }).replace(/\./g, ':') + ' WIB';
    }
    tick();
    setInterval(tick, 1000);
  }

  // 2) Filter: form terkirim otomatis saat tanggal/status diubah
  var form = document.getElementById('form-filter');
  if (form) {
    form.querySelectorAll('[data-auto]').forEach(function (el) {
      el.addEventListener('change', function () { form.submit(); });
    });

    // Kotak pencarian: kirim 0,6 detik setelah berhenti mengetik (debounce)
    var cari = document.getElementById('input-cari');
    var timer;
    cari.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () { form.submit(); }, 600);
    });

    // Setelah halaman dimuat ulang, kembalikan kursor ke akhir teks pencarian
    if (cari.value !== '') {
      cari.focus();
      cari.setSelectionRange(cari.value.length, cari.value.length);
    }
  }

  // 3) Auto-refresh tiap 60 detik (dilewati kalau sedang mengetik di kotak pencarian)
  if (document.getElementById('auto-refresh')) {
    setInterval(function () {
      if (document.activeElement && document.activeElement.id === 'input-cari') return;
      location.reload();
    }, 60000);
  }
});

// ===== Form: tombol mata password dan input kode huruf besar =====
document.addEventListener('DOMContentLoaded', function () {

  // Tombol mata: atribut data-target berisi id input password yang dikendalikan
  document.querySelectorAll('.toggle-pass').forEach(function (tombol) {
    tombol.addEventListener('click', function () {
      var input = document.getElementById(tombol.dataset.target);
      var tampil = input.type === 'password';
      input.type = tampil ? 'text' : 'password';
      tombol.innerHTML = tampil ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });
  });

  // Input dengan atribut data-upper: otomatis huruf besar, hanya huruf/angka/strip
  document.querySelectorAll('[data-upper]').forEach(function (input) {
    input.addEventListener('input', function () {
      input.value = input.value.toUpperCase().replace(/[^A-Z0-9-]/g, '');
    });
  });
});