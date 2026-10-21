/**
 * Pembaruan data real time untuk SIMUSREN.
 *
 * Cara pakai di halaman:
 *   <div id="nama-unik" data-langsung> ...bagian data... </div>
 *   <span data-langsung-status></span>          (opsional, penanda status)
 *   <script src="assets/langsung.js" data-api="api/versi.php" data-jeda="5000"></script>
 *
 * Setiap beberapa detik skrip menanyakan versi data ke server. Bila berubah,
 * halaman yang sama diambil ulang di latar belakang dan hanya bagian
 * [data-langsung] yang diganti, sehingga penyaring dan posisi gulir tetap.
 */
(function () {
  'use strict';

  var skrip = document.currentScript;
  var api = skrip.getAttribute('data-api') || 'api/versi.php';
  var jeda = parseInt(skrip.getAttribute('data-jeda'), 10) || 5000;

  var area = Array.prototype.slice.call(document.querySelectorAll('[data-langsung]'));
  var penanda = Array.prototype.slice.call(document.querySelectorAll('[data-langsung-status]'));
  if (!area.length) { return; }

  var versi = null;
  var sibuk = false;
  var berhenti = false;
  var tertunda = false;
  var pewaktu = null;

  /* Isian yang sedang diubah pengguna di dalam area tidak boleh tertimpa */
  area.forEach(function (a) {
    ['input', 'change'].forEach(function (ev) {
      a.addEventListener(ev, function (e) {
        if (e.target.matches('input, select, textarea')) { a.setAttribute('data-kotor', '1'); }
      });
    });
    a.addEventListener('submit', function () { a.removeAttribute('data-kotor'); });
  });

  function tulisStatus(jenis, teks) {
    penanda.forEach(function (p) {
      p.setAttribute('data-keadaan', jenis);
      p.innerHTML = '';
      var titik = document.createElement('span');
      titik.className = 'langsung-titik';
      p.appendChild(titik);
      if (jenis === 'tertunda') {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'langsung-tombol';
        b.textContent = 'Ada data baru, muat sekarang';
        b.addEventListener('click', function () {
          area.forEach(function (a) { a.removeAttribute('data-kotor'); });
          if (document.activeElement) { document.activeElement.blur(); }
          periksa(true);
        });
        p.appendChild(b);
      } else {
        p.appendChild(document.createTextNode(teks));
      }
    });
  }

  function sedangDiisi() {
    var aktif = document.activeElement;
    return area.some(function (a) {
      if (a.getAttribute('data-kotor') === '1') { return true; }
      return aktif && a.contains(aktif) && aktif.matches('input, select, textarea');
    });
  }

  /* Skrip hasil innerHTML tidak berjalan sendiri, jadi dibuat ulang */
  function jalankanSkrip(wadah) {
    wadah.querySelectorAll('script').forEach(function (lama) {
      var baru = document.createElement('script');
      baru.textContent = lama.textContent;
      lama.parentNode.replaceChild(baru, lama);
    });
  }

  function muatUlangArea() {
    return fetch(window.location.href, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (r.redirected && /login\.php/.test(r.url)) {
          berhenti = true;
          tulisStatus('putus', 'Sesi berakhir, silakan masuk kembali');
          return false;
        }
        if (!r.ok) { throw new Error('HTTP ' + r.status); }
        return r.text().then(function (html) {
          var doc = new DOMParser().parseFromString(html, 'text/html');
          area.forEach(function (a) {
            var sumber = doc.getElementById(a.id);
            if (!sumber) { return; }
            a.innerHTML = sumber.innerHTML;
            a.removeAttribute('data-kotor');
            jalankanSkrip(a);
            a.classList.remove('langsung-kilat');
            void a.offsetWidth; /* mulai ulang animasi */
            a.classList.add('langsung-kilat');
          });
          /* Halaman dapat bereaksi terhadap data baru, misalnya menampilkan pemberitahuan */
          document.dispatchEvent(new CustomEvent('langsung:diperbarui'));
          return true;
        });
      });
  }

  function periksa(paksa) {
    if (berhenti || sibuk || (document.hidden && !paksa)) { return; }
    sibuk = true;
    fetch(api + (api.indexOf('?') < 0 ? '?' : '&') + '_=' + Date.now(), { cache: 'no-store', credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (versi === null) {
          versi = j.versi;
          tulisStatus('ok', 'Real time, diperbarui ' + j.waktu);
          return;
        }
        if (j.versi === versi && !tertunda) {
          tulisStatus('ok', 'Real time, diperiksa ' + j.waktu);
          return;
        }
        if (sedangDiisi() && !paksa) {
          tertunda = true;
          tulisStatus('tertunda');
          return;
        }
        return muatUlangArea().then(function (berhasil) {
          if (berhasil) {
            versi = j.versi;
            tertunda = false;
            tulisStatus('ok', 'Data baru dimuat ' + j.waktu);
          }
        });
      })
      .catch(function () {
        tulisStatus('putus', 'Koneksi terputus, mencoba lagi');
      })
      .then(function () { sibuk = false; });
  }

  function mulai() {
    if (pewaktu) { clearInterval(pewaktu); }
    pewaktu = setInterval(periksa, jeda);
  }

  /* Hemat beban server: berhenti saat tab tidak dilihat, periksa segera saat kembali */
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) { periksa(); }
  });

  tulisStatus('ok', 'Menghubungkan...');
  periksa();
  mulai();
})();
