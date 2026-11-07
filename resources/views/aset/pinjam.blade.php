@extends('layouts.app')
@section('publik', '1')
@section('judul', __('publik.borrow.title'))

@php
    // Barang yang dipilih sebelum formulir ditolak, supaya pilihan tidak hilang.
    $barisLama = collect(old('baris', []));
    $pilihanLama = $barisLama->keyBy('aset_unit_id');

    // Galat per barang, dicocokkan lewat unitnya karena nomor baris bisa berubah.
    $galatBarang = [];
    foreach ($errors->getMessages() as $kunci => $daftarPesan) {
        if (preg_match('/^baris\.(\d+)\./', $kunci, $m) && isset($barisLama[$m[1]]['aset_unit_id'])) {
            $galatBarang[$barisLama[$m[1]]['aset_unit_id']] = $daftarPesan[0];
        }
    }

    // Unit yang sama jenisnya tampil sebagai satu barang. Urutannya mengikuti $tersedia.
    $daftarBarang = $tersedia->groupBy(fn ($u) => $u->kategori_aset_id . '|' . $u->nama . '|' . $u->merek);

    // Sama dengan batas 'baris' di PeminjamanAsetController::store().
    $maksUnit = 20;

    $peranAset = ['Mahasiswa', 'Dosen', 'Tenaga Kependidikan', 'Asisten / Laboran'];
@endphp

@section('isi')
<div class="pub-page">
    <h1 class="pub-h1">{{ __('publik.borrow.title') }}</h1>
    <p class="pub-lead">{{ __('publik.borrow.lead') }}</p>

    @include('publik.ringkasan-galat')

    {{-- Langkah 1: tentukan rentang tanggal --}}
    <form method="GET" action="{{ route('aset.pinjam') }}" class="pub-card pub-langkah">
        <h2 class="pub-langkah-judul"><span>1</span> {{ __('publik.borrow.step1') }}</h2>
        <div class="pub-form-baris pub-form-baris--pinjam">
            <div class="pub-field">
                <label for="fMulai">{{ __('publik.borrow.start') }}</label>
                <input type="date" id="fMulai" name="mulai" value="{{ $mulai->toDateString() }}" required>
            </div>
            <div class="pub-field">
                <label for="fSelesai">{{ __('publik.borrow.return') }}</label>
                <input type="date" id="fSelesai" name="selesai" value="{{ $selesai->toDateString() }}" required>
            </div>
            <div class="pub-field">
                <label for="fKategori">{{ __('publik.borrow.category') }}</label>
                <select id="fKategori" name="kategori">
                    <option value="">{{ __('publik.borrow.all_categories') }}</option>
                    @foreach($kategoris as $k)
                        <option value="{{ $k->id }}" @selected(request('kategori') == $k->id)>{{ $k->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pub-field">
                <label for="fCari">{{ __('publik.borrow.search') }}</label>
                <input type="text" id="fCari" name="q" value="{{ request('q') }}" placeholder="{{ __('publik.borrow.search_ph') }}">
            </div>
            <button class="pub-btn pub-btn--primary pub-btn--penuh-hp" type="submit">@ikon('search', 20) {{ __('publik.borrow.show') }}</button>
        </div>
    </form>

    @if($tersedia->count())
        <form method="POST" action="{{ route('aset.pinjam.store') }}" id="formPinjam" class="pub-pinjam">
            @csrf

            <div class="pub-pinjam-kiri">
                <div class="pub-stats">
                    <div class="pub-stat pub-stat--ok">
                        <span>{{ __('publik.borrow.stat_avail') }}</span>
                        <b>{{ $tersedia->count() }}</b>
                        <small>{{ __('publik.borrow.stat_avail_sub', ['a' => $mulai->translatedFormat('j M'), 'b' => $selesai->translatedFormat('j M Y')]) }}</small>
                    </div>
                    <div class="pub-stat">
                        <span>{{ __('publik.borrow.stat_days') }}</span>
                        <b>{{ __('publik.borrow.days', ['n' => $mulai->diffInDays($selesai) + 1]) }}</b>
                    </div>
                </div>

                {{-- Langkah 2: pilih barang --}}
                <section class="pub-card pub-langkah">
                    <h2 class="pub-langkah-judul"><span>2</span> {{ __('publik.borrow.choose_title') }}</h2>
                    <p class="pub-meta">{{ __('publik.borrow.choose_sub', ['n' => $maksUnit]) }}</p>

                    <div class="pub-item-daftar">
                        @foreach($daftarBarang->values() as $i => $unitBarang)
                            @php
                                $u = $unitBarang->first();
                                $lamaBarang = $unitBarang->map(fn ($x) => $pilihanLama[$x->id] ?? null)->filter();
                                $galatIni = $unitBarang->map(fn ($x) => $galatBarang[$x->id] ?? null)->filter()->first();
                            @endphp
                            <div class="pub-item {{ $galatIni ? 'pub-item--galat' : '' }}" data-barang="{{ $daftarBarang->keys()[$i] }}"
                                 data-unit="{{ $unitBarang->sortBy(fn ($x) => $unitMenunggu->contains($x->id) ? 1 : 0)->pluck('id')->values()->toJson() }}">
                                <div class="pub-item-atas">
                                    <div class="pub-item-teks">
                                        <b id="namaBarang{{ $i }}">{{ $u->nama }}</b>
                                        <span class="pub-meta">
                                            {{ __('publik.borrow.avail_n', ['n' => $unitBarang->count()]) }}@if($u->merek) · {{ $u->merek }}@endif
                                        </span>
                                        @if($u->kategori->nama !== $u->nama)
                                            <span class="pub-tag">{{ $u->kategori->nama }}</span>
                                        @endif
                                    </div>
                                    <div class="pub-jumlah">
                                        <button type="button" class="pub-btn pub-btn--secondary kurang-barang" aria-label="{{ __('publik.borrow.less', ['item' => $u->nama]) }}">@ikon('minus', 20)</button>
                                        <input type="number" class="jumlah-barang" inputmode="numeric" min="0" max="{{ $unitBarang->count() }}"
                                               value="{{ $lamaBarang->count() }}" aria-label="{{ __('publik.borrow.qty', ['item' => $u->nama]) }}">
                                        <button type="button" class="pub-btn pub-btn--secondary tambah-barang" aria-label="{{ __('publik.borrow.more', ['item' => $u->nama]) }}">@ikon('plus', 20)</button>
                                    </div>
                                </div>
                                <div class="pub-field pub-item-kembali">
                                    <label for="kembali{{ $i }}">{{ __('publik.borrow.return_short') }}</label>
                                    <input type="date" id="kembali{{ $i }}" class="tgl-kembali"
                                           value="{{ $lamaBarang->first()['tanggal_kembali'] ?? $selesai->toDateString() }}"
                                           min="{{ $mulai->toDateString() }}" @disabled($lamaBarang->isEmpty())>
                                </div>
                                @if($galatIni)
                                    <div class="pub-field-galat">@ikon('circle-alert', 16) <span>@pesan($galatIni)</span></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>

                <div id="isianBaris"></div>
            </div>

            <div class="pub-pinjam-kanan">
                {{-- Langkah 3: identitas pemohon --}}
                <section class="pub-card pub-langkah">
                    <h2 class="pub-langkah-judul"><span>3</span> {{ __('publik.borrow.applicant_title') }}</h2>

                    <div class="pub-form-dua">
                        <div class="pub-field">
                            <label for="pNama">{{ __('publik.form.applicant') }}</label>
                            <input type="text" id="pNama" name="nama" value="{{ old('nama') }}" required autocomplete="name">
                            @include('publik.galat', ['nama' => 'nama'])
                        </div>
                        <div class="pub-field">
                            <label for="pIdentitas">{{ __('publik.form.identity') }} <em>({{ __('publik.optional') }})</em></label>
                            <input type="text" id="pIdentitas" name="identitas" value="{{ old('identitas') }}">
                            <span class="hint">{{ __('publik.form.identity_hint') }}</span>
                        </div>
                        <div class="pub-field">
                            <label for="pPeran">{{ __('publik.form.status') }}</label>
                            <select id="pPeran" name="peran" required>
                                <option value="">{{ __('publik.choose') }}</option>
                                @foreach($peranAset as $o)
                                    <option value="{{ $o }}" @selected(old('peran') === $o)>{{ __('publik.form.peran.' . $o) }}</option>
                                @endforeach
                            </select>
                            @include('publik.galat', ['nama' => 'peran'])
                        </div>
                        <div class="pub-field">
                            <label for="pUnit">{{ __('publik.borrow.unit') }} <em>({{ __('publik.optional') }})</em></label>
                            <input type="text" id="pUnit" name="unit_organisasi" value="{{ old('unit_organisasi') }}" placeholder="{{ __('publik.borrow.unit_ph') }}">
                        </div>
                        <div class="pub-field">
                            <label for="pKontak">{{ __('publik.form.whatsapp') }}</label>
                            <input type="tel" id="pKontak" name="kontak" value="{{ old('kontak') }}" required placeholder="{{ __('publik.form.whatsapp_ph') }}" autocomplete="tel">
                            <span class="hint">{{ __('publik.form.whatsapp_hint') }}</span>
                            @include('publik.galat', ['nama' => 'kontak'])
                        </div>
                        <div class="pub-field">
                            <label for="pRuangan">{{ __('publik.borrow.room') }} <em>({{ __('publik.optional') }})</em></label>
                            <select id="pRuangan" name="ruangan_id">
                                <option value="">{{ __('publik.borrow.no_room') }}</option>
                                @foreach($ruangans as $r)
                                    <option value="{{ $r->id }}" @selected(old('ruangan_id') == $r->id)>
                                        {{ $r->kode }} — {{ $r->gedung->kode_pendek ?? $r->gedung->nama }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="pub-field">
                        <label for="pKeperluan">{{ __('publik.form.purpose') }}</label>
                        <textarea id="pKeperluan" name="keperluan" rows="3" required placeholder="{{ __('publik.borrow.purpose_ph') }}">{{ old('keperluan') }}</textarea>
                        @include('publik.galat', ['nama' => 'keperluan'])
                    </div>
                </section>

                @include('publik.pesan', ['jenis' => 'info', 'isi' => __('publik.borrow.notice')])

            </div>

            {{-- Anak langsung formulir, supaya bisa menempel selama daftar barang digulir di HP. --}}
            <div class="pub-kirim">
                <span aria-live="polite">
                    <span id="ringkasPilihan">{{ __('publik.borrow.none_selected') }}</span>
                    <span class="pub-batas" id="batasPilihan" hidden>{{ __('publik.borrow.limit', ['n' => $maksUnit]) }}</span>
                </span>
                <button class="pub-btn pub-btn--primary" id="tombolKirim" type="submit" disabled>@ikon('send', 20) {{ __('publik.borrow.send') }}</button>
            </div>
        </form>
    @else
        <div class="pub-stats">
            <div class="pub-stat pub-stat--ok">
                <span>{{ __('publik.borrow.stat_avail') }}</span>
                <b>0</b>
            </div>
            <div class="pub-stat">
                <span>{{ __('publik.borrow.stat_days') }}</span>
                <b>{{ __('publik.borrow.days', ['n' => $mulai->diffInDays($selesai) + 1]) }}</b>
            </div>
        </div>
        <div class="pub-card">
            @include('publik.kosong', ['ikon' => 'package-x', 'judul' => __('publik.borrow.none_title'), 'isi' => __('publik.borrow.none_body')])
        </div>
    @endif
</div>
@endsection

@push('skrip')
<script>
(function () {
    var form     = document.getElementById('formPinjam');
    var wadah    = document.getElementById('isianBaris');
    var tombol   = document.getElementById('tombolKirim');
    var ringkas  = document.getElementById('ringkasPilihan');

    var ringkasan = document.querySelector('[data-ringkasan-galat]');
    if (ringkasan) { ringkasan.focus(); }

    if (!form || !wadah) { return; }

    var TEKS_KOSONG = @json(__('publik.borrow.none_selected'));
    var TEKS_PILIH  = @json(__('publik.borrow.selected'));

    // Berpindah bahasa memuat ulang halaman, jadi pilihan disimpan sementara.
    var KUNCI = 'pinjamPilihan:' + window.location.pathname + window.location.search.replace(/([?&])lang=[^&]*&?/, '$1').replace(/[?&]$/, '');
    var adaPilihanServer = {{ $pilihanLama->isNotEmpty() ? 'true' : 'false' }};

    var MAKS_UNIT = {{ $maksUnit }};
    var TANGGAL_PINJAM = @json($mulai->toDateString());
    var batas = document.getElementById('batasPilihan');
    var daftar = Array.prototype.slice.call(document.querySelectorAll('[data-barang]'));

    function jumlahDi(barang) {
        return parseInt(barang.querySelector('.jumlah-barang').value, 10) || 0;
    }

    function totalUnit() {
        return daftar.reduce(function (t, b) { return t + jumlahDi(b); }, 0);
    }

    function simpanPilihan() {
        var data = {};
        daftar.forEach(function (b) {
            if (jumlahDi(b)) {
                data[b.dataset.barang] = {n: jumlahDi(b), kembali: b.querySelector('.tgl-kembali').value};
            }
        });
        try { sessionStorage.setItem(KUNCI, JSON.stringify(data)); } catch (x) { /* tanpa penyimpanan: abaikan */ }
    }

    // Isian untuk server tetap per unit. Unit diambil berurutan dari yang tersedia.
    function tambahIsian(i, nama, nilai) {
        var isian = document.createElement('input');
        isian.type = 'hidden';
        isian.name = 'baris[' + i + '][' + nama + ']';
        isian.value = nilai;
        wadah.appendChild(isian);
    }

    function bangunIsian() {
        var i = 0;
        wadah.textContent = '';
        daftar.forEach(function (b) {
            JSON.parse(b.dataset.unit).slice(0, jumlahDi(b)).forEach(function (id) {
                tambahIsian(i, 'aset_unit_id', id);
                tambahIsian(i, 'tanggal_pinjam', TANGGAL_PINJAM);
                tambahIsian(i, 'tanggal_kembali', b.querySelector('.tgl-kembali').value);
                i++;
            });
        });
    }

    function segarkan() {
        var total = totalUnit();

        daftar.forEach(function (b) {
            var n = jumlahDi(b);
            b.classList.toggle('pub-item--pilih', n > 0);
            b.querySelector('.tgl-kembali').disabled = n === 0;
            b.querySelector('.kurang-barang').disabled = n === 0;
            b.querySelector('.tambah-barang').disabled = total >= MAKS_UNIT || n >= JSON.parse(b.dataset.unit).length;
        });

        if (tombol) { tombol.disabled = total === 0; }
        if (ringkas) { ringkas.textContent = total ? TEKS_PILIH.replace(':n', total) : TEKS_KOSONG; }
        if (batas) { batas.hidden = total < MAKS_UNIT; }

        bangunIsian();
        simpanPilihan();
    }

    // Jumlah dijaga tidak melebihi stok barang itu maupun batas per pengajuan.
    function aturJumlah(barang, n) {
        var sisa = MAKS_UNIT - (totalUnit() - jumlahDi(barang));
        var stok = JSON.parse(barang.dataset.unit).length;
        barang.querySelector('.jumlah-barang').value = Math.max(0, Math.min(n || 0, stok, sisa));
        segarkan();
    }

    daftar.forEach(function (b) {
        var isian = b.querySelector('.jumlah-barang');

        b.querySelector('.kurang-barang').addEventListener('click', function () { aturJumlah(b, jumlahDi(b) - 1); });
        b.querySelector('.tambah-barang').addEventListener('click', function () { aturJumlah(b, jumlahDi(b) + 1); });
        isian.addEventListener('change', function () { aturJumlah(b, parseInt(isian.value, 10)); });
        b.querySelector('.tgl-kembali').addEventListener('change', segarkan);
    });

    if (!adaPilihanServer) {
        try {
            var tersimpan = JSON.parse(sessionStorage.getItem(KUNCI) || '{}');
            daftar.forEach(function (b) {
                var lama = tersimpan[b.dataset.barang];
                if (lama && lama.n) {
                    b.querySelector('.jumlah-barang').value = lama.n;
                    b.querySelector('.tgl-kembali').value = lama.kembali;
                }
            });
        } catch (x) { /* data lama tidak terbaca: mulai dari kosong */ }
    }

    segarkan();

    form.addEventListener('submit', function () {
        try { sessionStorage.removeItem(KUNCI); } catch (x) { /* abaikan */ }
    });
})();
</script>
@endpush
