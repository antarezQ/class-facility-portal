@php $tanpaHeader = true; @endphp
@extends('layouts.app')
@section('publik', '1')
@section('judul', __('publik.board.title'))

@section('isi')
@php
    // Sumbu dimulai pada jam buka gedung, bukan pukul 06:00 — setengah jam
    // pertama itu tidak pernah dipakai dan hanya memakan lebar. Bila ada
    // kegiatan yang mulai lebih pagi, sumbunya mundur secukupnya.
    $awalTetap = (int) explode(':', config('jadwal.jam_buka'))[0] * 60
               + (int) explode(':', config('jadwal.jam_buka'))[1];

    $awalIsi = (int) ($kegiatan->min('mulai') ?? $awalTetap);
    $awal    = max(0, min($awalTetap, (int) floor($awalIsi / 30) * 30));

    // Sama seperti Timeline: berhenti di jam tampil, tetapi melar bila ada
    // kegiatan yang melewatinya agar tidak ada yang terpotong dari layar.
    $akhirTetap = (int) explode(':', config('jadwal.jam_tampil_akhir'))[0] * 60
                + (int) explode(':', config('jadwal.jam_tampil_akhir'))[1];

    $akhirIsi = (int) ($kegiatan->max('selesai') ?? 0);
    $akhir    = min(24 * 60, max($akhirTetap, (int) ceil($akhirIsi / 60) * 60));

    $perRuangan = $kegiatan->groupBy('ruangan_id');
    $menitKini = now()->hour * 60 + now()->minute;
    $hariMinggu = now()->dayOfWeekIso === 7;

    // Semua ruangan tampil, termasuk yang kosong sehari penuh.
    $barisan = $ruangans->sortBy('urutan')->map(fn ($r) => [
        'id' => $r->id,
        'kode' => $r->kode,
        'meta' => collect([
            __('publik.floor', ['n' => $r->lantai]),
            __('publik.seats', ['n' => ($r->kapasitas_perkiraan ? '± ' : '') . $r->kapasitas_efektif]),
        ])->join(' · '),
    ])->values();
@endphp

<div class="pub-board" @if($periode && ! $diLuarMasa && ! $hariMinggu) data-status-url="{{ route('papan.json') }}{{ request('gedung') ? '?gedung=' . (int) request('gedung') : '' }}" @endif>
    <header class="pub-board-kepala">
        <a class="pub-pelat" href="{{ route('menu') }}" aria-label="{{ __('publik.logo_label') }}">
            <span class="pub-merek">@ikon('building-2', 24)<b>{{ config('jadwal.nama_pendek') }}</b></span>
        </a>
        <div class="pub-board-judul">
            <h1>{{ __('publik.board.heading', ['day' => now()->translatedFormat('l')]) }}</h1>
            <p>
                {{ now()->translatedFormat('D, j M Y') }}
                · {{ $periode->nama ?? __('publik.board.no_data') }}
                @if($periode && $periode->punyaTanggal())
                    @if($diLuarMasa)
                        · {{ __('publik.outside_term') }}
                    @elseif($pekanKe)
                        · {{ __('publik.week', ['n' => $pekanKe]) }}
                    @endif
                @endif
            </p>
        </div>
        <div class="pub-stats pub-stats--board">
            <div class="pub-stat"><span>{{ __('publik.board.total') }}</span><b id="pTotal">{{ $ruangans->count() }}</b></div>
            <div class="pub-stat pub-stat--busy"><span>{{ __('publik.board.used') }}</span><b id="pPakai">{{ $terpakai->count() ?? 0 }}</b></div>
            <div class="pub-stat pub-stat--ok"><span>{{ __('publik.board.free') }}</span><b id="pKosong">{{ $ruangans->count() - ($terpakai->count() ?? 0) }}</b></div>
            <div class="pub-stat pub-stat--jam"><span>WIB (UTC+7)</span><b id="pJam">--:--</b></div>
        </div>
    </header>

    @if(! $periode)
        <div class="pub-board-isi">
            @include('publik.kosong', ['ikon' => 'calendar-off', 'judul' => __('publik.board.empty_sem_title'), 'isi' => __('publik.board.empty_sem_body')])
        </div>
    @elseif($diLuarMasa)
        <div class="pub-board-isi">
            @include('publik.kosong', [
                'ikon' => 'calendar-off',
                'judul' => __('publik.outside_term'),
                'isi' => __('publik.board.out_body', [
                    'name' => $periode->nama,
                    'a' => $periode->tanggal_mulai->translatedFormat('j M Y'),
                    'b' => $periode->tanggal_selesai->translatedFormat('j M Y'),
                ]),
            ])
        </div>
    @elseif($hariMinggu)
        <div class="pub-board-isi">
            @include('publik.kosong', ['ikon' => 'calendar-off', 'judul' => __('publik.status.building_closed'), 'isi' => __('publik.board.none_today')])
        </div>
    @else
        <div class="pub-board-isi">
            {{-- HP: chip penyaring dan kartu agenda --}}
            <div class="hanya-hp">
                <div class="pub-chips" role="group" aria-label="{{ __('publik.board.filter') }}">
                    <button type="button" class="pub-chip on" data-saring="all" aria-pressed="true">@ikon('check', 16)<span>{{ __('publik.board.all') }}</span></button>
                    @foreach(['available', 'soon', 'occupied'] as $kunci)
                        <button type="button" class="pub-chip" data-saring="{{ $kunci }}" aria-pressed="false">@ikon('check', 16)<span>{{ __('publik.status.' . $kunci) }}</span></button>
                    @endforeach
                </div>
                <div class="pub-tumpuk">
                    @foreach($barisan as $b)
                        @include('publik.agenda', [
                            'kode' => $b['kode'], 'meta' => $b['meta'],
                            'daftar' => $perRuangan[$b['id']] ?? collect(),
                            'menit' => $menitKini, 'hariIni' => true, 'saring' => true,
                        ])
                    @endforeach
                </div>
            </div>

            {{-- iPad dan desktop: sumbu waktu --}}
            <div class="bukan-hp">
                @include('publik.legenda', ['catatan' => __('publik.tl.now_note', ['t' => now()->format('H:i')])])
                @if($barisan->isEmpty())
                    @include('publik.kosong', ['ikon' => 'calendar-off', 'judul' => __('publik.board.none_today'), 'isi' => __('publik.zone')])
                @else
                    @include('publik.sumbu', [
                        'barisan' => $barisan, 'perRuangan' => $perRuangan, 'awal' => $awal, 'akhir' => $akhir,
                        'hariIni' => true, 'menit' => $menitKini, 'tampilStatus' => true,
                    ])
                @endif
            </div>
        </div>
    @endif

    <footer class="pub-board-kaki">
        <span>{{ $periode->nama ?? '' }} · {{ __('publik.board.count', ['n' => $kegiatan->count()]) }}</span>
        <span>{{ __('publik.board.auto') }} · {{ __('publik.board.updated') }}: <span id="pUpd" class="pub-angka">--:--:--</span></span>
    </footer>
</div>

@include('partials.rinci-jadwal', ['gelap' => true])
@endsection

@push('skrip')
<script>
function jamJalan() {
    var b = window.PubStatus.bagianWaktu();
    document.getElementById('pJam').textContent = b.hour + ':' + b.minute;
}
function waktuDiperbarui() {
    var b = window.PubStatus.bagianWaktu();
    document.getElementById('pUpd').textContent = b.hour + ':' + b.minute + ':' + b.second;
}
setInterval(jamJalan, 1000); jamJalan(); waktuDiperbarui();

// Selagi rincian dibuka, papan dibiarkan diam. Tanpa ini, muat ulang
// otomatis akan menutup panel tepat saat orang sedang membacanya.
function papanSibuk() {
    return typeof window.rinciTerbuka === 'function' && window.rinciTerbuka();
}

// Segarkan angka tanpa memuat ulang halaman; muat ulang penuh tiap 10 menit
setInterval(function () {
    if (papanSibuk()) { return; }
    fetch('{{ route('papan.json') }}' + window.location.search.replace(/([?&])lang=[^&]*&?/, '$1').replace(/[?&]$/, ''))
        .then(function (r) { return r.json(); })
        .then(function (d) {
            document.getElementById('pTotal').textContent = d.total;
            document.getElementById('pPakai').textContent = d.terpakai;
            document.getElementById('pKosong').textContent = d.kosong;
            waktuDiperbarui();
            // Bila status masa perkuliahan berubah (mis. hari pertama kuliah),
            // muat ulang halaman supaya isinya ikut berganti.
            if (d.di_luar_masa !== {{ $diLuarMasa ? 'true' : 'false' }}) { window.location.reload(); }
        })
        .catch(function () {});
}, 60000);
// Dulu setTimeout sekali jalan; diubah jadi setInterval supaya muat ulang
// yang dilewati karena rincian terbuka tetap dicoba lagi berikutnya.
setInterval(function () {
    if (papanSibuk()) { return; }
    window.location.reload();
}, 600000);
</script>
@endpush
