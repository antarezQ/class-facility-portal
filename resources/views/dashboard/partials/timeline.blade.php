@php
    // Sumbu dimulai pada jam buka gedung, bukan pukul 06:00 — setengah jam
    // pertama itu tidak pernah dipakai dan hanya memakan lebar. Bila ada
    // kegiatan yang mulai lebih pagi, sumbunya mundur secukupnya.
    $awalTetap = (int) explode(':', config('jadwal.jam_buka'))[0] * 60
               + (int) explode(':', config('jadwal.jam_buka'))[1];

    $awalIsi = (int) ($pemakaian->min('mulai') ?? $awalTetap);
    $awal    = max(0, min($awalTetap, (int) floor($awalIsi / 30) * 30));

    // Batas kanan sumbu. Memakai 24:00 membuat jam pagi berdesakan padahal
    // sepertiga kanannya tidak pernah terpakai; sebaliknya memotong di jam
    // tetap akan menyembunyikan kegiatan malam seperti praktikum. Jadi
    // batasnya mengikuti config, lalu melar bila ada yang melewatinya.
    $akhirTetap = (int) explode(':', config('jadwal.jam_tampil_akhir'))[0] * 60
                + (int) explode(':', config('jadwal.jam_tampil_akhir'))[1];

    $akhirIsi = (int) ($pemakaian->max('selesai') ?? 0);
    $akhir    = min(24 * 60, max($akhirTetap, (int) ceil($akhirIsi / 60) * 60));

    $perRuangan = $pemakaian->groupBy('ruangan_id');
    $aktif      = $perRuangan->keys();
    // Di luar masa perkuliahan tidak ada kegiatan sama sekali, jadi lebih
    // berguna menampilkan seluruh ruangan daripada daftar kosong.
    $diLuarMasa = $periode->punyaTanggal() && ! $periode->mencakup($tanggal);
    $mode       = request('mode', $diLuarMasa ? 'semua' : 'aktif');
    $celahPeta  = $celah->keyBy(fn ($c) => $c['ruangan']->id);
    $hariIni    = $tanggal->isToday();
    $menitKini  = now()->hour * 60 + now()->minute;
    $urlDasar   = route('dashboard', 'timeline') . '?periode=' . $periode->kode . ($gedung ? '&gedung=' . $gedung->id : '');
    $minggu     = $hari < 0 || $hari > 5;

    $barisan = ($mode === 'semua' ? $ruangans : $ruangans->whereIn('id', $aktif))->sortBy('urutan')->map(fn ($r) => [
        'id' => $r->id,
        'kode' => $r->kode,
        'meta' => collect([
            __('publik.floor', ['n' => $r->lantai]),
            __('publik.seats', ['n' => ($r->kapasitas_perkiraan ? '± ' : '') . $r->kapasitas_efektif]),
        ])->join(' · '),
    ])->values();

    $pekan = $periode->pekanKe($tanggal);
@endphp

<div class="pub-tl-kontrol">
    <div class="pub-tl-tanggal">
        <a class="pub-btn-ikon" aria-label="{{ __('publik.tl.prev_day') }}" href="{{ $urlDasar }}&tanggal={{ $tanggal->copy()->subDay()->toDateString() }}&mode={{ $mode }}">@ikon('chevron-left', 20)</a>

        <form method="GET" action="{{ route('dashboard', 'timeline') }}" class="pub-tl-form">
            <input type="hidden" name="periode" value="{{ $periode->kode }}">
            @if($gedung)<input type="hidden" name="gedung" value="{{ $gedung->id }}">@endif
            <input type="hidden" name="mode" value="{{ $mode }}">
            <label class="pub-sr" for="tlTanggal">{{ __('publik.tl.date') }}</label>
            <input type="date" id="tlTanggal" name="tanggal" value="{{ $tanggal->toDateString() }}" onchange="this.form.submit()">
        </form>

        <a class="pub-btn-ikon" aria-label="{{ __('publik.tl.next_day') }}" href="{{ $urlDasar }}&tanggal={{ $tanggal->copy()->addDay()->toDateString() }}&mode={{ $mode }}">@ikon('chevron-right', 20)</a>
    </div>

    <div class="pub-tl-mode">
        <a class="pub-chip {{ $mode === 'semua' ? 'on' : '' }}" href="{{ $urlDasar }}&tanggal={{ $tanggal->toDateString() }}&mode=semua">
            @if($mode === 'semua')@ikon('check', 16)@endif <span>{{ __('publik.tl.all') }}</span>
        </a>
        <a class="pub-btn pub-btn--secondary pub-tl-today" href="{{ $urlDasar }}&tanggal={{ now()->toDateString() }}&mode={{ $mode }}">{{ __('publik.tl.today') }}</a>
        <a class="pub-chip {{ $mode === 'aktif' ? 'on' : '' }}" href="{{ $urlDasar }}&tanggal={{ $tanggal->toDateString() }}&mode=aktif">
            @if($mode === 'aktif')@ikon('check', 16)@endif <span>{{ __('publik.tl.active') }}</span>
        </a>
    </div>
</div>

@unless($minggu)
    <div class="pub-stats pub-stats--tiga">
        <div class="pub-stat">
            <span><span class="bukan-hp-inline">{{ __('publik.tl.total') }}</span><span class="hanya-hp-inline">{{ __('publik.tl.total_short') }}</span></span>
            <b>{{ $ruangans->count() }}</b>
        </div>
        <div class="pub-stat pub-stat--busy">
            <span><span class="bukan-hp-inline">{{ __('publik.tl.has_activity') }}</span><span class="hanya-hp-inline">{{ __('publik.tl.has_activity_short') }}</span></span>
            <b>{{ $aktif->count() }}</b>
        </div>
        <div class="pub-stat pub-stat--ok">
            <span>{{ __('publik.tl.free') }}</span>
            <b>{{ $ruangans->count() - $aktif->count() }}</b>
        </div>
    </div>
@endunless

<p class="pub-meta">
    {{ $tanggal->translatedFormat('D, j M Y') }}
    @if($periode->punyaTanggal())
        · {{ $pekan ? __('publik.week', ['n' => $pekan]) : __('publik.outside_term') }}
    @endif
    @if($hariIni)
        · {{ __('publik.event.now') }} {{ now()->format('H:i') }} WIB (UTC+7)
    @else
        · {{ __('publik.zone') }}
    @endif
</p>

@if($barisan->isEmpty())
    <div class="pub-card">
        @if($minggu)
            @include('publik.kosong', ['ikon' => 'calendar-off', 'judul' => __('publik.tl.sunday_title'), 'isi' => __('publik.tl.sunday_body')])
        @elseif($diLuarMasa)
            @include('publik.kosong', [
                'ikon' => 'calendar-off', 'judul' => __('publik.tl.out_title'),
                'isi' => __('publik.tl.out_body', [
                    'name' => $periode->nama,
                    'a' => $periode->tanggal_mulai->translatedFormat('j M Y'),
                    'b' => $periode->tanggal_selesai->translatedFormat('j M Y'),
                ]),
            ])
        @else
            @include('publik.kosong', ['ikon' => 'calendar-off', 'judul' => __('publik.tl.none_title'), 'isi' => __('publik.tl.none_body')])
        @endif
    </div>
@else
    @if($minggu)
        @include('publik.pesan', ['jenis' => 'info', 'judul' => __('publik.tl.sunday_title'), 'isi' => __('publik.tl.sunday_body')])
    @elseif($diLuarMasa)
        @include('publik.pesan', ['jenis' => 'info', 'judul' => __('publik.tl.out_title'), 'isi' => __('publik.tl.out_body', [
            'name' => $periode->nama,
            'a' => $periode->tanggal_mulai->translatedFormat('j M Y'),
            'b' => $periode->tanggal_selesai->translatedFormat('j M Y'),
        ])])
    @endif

    {{-- HP: agenda per ruangan --}}
    <div class="pub-tumpuk hanya-hp">
        @foreach($barisan as $b)
            @include('publik.agenda', [
                'kode' => $b['kode'], 'meta' => $b['meta'],
                'daftar' => $perRuangan[$b['id']] ?? collect(),
                'menit' => $menitKini, 'hariIni' => $hariIni && ! $minggu,
            ])
        @endforeach
    </div>

    {{-- iPad dan desktop: sumbu waktu --}}
    <div class="bukan-hp">
        @include('publik.legenda', ['catatan' => $hariIni ? __('publik.tl.now_note', ['t' => now()->format('H:i')]) : null])
        @include('publik.sumbu', [
            'barisan' => $barisan, 'perRuangan' => $perRuangan, 'awal' => $awal, 'akhir' => $akhir,
            'hariIni' => $hariIni, 'menit' => $menitKini, 'celahPeta' => $celahPeta,
            'tampilStatus' => $hariIni && ! $minggu,
        ])
    </div>
@endif

@include('partials.rinci-jadwal')
