{{--
    Sumbu waktu untuk iPad dan desktop (Papan dan Timeline).
    $barisan: [kode, meta, id]; $perRuangan; $awal; $akhir; $hariIni; $menit;
    $celahPeta opsional; $tampilStatus: badge di kolom ruangan.
--}}
@php
    use App\Support\StatusRuangan;
    use App\Support\TerjemahPesan;

    $rentang = $akhir - $awal;
    $posisi = fn ($m) => ($m - $awal) / $rentang * 100;
    $jamPenuh = (int) ceil($rentang / 60);
    $celahPeta = $celahPeta ?? collect();
    $tampilKini = $hariIni && $menit >= $awal && $menit <= $akhir;
@endphp
<div class="pub-axis-gulir" tabindex="0">
    <div class="pub-axis" style="--jumlah-jam:{{ $jamPenuh }}">
        <div class="pub-axis-kepala">
            <div class="pub-axis-ruang">{{ __('publik.tl.room') }}</div>
            <div class="pub-axis-jam">
                @for($m = $awal; $m < $akhir; $m += 60)
                    <span style="left:{{ $posisi($m) }}%">{{ StatusRuangan::jam($m) }}</span>
                @endfor
                @if($tampilKini)
                    <b class="pub-axis-kini-label" style="left:{{ $posisi($menit) }}%">{{ __('publik.event.now') }} {{ StatusRuangan::jam($menit) }}</b>
                @endif
            </div>
        </div>

        @foreach($barisan as $i => $b)
            @php
                $keg = ($perRuangan[$b['id']] ?? collect())->sortBy('mulai')->values();
                $status = ($tampilStatus ?? false) ? StatusRuangan::hitung($keg, $menit) : null;
            @endphp
            <div class="pub-axis-baris" @if($status) data-kode="{{ $b['kode'] }}" data-status="{{ $status['kunci'] }}" @endif>
                <div class="pub-axis-ruang">
                    <b class="pub-kode">{{ $b['kode'] }}</b>
                    <span class="pub-meta">{{ $b['meta'] }}</span>
                    @if($status)
                        <span data-lencana class="pub-badge pub-badge--{{ $status['kunci'] }}">
                            @ikon(StatusRuangan::IKON[$status['kunci']], 16)<span>{{ __('publik.status.' . $status['kunci']) }}</span>
                        </span>
                    @endif
                </div>
                <div class="pub-axis-lajur">
                    @foreach(($celahPeta[$b['id']]['celah'] ?? []) as $c)
                        <div class="pub-gap" style="left:{{ $posisi($c['mulai']) }}%;width:{{ ($c['selesai'] - $c['mulai']) / $rentang * 100 }}%">
                            {{ __('publik.event.gap', ['n' => round(($c['selesai'] - $c['mulai']) / 60, 1)]) }}
                        </div>
                    @endforeach

                    @foreach($keg as $x)
                        @php
                            $gaya = $x['jenis'] === 'lewat' ? 'kuliah' : $x['jenis'];
                            $judul = StatusRuangan::judul($x);
                            $jam = StatusRuangan::jam($x['mulai']) . '–' . StatusRuangan::jam($x['selesai']);
                            $rinci = StatusRuangan::rinci($x, $judul);
                            $pendek = $x['selesai'] - $x['mulai'] <= 60;
                            $menunggu = $x['status'] === 'menunggu';
                        @endphp
                        <button type="button"
                                class="pub-ev pub-ev--{{ $gaya }} {{ $menunggu ? 'pub-ev--menunggu' : '' }}"
                                style="left:{{ $posisi($x['mulai']) }}%;width:calc({{ ($x['selesai'] - $x['mulai']) / $rentang * 100 }}% - 2px)"
                                aria-label="{{ __('publik.tl.block_label', ['kind' => __('publik.event.' . $x['jenis']), 'title' => $judul, 'a' => StatusRuangan::jam($x['mulai']), 'b' => StatusRuangan::jam($x['selesai']), 'room' => $b['kode']]) }}"
                                @if($rinci) data-rinci="{{ $rinci }}" @endif>
                            <b>{{ $judul }}</b>
                            <span class="pub-angka">{{ $jam }}</span>
                            @if(! $pendek && ($x['ket'] || $menunggu))
                                <span>{{ collect([TerjemahPesan::teks($x['ket']), $menunggu ? __('publik.status.pending') : null])->filter()->join(' · ') }}</span>
                            @endif
                        </button>
                    @endforeach

                    @if($tampilKini)
                        <div class="pub-axis-kini" @if($i === 0) data-gulir-ke-sekarang @endif style="left:{{ $posisi($menit) }}%"></div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
