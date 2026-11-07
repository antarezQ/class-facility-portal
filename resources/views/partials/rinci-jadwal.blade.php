{{--
    Panel rincian satu blok jadwal.

    Dipakai Timeline dan Papan. Isinya tidak ditulis di sini, melainkan
    diambil dari atribut data-rinci milik blok yang diklik, supaya tidak ada
    kueri tambahan saat panel dibuka dan satu panel cukup untuk seluruh blok
    di halaman itu.

    Pemanggilan:
        @include('partials.rinci-jadwal')                      untuk latar terang
        @include('partials.rinci-jadwal', ['gelap' => true])   untuk papan
--}}
@php $gelap = $gelap ?? false; @endphp

<div class="modal rinci" id="rinci" hidden>
    <div class="modal-kotak {{ $gelap ? 'rinci-gelap' : '' }}" role="dialog" aria-modal="true"
         aria-labelledby="rinciJudul">
        <div class="modal-atas">
            <b id="rinciJudul" tabindex="-1"></b>
            <button type="button" class="modal-tutup" data-rinci-tutup aria-label="{{ __('publik.close_details') }}">@ikon('x', 24)</button>
        </div>
        <div class="modal-isi">
            <span class="rinci-tag" id="rinciTag" hidden></span>
            <dl class="rinci-dl" id="rinciIsi"></dl>
        </div>
    </div>
</div>

<script>
(function () {
    var panel = document.getElementById('rinci');
    if (! panel) { return; }

    var tag   = document.getElementById('rinciTag'),
        judul = document.getElementById('rinciJudul'),
        isi   = document.getElementById('rinciIsi'),
        pemicuTerakhir = null;

    function buka(data, pemicu) {
        judul.textContent = data.judul || '';

        tag.textContent = data.tag || '';
        tag.hidden = ! data.tag;

        // Isi disusun lewat textContent, bukan innerHTML, supaya nama mata
        // kuliah atau alasan yang memuat tanda < > tidak pernah ditafsirkan
        // sebagai markah.
        isi.textContent = '';
        (data.baris || []).forEach(function (b) {
            var dt = document.createElement('dt');
            dt.textContent = b.label;
            var dd = document.createElement('dd');
            dd.textContent = b.nilai;
            isi.appendChild(dt);
            isi.appendChild(dd);
        });

        pemicuTerakhir = pemicu;
        panel.hidden = false;
        kunci(true);
        judul.focus();
    }

    function tutup() {
        panel.hidden = true;
        kunci(false);
        if (pemicuTerakhir) { pemicuTerakhir.focus(); }
    }

    // Pada layar lebar yang menggulung adalah .main, bukan body — mengunci
    // body saja membuat halaman di belakang panel tetap bisa digulung.
    function kunci(aktif) {
        [document.body, document.querySelector('.main')].forEach(function (el) {
            if (el) { el.style.overflow = aktif ? 'hidden' : ''; }
        });
    }

    // Satu penyimak untuk seluruh halaman: blok jadwal digambar ulang tiap
    // ganti tanggal, jadi memasang penyimak per blok akan terlepas.
    document.addEventListener('click', function (e) {
        var pemicu = e.target.closest('[data-rinci]');

        if (pemicu) {
            try {
                buka(JSON.parse(pemicu.getAttribute('data-rinci')), pemicu);
            } catch (x) { /* blok tanpa rincian: biarkan saja */ }

            return;
        }

        // Klik di luar kotak, atau pada tombol tutup.
        if (e.target.closest('[data-rinci-tutup]') || e.target === panel) {
            tutup();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && ! panel.hidden) { tutup(); }

        // Fokus tetap di dalam panel.
        if (e.key === 'Tab' && ! panel.hidden) {
            var tombol = panel.querySelector('[data-rinci-tutup]');
            e.preventDefault();
            (document.activeElement === tombol ? judul : tombol).focus();
        }
    });

    // Dipakai papan: jangan memuat ulang halaman selagi rincian dibuka.
    window.rinciTerbuka = function () { return ! panel.hidden; };
})();
</script>
