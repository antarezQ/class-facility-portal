/* Perilaku halaman publik yang dipakai bersama. Tanpa kerangka kerja. */
(function () {
    'use strict';

    var SEGERA_MENIT = 30;

    function t(kunci, ganti) {
        var teks = (window.PUB_TEKS && window.PUB_TEKS[kunci]) || kunci;
        Object.keys(ganti || {}).forEach(function (k) {
            teks = teks.split(':' + k).join(ganti[k]);
        });
        return teks;
    }

    // Selalu waktu kampus, bukan zona perangkat (DESIGN §3.3).
    function bagianWaktu() {
        var bagian = {};
        new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit', weekday: 'short', hourCycle: 'h23'
        }).formatToParts(new Date()).forEach(function (p) { bagian[p.type] = p.value; });
        return bagian;
    }

    function menitSekarang() {
        var b = bagianWaktu();
        return parseInt(b.hour, 10) * 60 + parseInt(b.minute, 10);
    }

    function jamTeks(m) {
        return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
    }

    // Harus sama urutannya dengan App\Support\StatusRuangan::hitung().
    function hitungStatus(kegiatan, menit) {
        var urut = kegiatan.slice().sort(function (a, b) { return a.mulai - b.mulai; });
        var kini = null, lanjut = null, tutup = false;

        urut.forEach(function (x) {
            if (x.jenis === 'tutup') { tutup = true; return; }
            if (x.mulai <= menit && menit < x.selesai && !kini) { kini = x; }
            if (x.mulai > menit && !lanjut) { lanjut = x; }
        });

        if (tutup) { return { kunci: 'closed', teks: t('closed') }; }
        if (kini) { return { kunci: 'occupied', teks: t('busy_until', { t: jamTeks(kini.selesai) }) }; }
        if (lanjut && lanjut.mulai - menit <= SEGERA_MENIT) {
            return { kunci: 'soon', teks: t('starts_in', { n: lanjut.mulai - menit }) };
        }
        return {
            kunci: 'available',
            teks: lanjut ? t('free_until', { t: jamTeks(lanjut.mulai) }) : t('free_rest')
        };
    }

    // Ikon tiap status sudah dicetak server di dalam <template id="ikonStatus">.
    function pasangStatus(wadah, status) {
        var lencana = wadah.querySelector('[data-lencana]');
        var teks = wadah.querySelector('[data-teks-status]');

        if (lencana) {
            lencana.hidden = false;
            lencana.className = 'pub-badge pub-badge--' + status.kunci;
            var cetakan = document.getElementById('ikonStatus');
            var sumber = cetakan && cetakan.content.querySelector('[data-kunci="' + status.kunci + '"]');
            lencana.innerHTML = (sumber ? sumber.innerHTML : '') + '<span></span>';
            lencana.querySelector('span').textContent = t(status.kunci);
        }
        if (teks) {
            teks.hidden = false;
            teks.textContent = status.teks;
        }
        wadah.setAttribute('data-status', status.kunci);
    }

    /* Kartu yang statusnya mengikuti /papan/data: Dashboard (Ruangan) dan Papan.
       Bila gagal dimuat, badge disembunyikan: lebih baik tidak ada status
       daripada status yang tidak pasti. */
    function segarkanStatus(url) {
        var kartu = document.querySelectorAll('[data-kode]');
        var pesanGagal = document.getElementById('statusGagal');

        function sembunyikan() {
            kartu.forEach(function (k) {
                var l = k.querySelector('[data-lencana]'), s = k.querySelector('[data-teks-status]');
                if (l) { l.hidden = true; }
                if (s) { s.hidden = true; }
            });
            if (pesanGagal) { pesanGagal.hidden = false; }
        }

        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (r) { if (!r.ok) { throw new Error('gagal'); } return r.json(); })
            .then(function (d) {
                if (pesanGagal) { pesanGagal.hidden = true; }

                var minggu = bagianWaktu().weekday === 'Sun';
                var pesanMinggu = document.getElementById('gedungTutup');
                if (pesanMinggu) { pesanMinggu.hidden = !minggu; }

                if (!d.periode || minggu) {
                    kartu.forEach(function (k) {
                        var l = k.querySelector('[data-lencana]'), s = k.querySelector('[data-teks-status]');
                        if (l) { l.hidden = true; }
                        if (s) { s.hidden = true; }
                    });
                    return;
                }

                var menit = menitSekarang();
                kartu.forEach(function (k) {
                    var punya = (d.kegiatan || []).filter(function (x) { return x.ruangan === k.dataset.kode; });
                    pasangStatus(k, hitungStatus(punya, menit));
                });
                terapkanSaringan();
                document.dispatchEvent(new CustomEvent('pub:status', { detail: d }));
            })
            .catch(sembunyikan);
    }

    // Chip All / Available / Soon busy / Occupied di Papan HP.
    var saringan = 'all';

    function terapkanSaringan() {
        document.querySelectorAll('[data-saring-kartu]').forEach(function (k) {
            k.hidden = saringan !== 'all' && k.getAttribute('data-status') !== saringan;
        });
    }

    document.addEventListener('click', function (e) {
        var chip = e.target.closest('[data-saring]');
        if (chip) {
            saringan = chip.dataset.saring;
            document.querySelectorAll('[data-saring]').forEach(function (c) {
                var aktif = c === chip;
                c.classList.toggle('on', aktif);
                c.setAttribute('aria-pressed', aktif ? 'true' : 'false');
            });
            terapkanSaringan();
        }

        var salin = e.target.closest('[data-salin]');
        if (salin && navigator.clipboard) {
            navigator.clipboard.writeText(salin.dataset.salin).then(function () {
                var label = salin.querySelector('span');
                if (label) { label.textContent = t('copied'); }
            });
        }

        // Lipatan filter di HP.
        var lipat = e.target.closest('[data-lipat]');
        if (lipat) {
            var sasaran = document.getElementById(lipat.dataset.lipat);
            if (sasaran) {
                var buka = sasaran.classList.toggle('terbuka');
                lipat.setAttribute('aria-expanded', buka ? 'true' : 'false');
            }
        }
    });

    window.PubStatus = { segarkan: segarkanStatus, bagianWaktu: bagianWaktu, jamTeks: jamTeks, menitSekarang: menitSekarang };

    document.addEventListener('DOMContentLoaded', function () {
        var sumber = document.querySelector('[data-status-url]');
        if (sumber) {
            var url = sumber.dataset.statusUrl;
            segarkanStatus(url);
            setInterval(function () { segarkanStatus(url); }, 60000);
        }

        // Timeline: buka di dekat waktu sekarang, dan tab aktif tetap terlihat.
        var garis = document.querySelector('[data-gulir-ke-sekarang]');
        var gulir = document.querySelector('.pub-axis-gulir');
        if (garis && gulir && gulir.scrollWidth > gulir.clientWidth) {
            gulir.scrollLeft = Math.max(0, garis.getBoundingClientRect().left - gulir.getBoundingClientRect().left - 240);
        }
    });
})();
