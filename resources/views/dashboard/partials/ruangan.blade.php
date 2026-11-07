{{-- Status terkini diisi public/js/publik.js dari /papan/data. Bila gagal, badge tetap tersembunyi. --}}
<div data-status-url="{{ route('papan.json') }}" class="pub-tumpuk">
    @include('publik.pesan', ['jenis' => 'peringatan', 'isi' => __('publik.status.load_failed'), 'idPesan' => 'statusGagal', 'sembunyi' => true])
    @include('publik.pesan', ['jenis' => 'info', 'isi' => __('publik.status.building_closed'), 'idPesan' => 'gedungTutup', 'sembunyi' => true])

    @foreach($ruanganStat->sortBy('urutan')->groupBy('lantai') as $lantai => $daftar)
        <section class="pub-grup">
            <div class="pub-grup-kepala">
                <h2>{{ __('publik.floor', ['n' => $lantai]) }}</h2>
                <span>{{ __('publik.rooms.count', ['n' => $daftar->count(), 'building' => $daftar->pluck('gedung')->unique()->join(', ')]) }}</span>
            </div>

            <div class="pub-grid">
                @foreach($daftar as $r)
                    @php
                        $obj = $ruanganObjek[$r['kode']] ?? null;
                        $tutup = ($penutupanAktif[$r['kode']] ?? collect())->first();
                        $fasilitas = $obj ? $obj->seluruh_fasilitas : [];
                    @endphp
                    <article class="pub-card pub-room" data-kode="{{ $r['kode'] }}">
                        @if($obj?->foto_url)
                            <img class="pub-room-foto" src="{{ $obj->foto_url }}" alt="{{ __('publik.rooms.photo_alt', ['code' => $r['kode']]) }}" loading="lazy">
                        @endif
                        <div class="pub-room-isi">
                            <div class="pub-card-atas">
                                <h3 class="pub-kode">{{ $r['kode'] }}</h3>
                                <span data-lencana class="pub-badge" hidden></span>
                            </div>
                            <p class="pub-teks-status" data-teks-status hidden></p>
                            <p class="pub-meta-utama">
                                {{ collect([
                                    __('publik.floor', ['n' => $r['lantai']]),
                                    __('publik.seats', ['n' => ($r['kapasitas_taksiran'] ? '± ' : '') . $r['kapasitas']]),
                                    $obj?->tipe,
                                    $r['gedung'],
                                ])->filter()->join(' · ') }}
                            </p>

                            @if($obj?->adalah_manual)
                                <p class="pub-meta">{{ __('publik.rooms.special') }}</p>
                            @endif

                            @if($tutup)
                                <div class="pub-tutup">
                                    @ikon('lock', 16)
                                    <div>
                                        <b>{{ __($tutup->sedang_berlaku ? 'publik.rooms.closed_reason' : 'publik.rooms.closing_soon', ['reason' => $tutup->alasan]) }}</b>
                                        <span>{{ $tutup->rentang_teks }}</span>
                                    </div>
                                </div>
                            @endif

                            @if(count($fasilitas))
                                <ul class="pub-fasilitas">
                                    @foreach($fasilitas as $f)
                                        <li>{{ $f['nama'] }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            @if($obj?->keterangan)
                                <p class="pub-meta">{{ $obj->keterangan }}</p>
                            @endif

                            <p class="pub-meta">{{ __('publik.rooms.week_count', ['n' => $r['jumlah_jadwal']]) }}</p>

                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
