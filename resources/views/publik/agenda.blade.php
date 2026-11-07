{{--
    Kartu agenda untuk HP (Papan dan Timeline).
    $kode, $meta, $daftar (kegiatan ruangan itu), $menit, $hariIni,
    $saring (true bila ikut chip penyaring di Papan).
--}}
@php
    use App\Support\StatusRuangan;

    $urut = $daftar->sortBy('mulai')->values();
    $status = $hariIni ? StatusRuangan::hitung($urut, $menit) : null;
    $judulKeg = fn ($x) => StatusRuangan::judul($x);
@endphp
<article class="pub-card pub-agenda" @if($hariIni) data-kode="{{ $kode }}" data-status="{{ $status['kunci'] }}" @endif
         @if($saring ?? false) data-saring-kartu @endif>
    <div class="pub-card-atas">
        <h3 class="pub-kode">{{ $kode }}</h3>
        @if($status)
            <span data-lencana class="pub-badge pub-badge--{{ $status['kunci'] }}">
                @ikon(StatusRuangan::IKON[$status['kunci']], 16)<span>{{ __('publik.status.' . $status['kunci']) }}</span>
            </span>
        @endif
    </div>
    <p class="pub-meta">{{ $meta }}</p>

    @if($status)
        <dl class="pub-agenda-dl">
            @if($status['kunci'] === 'closed')
                <dt>{{ __('publik.event.now') }}</dt>
                <dd>
                    <button type="button" class="pub-tautan-blok" @if($r = StatusRuangan::rinci($status['kini'], $judulKeg($status['kini']))) data-rinci="{{ $r }}" @endif>
                        {{ $judulKeg($status['kini']) }}
                    </button>
                </dd>
            @else
                <dt>{{ __('publik.event.now') }}</dt>
                <dd>
                    @if($status['kini'])
                        <button type="button" class="pub-tautan-blok" @if($r = StatusRuangan::rinci($status['kini'], $judulKeg($status['kini']))) data-rinci="{{ $r }}" @endif>
                            {{ __('publik.event.until', ['what' => $judulKeg($status['kini']), 't' => StatusRuangan::jam($status['kini']['selesai'])]) }}
                        </button>
                    @else
                        <span data-teks-status>{{ $status['teks'] }}</span>
                    @endif
                </dd>
                <dt>{{ __('publik.page.next') }}</dt>
                <dd>
                    @if($status['lanjut'])
                        <button type="button" class="pub-tautan-blok" @if($r = StatusRuangan::rinci($status['lanjut'], $judulKeg($status['lanjut']))) data-rinci="{{ $r }}" @endif>
                            {{ __('publik.event.from', ['what' => $judulKeg($status['lanjut']), 't' => StatusRuangan::jam($status['lanjut']['mulai'])]) }}
                        </button>
                    @else
                        {{ __('publik.event.nothing') }}
                    @endif
                </dd>
            @endif
        </dl>
    @else
        <dl class="pub-agenda-dl">
            @forelse($urut as $x)
                <dt class="pub-angka">{{ StatusRuangan::jam($x['mulai']) }}</dt>
                <dd>
                    <button type="button" class="pub-tautan-blok" @if($r = StatusRuangan::rinci($x, $judulKeg($x))) data-rinci="{{ $r }}" @endif>
                        {{ $judulKeg($x) }}
                    </button>
                </dd>
            @empty
                <dd class="pub-agenda-kosong">{{ __('publik.status.free_rest') }}</dd>
            @endforelse
        </dl>
    @endif
</article>
