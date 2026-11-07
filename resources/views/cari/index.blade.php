@extends('layouts.app')
@section('publik', '1')
@section('judul', __('publik.find.title'))

@php
    use App\Support\TerjemahPesan;

    // Validasi bawaan Laravel tidak membawa penanda bukaModal, jadi pop-up
    // yang harus terbuka lagi juga ditentukan dari isian lama.
    $modalBuka = session('bukaModal');
    if (! $modalBuka && $errors->any()) {
        $modalBuka = old('jumlah_peserta') !== null ? 'ruangan' : (old('keperluan') !== null ? 'pergantian' : null);
    }

    // Baris pergantian yang ditolak, dipulihkan oleh skrip di bawah.
    $barisLama = [];
    if ($modalBuka === 'pergantian') {
        foreach ((array) old('baris', []) as $i => $b) {
            $j = isset($b['jadwal_id'])
                ? \App\Models\Jadwal::with(['mataKuliah', 'dosen', 'ruangan'])->find($b['jadwal_id'])
                : null;
            $galat = collect($errors->getMessages())
                ->filter(fn ($p, $k) => str_starts_with($k, "baris.{$i}."))
                ->flatten()
                ->map(fn ($p) => TerjemahPesan::teks($p))
                ->values()
                ->all();

            $barisLama[] = [
                'i' => (int) $i,
                'tanggal_asal' => $b['tanggal_asal'] ?? '',
                'ruangan_id' => $b['ruangan_id'] ?? '',
                'tanggal' => $b['tanggal'] ?? '',
                'mulai' => $b['mulai'] ?? '',
                'selesai' => $b['selesai'] ?? '',
                'jadwal_id' => $b['jadwal_id'] ?? '',
                'galat' => $galat,
                'jadwal' => $j ? [
                    'pindah' => [],
                    'id' => $j->id,
                    'kode' => $j->mataKuliah->kode ?? '',
                    'nama' => $j->mataKuliah->nama ?? '-',
                    'dosen' => $j->dosen->kode ?? '-',
                    'kelas' => $j->kelas,
                    'hari' => $j->nama_hari,
                    'hari_ke' => $j->hari,
                    'mulai' => substr($j->jam_mulai, 0, 5),
                    'selesai' => substr($j->jam_selesai, 0, 5),
                    'durasi' => $j->durasi_menit,
                    'ruangan' => $j->ruangan->kode ?? '-',
                    'ruangan_id' => $j->ruangan_id,
                ] : null,
            ];
        }
    }

    $catatanHasil = function ($h) {
        if ($h['kosong_penuh']) {
            return ['available', __('publik.find.free_all_day')];
        }
        if (($h['jeda_sebelum'] !== null && $h['jeda_sebelum'] < 30) || ($h['jeda_sesudah'] !== null && $h['jeda_sesudah'] < 30)) {
            return ['soon', __('publik.find.tight')];
        }

        return ['available', __('publik.find.good')];
    };
@endphp

@section('isi')
<div class="pub-page">
    <h1 class="pub-h1">{{ __('publik.find.title') }}</h1>
    <p class="pub-lead">{{ __('publik.find.lead') }} {{ __('publik.zone') }}.</p>

    <form method="GET" action="{{ route('cari.index') }}" class="pub-card pub-langkah">
        <input type="hidden" name="periode" value="{{ $periode->kode }}">
        <input type="hidden" name="cari" value="1">

        <h2 class="pub-sr">{{ __('publik.find.form_title') }}</h2>

        <div class="pub-form-baris pub-form-baris--cari">
            <div class="pub-field pub-area-tanggal">
                <label for="cTanggal">{{ __('publik.find.date') }}</label>
                <input type="date" id="cTanggal" name="tanggal" value="{{ $isian['tanggal'] }}">
            </div>
            <div class="pub-field">
                <label for="cMulai">{{ __('publik.find.start') }}</label>
                <input type="time" id="cMulai" name="mulai" value="{{ $isian['mulai'] }}" step="900">
            </div>
            <div class="pub-field">
                <label for="cSelesai">{{ __('publik.find.end') }}</label>
                <input type="time" id="cSelesai" name="selesai" value="{{ $isian['selesai'] }}" step="900">
            </div>
            <div class="pub-field">
                <label for="cKapasitas">{{ __('publik.find.min_seats') }}</label>
                <input type="number" id="cKapasitas" name="kapasitas" min="0" value="{{ $isian['kapasitas'] }}" placeholder="0">
            </div>
            <div class="pub-field">
                <label for="cGedung">{{ __('publik.find.building') }}</label>
                <select id="cGedung" name="gedung">
                    <option value="">{{ __('publik.find.all_buildings') }}</option>
                    @foreach($gedungs as $g)
                        <option value="{{ $g->id }}" @selected($isian['gedung'] == $g->id)>{{ $g->kode_pendek ?? $g->nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="pub-field pub-area-ruang">
                <label for="cRuangan">{{ __('publik.find.room_opt') }} <em>({{ __('publik.optional') }})</em></label>
                <input type="text" id="cRuangan" name="ruangan" value="{{ $isian['ruangan'] }}" placeholder="{{ __('publik.find.room_ph') }}" aria-describedby="cRuanganHint">
                <span class="hint" id="cRuanganHint">{{ __('publik.find.room_hint') }}</span>
            </div>
            <button type="submit" class="pub-btn pub-btn--primary pub-btn--penuh-hp pub-area-tombol">@ikon('search', 20) {{ __('publik.find.search') }}</button>
        </div>
    </form>

    @isset($pesanGalat)
        @include('publik.pesan', ['jenis' => 'galat', 'isi' => TerjemahPesan::teks($pesanGalat)])
    @endisset

    {{-- Dua jalur pengajuan. Kriteria di dalam pop-up masih sementara
         dan gampang diubah begitu unit sudah menetapkan syaratnya. --}}
    <section class="pub-card pub-cta">
        <div>
            <h2>{{ __('publik.find.cta_title') }}</h2>
            <p class="pub-meta">{{ __('publik.find.cta_body') }}</p>
        </div>
        <div class="pub-cta-aksi">
            <button class="pub-btn pub-btn--secondary" type="button" data-aturan="ruangan">{{ __('publik.find.cta_room') }}</button>
            <button class="pub-btn pub-btn--secondary" type="button" data-aturan="pergantian">{{ __('publik.find.cta_reschedule') }}</button>
        </div>
    </section>

    @isset($bentrok)
        @if($bentrok)
            <section class="pub-card pub-langkah">
                <h2 class="pub-h2">{{ __('publik.find.conflict_title', ['code' => $bentrok['ruangan']->kode]) }}</h2>
                @php
                    $adaHalangan = $bentrok['daftar']->count()
                        || ($bentrok['reservasi'] ?? collect())->count()
                        || ($bentrok['pengganti'] ?? collect())->count()
                        || ($bentrok['ditutup'] ?? null);
                @endphp

                @if($bentrok['ditutup'] ?? null)
                    <div class="pub-tutup">
                        @ikon('lock', 16)
                        <div>{{ __('publik.find.closed_msg', ['code' => $bentrok['ruangan']->kode, 'period' => $bentrok['ditutup']->rentang_teks, 'reason' => $bentrok['ditutup']->alasan]) }}</div>
                    </div>
                @endif

                @if($adaHalangan)
                    @foreach($bentrok['daftar'] as $j)
                        <div class="pub-bentrok">
                            <span class="pub-angka">{{ $j->rentang_jam }}</span>
                            <b>{{ $j->mataKuliah->nama ?? '-' }}</b>
                            <span class="pub-meta">
                                {{ __('publik.find.col_class') }} {{ $j->kelas }} ·
                                {{ __('publik.find.col_lecturer') }} {{ $j->dosen->kode ?? '-' }} ·
                                {{ __('publik.sched.students', ['n' => $j->jml_mhs]) }}
                            </span>
                        </div>
                    @endforeach

                    @if(($bentrok['reservasi'] ?? collect())->count())
                        <div class="pub-msg pub-msg--info">
                            @ikon('calendar-check', 20)
                            <div class="pub-msg-isi">
                                <b>{{ __('publik.find.booked') }}</b>
                                @foreach($bentrok['reservasi'] as $rsv)
                                    <div>
                                        {{ $rsv->rentang_jam }} — {{ __('publik.event.reserved') }}
                                        · @pesan($rsv->label_jenis)
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(($bentrok['pengganti'] ?? collect())->count())
                        <div class="pub-msg pub-msg--info">
                            @ikon('repeat', 20)
                            <div class="pub-msg-isi">
                                <b>{{ __('publik.find.replacement_used') }}</b>
                                @foreach($bentrok['pengganti'] as $pg)
                                    <div>
                                        {{ $pg->rentang_jam }} —
                                        {{ $pg->jadwal->mataKuliah->nama ?? __('publik.event.ganti') }}
                                        ({{ $pg->jadwal->kelas ?? '-' }}) ·
                                        {{ __('publik.find.replacement_of', ['date' => $pg->tanggal_asal->translatedFormat('j M Y')]) }}
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @include('publik.pesan', ['jenis' => 'peringatan', 'isi' => __('publik.find.unusable')])
                @else
                    @unless($bentrok['ditutup'] ?? null)
                        @include('publik.pesan', ['jenis' => 'sukses', 'isi' => __('publik.find.room_free', ['code' => $bentrok['ruangan']->kode])])
                    @endunless
                @endif
            </section>
        @endif
    @endisset

    @isset($hasil)
        @if($hasil)
            <section class="pub-tumpuk">
                <div>
                    <h2 class="pub-h2">{{ __('publik.find.results_title') }}</h2>
                    <p class="pub-meta">{{ $isian['nama_hari'] }} · {{ $isian['mulai'] }}–{{ $isian['selesai'] }}. {{ __('publik.find.results_sub') }}</p>
                </div>

                @isset($penutupanHariItu)
                    @if($penutupanHariItu->count())
                        <div class="pub-msg pub-msg--peringatan">
                            @ikon('lock', 20)
                            <div class="pub-msg-isi">
                                <b>{{ __('publik.find.closed_list', ['n' => $penutupanHariItu->count()]) }}</b>
                                @foreach($penutupanHariItu as $t)
                                    <div><span class="pub-kode">{{ $t->ruangan->kode }}</span>: {{ $t->alasan }} ({{ $t->rentang_teks }})</div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endisset

                @isset($jadwalBerlaku)
                    @unless($jadwalBerlaku)
                        @include('publik.pesan', ['jenis' => 'info', 'judul' => __('publik.find.out_term_title'), 'isi' => __('publik.find.out_term_body')])
                    @endunless
                @endisset

                <div class="pub-stats pub-stats--tiga pub-stats--cari">
                    <div class="pub-stat pub-stat--ok">
                        <span>{{ __('publik.find.stat_free') }}</span>
                        <b>{{ $hasil->count() }}</b>
                    </div>
                    <div class="pub-stat pub-stat--busy">
                        <span>{{ __('publik.find.stat_used') }}</span>
                        <b>{{ $terpakai }}</b>
                        <small>
                            {{ (isset($jadwalBerlaku) && ! $jadwalBerlaku) ? __('publik.find.stat_used_out') : __('publik.find.stat_used_sub') }}
                        </small>
                    </div>
                    <div class="pub-stat">
                        <span>{{ __('publik.find.stat_range') }}</span>
                        <b class="pub-stat-kecil">{{ $isian['mulai'] }}–{{ $isian['selesai'] }}</b>
                        <small>
                            {{ collect([
                                __('publik.find.stat_range_sub', ['n' => $durasi]),
                                $isian['kapasitas'] ? __('publik.find.stat_seats', ['k' => $isian['kapasitas']]) : null,
                                ($pekanKe ?? null) ? __('publik.week', ['n' => $pekanKe]) : null,
                            ])->filter()->join(' · ') }}
                        </small>
                    </div>
                </div>

                @if($hasil->count())
                    {{-- HP dan iPad: kartu --}}
                    <div class="pub-grid pub-grid--dua bukan-lebar">
                        @foreach($hasil as $h)
                            @php [$kunciCatatan, $teksCatatan] = $catatanHasil($h); @endphp
                            <article class="pub-card pub-hasil">
                                <div class="pub-card-atas">
                                    <h3 class="pub-kode">{{ $h['ruangan']->kode }}</h3>
                                    @include('publik.lencana', ['kunci' => $kunciCatatan, 'teks' => $teksCatatan])
                                </div>
                                <p class="pub-meta-utama">
                                    {{ __('publik.floor', ['n' => $h['ruangan']->lantai]) }} ·
                                    {{ __('publik.seats', ['n' => ($h['ruangan']->kapasitas_perkiraan ? '± ' : '') . $h['ruangan']->kapasitas_efektif]) }} ·
                                    {{ $h['ruangan']->gedung->kode_pendek ?? $h['ruangan']->gedung->nama }}
                                </p>
                                <p class="pub-meta">
                                    {{ $h['sebelum'] ? __('publik.find.before_line', ['t' => substr($h['sebelum']->jam_selesai, 0, 5)]) : __('publik.find.no_before') }}<br>
                                    {{ $h['sesudah'] ? __('publik.find.after_line', ['t' => substr($h['sesudah']->jam_mulai, 0, 5)]) : __('publik.find.no_after') }}
                                </p>
                                <button type="button" class="pub-btn pub-btn--secondary pub-btn--penuh" data-aturan="ruangan" data-ruang="{{ $h['ruangan']->id }}">
                                    {{ __('publik.find.reserve_room') }}
                                </button>
                            </article>
                        @endforeach
                    </div>

                    {{-- Desktop: tabel --}}
                    <div class="pub-table-wrap hanya-lebar" tabindex="0">
                        <table class="pub-table">
                            <thead><tr>
                                <th>{{ __('publik.find.col_room') }}</th>
                                <th>{{ __('publik.find.col_building') }}</th>
                                <th>{{ __('publik.find.col_floor') }}</th>
                                <th>{{ __('publik.find.col_capacity') }}</th>
                                <th>{{ __('publik.find.col_before') }}</th>
                                <th>{{ __('publik.find.col_after') }}</th>
                                <th>{{ __('publik.find.col_note') }}</th>
                                <th><span class="pub-sr">{{ __('publik.find.reserve') }}</span></th>
                            </tr></thead>
                            <tbody>
                            @foreach($hasil as $h)
                                @php [$kunciCatatan, $teksCatatan] = $catatanHasil($h); @endphp
                                <tr>
                                    <td class="pub-kode">{{ $h['ruangan']->kode }}</td>
                                    <td>{{ $h['ruangan']->gedung->kode_pendek ?? $h['ruangan']->gedung->nama }}</td>
                                    <td class="pub-angka">{{ $h['ruangan']->lantai }}</td>
                                    <td class="pub-angka">{{ $h['ruangan']->kapasitas_perkiraan ? '± ' : '' }}{{ $h['ruangan']->kapasitas_efektif }}</td>
                                    <td>
                                        @if($h['sebelum'])
                                            <span class="pub-angka">{{ __('publik.find.ends', ['t' => substr($h['sebelum']->jam_selesai, 0, 5)]) }}</span>
                                            <div class="pub-meta">{{ $h['sebelum']->mataKuliah->nama ?? '-' }}</div>
                                        @else
                                            {{ __('publik.event.nothing') }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($h['sesudah'])
                                            <span class="pub-angka">{{ __('publik.find.starts', ['t' => substr($h['sesudah']->jam_mulai, 0, 5)]) }}</span>
                                            <div class="pub-meta">{{ $h['sesudah']->mataKuliah->nama ?? '-' }}</div>
                                        @else
                                            {{ __('publik.event.nothing') }}
                                        @endif
                                    </td>
                                    <td>@include('publik.lencana', ['kunci' => $kunciCatatan, 'teks' => $teksCatatan])</td>
                                    <td>
                                        <button type="button" class="pub-btn pub-btn--secondary pub-btn--kecil" data-aturan="ruangan" data-ruang="{{ $h['ruangan']->id }}">
                                            {{ __('publik.find.reserve') }}
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="pub-card">
                        @include('publik.kosong', [
                            'ikon' => 'search-x', 'judul' => __('publik.find.none_title'), 'isi' => __('publik.find.none_body'),
                            'aksi' => __('publik.dash.tab_timeline'), 'tautan' => route('dashboard', 'timeline'),
                        ])
                    </div>
                @endif
            </section>
        @endif
    @endisset

    @empty($hasil)
        @include('publik.pesan', ['jenis' => 'info', 'judul' => __('publik.find.example_title'), 'isi' => __('publik.find.example')])
    @endempty
</div>


{{-- ============ POP-UP KETENTUAN ============ --}}
@foreach(['ruangan', 'pergantian'] as $jenisAturan)
    @php $aturan = config('peraturan.' . $jenisAturan); @endphp
    <div class="modal" id="aturan{{ ucfirst($jenisAturan) }}" data-modal-ketentuan hidden>
        <div class="modal-kotak modal-kotak--sempit" role="dialog" aria-modal="true" aria-labelledby="judulAturan{{ $jenisAturan }}">
            <div class="modal-atas">
                <b id="judulAturan{{ $jenisAturan }}" tabindex="-1" data-judul-modal lang="en">{{ $aturan['judul'] }}</b>
                <button class="modal-tutup" type="button" data-tutup aria-label="{{ __('publik.close') }}">@ikon('x', 24)</button>
            </div>
            <div class="modal-isi" lang="en">
                <ol class="pub-ketentuan">
                    @foreach($aturan['butir'] as $butir)
                        <li>{{ $butir }}</li>
                    @endforeach
                </ol>

                <p class="pub-meta">{{ $aturan['penutup'] }}</p>
            </div>
            <div class="modal-aksi">
                <label class="pub-setuju">
                    <input type="checkbox" class="setuju-aturan" data-untuk="{{ $jenisAturan }}">
                    <span>{{ __('publik.form.agree') }}</span>
                </label>
                <div class="modal-aksi-tombol">
                    <button class="pub-btn pub-btn--ghost" type="button" data-tutup>{{ __('publik.cancel') }}</button>
                    <button class="pub-btn pub-btn--primary lanjut-aturan" type="button"
                            data-untuk="{{ $jenisAturan }}" disabled>{{ __('publik.continue') }}</button>
                </div>
            </div>
        </div>
    </div>
@endforeach

{{-- ================= POP-UP RESERVASI RUANGAN ================= --}}
<div class="modal" id="modalRuangan" @if($modalBuka !== 'ruangan') hidden @endif>
    <div class="modal-kotak" role="dialog" aria-modal="true" aria-labelledby="judulRuangan">
        <div class="modal-atas">
            <b id="judulRuangan" tabindex="-1" data-judul-modal>{{ __('publik.find.cta_room') }}</b>
            <button class="modal-tutup" type="button" data-tutup aria-label="{{ __('publik.close') }}">@ikon('x', 24)</button>
        </div>
        <form method="POST" action="{{ route('pengajuan.store', 'ruangan') }}" class="modal-form">
            @csrf
            <div class="modal-isi">
                @if($modalBuka === 'ruangan')
                    @include('publik.ringkasan-galat')
                @endif

                <div class="pub-form-dua">
                    <div class="pub-field">
                        <label for="rNama">{{ __('publik.form.applicant') }}</label>
                        <input type="text" id="rNama" name="nama" value="{{ old('nama') }}" required autocomplete="name">
                        @include('publik.galat', ['nama' => 'nama', 'id' => 'rNama'])
                    </div>
                    <div class="pub-field">
                        <label for="rIdentitas">{{ __('publik.form.identity') }} <em>({{ __('publik.optional') }})</em></label>
                        <input type="text" id="rIdentitas" name="identitas" value="{{ old('identitas') }}">
                        <span class="hint">{{ __('publik.form.identity_hint') }}</span>
                    </div>
                    <div class="pub-field">
                        <label for="peranReservasi">{{ __('publik.form.status') }}</label>
                        <select name="peran" id="peranReservasi" required>
                            <option value="">{{ __('publik.choose') }}</option>
                            @foreach(['Dosen', 'Mahasiswa International', 'Lainnya'] as $o)
                                <option value="{{ $o }}" @selected(old('peran') === $o)>{{ __('publik.form.peran.' . $o) }}</option>
                            @endforeach
                        </select>
                    </div>
                    {{-- Hanya ditanyakan bila statusnya "Lainnya"; dosen dan
                         mahasiswa international sudah jelas unitnya. --}}
                    <div class="pub-field" id="unitReservasi" @unless(old('peran') === 'Lainnya') hidden @endunless>
                        <label for="rUnit">{{ __('publik.form.unit') }}</label>
                        <input type="text" id="rUnit" name="unit" value="{{ old('unit') }}" placeholder="{{ __('publik.form.unit_ph') }}">
                    </div>
                    <div class="pub-field">
                        <label for="rKontak">{{ __('publik.form.whatsapp') }}</label>
                        <input type="tel" id="rKontak" name="kontak" value="{{ old('kontak') }}" required placeholder="{{ __('publik.form.whatsapp_ph') }}" autocomplete="tel">
                        <span class="hint">{{ __('publik.form.whatsapp_hint') }}</span>
                        @include('publik.galat', ['nama' => 'kontak', 'id' => 'rKontak'])
                    </div>
                    <div class="pub-field">
                        <label for="rPeserta">{{ __('publik.form.participants') }}</label>
                        <input type="number" id="rPeserta" name="jumlah_peserta" min="1" value="{{ old('jumlah_peserta', 20) }}" required>
                    </div>
                    <div class="pub-field">
                        <label for="rTanggal">{{ __('publik.form.use_date') }}</label>
                        <input type="date" id="rTanggal" name="tanggal" data-isi="tanggal" value="{{ old('tanggal') }}" required>
                        @include('publik.galat', ['nama' => 'tanggal', 'id' => 'rTanggal'])
                    </div>
                    <div class="pub-field">
                        <label for="ruanganReservasi">{{ __('publik.form.room_req') }}</label>
                        <select name="ruangan_id" id="ruanganReservasi" required>
                            <option value="">{{ __('publik.form.choose_room') }}</option>
                            @foreach($daftarRuangan as $r)
                                <option value="{{ $r->id }}" @selected(old('ruangan_id') == $r->id)>
                                    {{ $r->kode }} — {{ $r->gedung->kode_pendek ?? $r->gedung->nama }}
                                </option>
                            @endforeach
                        </select>
                        @include('publik.galat', ['nama' => 'ruangan_id', 'id' => 'ruanganReservasi'])
                    </div>
                    <div class="pub-field">
                        <label for="mulaiReservasi">{{ __('publik.find.start') }}</label>
                        <input type="time" name="mulai" id="mulaiReservasi" data-isi="mulai" value="{{ old('mulai') }}" required>
                        @include('publik.galat', ['nama' => 'mulai', 'id' => 'mulaiReservasi'])
                    </div>
                    <div class="pub-field">
                        <label for="selesaiReservasi">{{ __('publik.find.end') }}</label>
                        <input type="time" name="selesai" id="selesaiReservasi" data-isi="selesai" value="{{ old('selesai') }}" required>
                        @include('publik.galat', ['nama' => 'selesai', 'id' => 'selesaiReservasi'])
                    </div>
                </div>

                <div class="pub-field">
                    <label for="rKeperluan">{{ __('publik.form.purpose') }}</label>
                    <textarea id="rKeperluan" name="keperluan" rows="3" required placeholder="{{ __('publik.form.purpose_ph') }}">{{ old('keperluan') }}</textarea>
                    @include('publik.galat', ['nama' => 'keperluan', 'id' => 'rKeperluan'])
                </div>

                {{-- Jalan keluar bila ruangan yang diminta ternyata sudah terpakai --}}
                @if(session('bukaModal') === 'ruangan' && session('saran') && count(session('saran')['ruangan']))
                    <div class="pub-msg pub-msg--sukses">
                        @ikon('circle-check', 20)
                        <div class="pub-msg-isi">
                            <b>{{ __('publik.form.suggest_click') }}</b>
                            <div class="pub-saran">
                                @foreach(session('saran')['ruangan'] as $r)
                                    <button type="button" class="pub-btn pub-btn--secondary pub-btn--kecil" data-pilih-ruangan-rsv="{{ $r['id'] }}">
                                        {{ $r['kode'] }} · {{ $r['taksiran'] ? '±' : '' }}{{ $r['kapasitas'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="modal-aksi">
                <div class="modal-aksi-tombol">
                    <button class="pub-btn pub-btn--ghost" type="button" data-tutup>{{ __('publik.cancel') }}</button>
                    <button class="pub-btn pub-btn--primary" type="submit">{{ __('publik.submit_request') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ============ POP-UP RESERVASI PERGANTIAN JADWAL ============ --}}
<div class="modal" id="modalPergantian" @if($modalBuka !== 'pergantian') hidden @endif>
    <div class="modal-kotak modal-kotak--lebar" role="dialog" aria-modal="true" aria-labelledby="judulPergantian">
        <div class="modal-atas">
            <b id="judulPergantian" tabindex="-1" data-judul-modal>{{ __('publik.form.reschedule_title') }}</b>
            <button class="modal-tutup" type="button" data-tutup aria-label="{{ __('publik.close') }}">@ikon('x', 24)</button>
        </div>

        <form method="POST" action="{{ route('pengajuan.store', 'pergantian') }}" class="modal-form">
            @csrf
            <div class="modal-isi">
                @if($modalBuka === 'pergantian')
                    @include('publik.ringkasan-galat')
                @endif

                {{-- Identitas pemohon --}}
                <div class="pub-form-dua">
                    <div class="pub-field">
                        <label for="gNama">{{ __('publik.form.applicant') }}</label>
                        <input type="text" id="gNama" name="nama" value="{{ old('nama') }}" required autocomplete="name">
                        @include('publik.galat', ['nama' => 'nama', 'id' => 'gNama'])
                    </div>
                    <div class="pub-field">
                        <label for="gIdentitas">{{ __('publik.form.identity') }} <em>({{ __('publik.optional') }})</em></label>
                        <input type="text" id="gIdentitas" name="identitas" value="{{ old('identitas') }}">
                        <span class="hint">{{ __('publik.form.identity_hint') }}</span>
                    </div>
                    <div class="pub-field">
                        <label for="gPeran">{{ __('publik.form.status') }}</label>
                        <select id="gPeran" name="peran" required>
                            <option value="">{{ __('publik.choose') }}</option>
                            @foreach(['Dosen', 'Mahasiswa International'] as $o)
                                <option value="{{ $o }}" @selected(old('peran') === $o)>{{ __('publik.form.peran.' . $o) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pub-field">
                        <label for="gUnit">{{ __('publik.form.program') }} <em>({{ __('publik.optional') }})</em></label>
                        <input type="text" id="gUnit" name="unit" value="{{ old('unit') }}">
                    </div>
                    <div class="pub-field">
                        <label for="gKontak">{{ __('publik.form.whatsapp') }}</label>
                        <input type="tel" id="gKontak" name="kontak" value="{{ old('kontak') }}" required placeholder="{{ __('publik.form.whatsapp_ph') }}" autocomplete="tel">
                        <span class="hint">{{ __('publik.form.whatsapp_hint') }}</span>
                        @include('publik.galat', ['nama' => 'kontak', 'id' => 'gKontak'])
                    </div>
                </div>

                {{-- Daftar pergantian. Bisa ditambah sebanyak yang diperlukan. --}}
                <div id="daftarBaris"></div>

                <div>
                    <button class="pub-btn pub-btn--secondary" type="button" id="tambahBaris">@ikon('plus', 20) {{ __('publik.form.add_row') }}</button>
                </div>

                @include('publik.galat', ['nama' => 'baris', 'id' => 'gBaris'])

                {{-- Jalan keluar bila ada baris yang bentrok --}}
                @if(session('bukaModal') === 'pergantian' && session('saran'))
                    @php $saran = session('saran'); @endphp
                    @if(($saran['jenis'] === 'ruangan' && count($saran['ruangan'])) || ($saran['jenis'] === 'waktu' && count($saran['waktu'])))
                        <div class="pub-msg pub-msg--sukses">
                            @ikon('circle-check', 20)
                            <div class="pub-msg-isi">
                                @if($saran['jenis'] === 'ruangan')
                                    <b>{{ __('publik.form.suggest_room') }}</b>
                                    <div class="pub-saran">
                                        @foreach($saran['ruangan'] as $r)
                                            <span class="pub-tag">{{ $r['kode'] }} · {{ $r['taksiran'] ? '±' : '' }}{{ $r['kapasitas'] }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <b>{{ __('publik.form.suggest_time') }}</b>
                                    <div class="pub-saran">
                                        @foreach($saran['waktu'] as $w)
                                            <span class="pub-tag">{{ __('publik.form.suggest_time_item', ['a' => $w['mulai'], 'b' => $w['selesai'], 'n' => $w['jumlah_ruang']]) }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endif

                <div class="pub-field">
                    <label for="gKeperluan">{{ __('publik.form.reason') }}</label>
                    <textarea id="gKeperluan" name="keperluan" rows="3" required placeholder="{{ __('publik.form.reason_ph') }}">{{ old('keperluan') }}</textarea>
                    @include('publik.galat', ['nama' => 'keperluan', 'id' => 'gKeperluan'])
                </div>
            </div>

            <div class="modal-aksi">
                <div class="modal-aksi-tombol">
                    <button class="pub-btn pub-btn--ghost" type="button" data-tutup>{{ __('publik.cancel') }}</button>
                    <button class="pub-btn pub-btn--primary" type="submit">{{ __('publik.submit_request') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Cetakan satu baris pergantian, digandakan lewat skrip --}}
<template id="cetakanBaris">
    <div class="baris-ganti">
        <div class="baris-kepala">
            <b class="baris-nomor">{{ __('publik.js.row', ['n' => 1]) }}</b>
            <button class="pub-btn pub-btn--danger pub-btn--kecil hapus-baris" type="button">{{ __('publik.form.remove') }}</button>
        </div>

        <div class="pub-field pub-field--cari-mk">
            <label>{{ __('publik.form.course_move') }}
                <input type="text" class="cari-mk" autocomplete="off" placeholder="{{ __('publik.form.course_ph') }}">
            </label>
            <div class="hasil-cari" hidden></div>
            <input type="hidden" class="isi-jadwal" name="baris[__I__][jadwal_id]">
        </div>

        <div class="kartu-jadwal" hidden></div>

        <div class="pub-form-dua">
            <div class="pub-field"><label>{{ __('publik.form.date_cancel') }}
                <input type="date" class="isi-asal" name="baris[__I__][tanggal_asal]" required></label>
                <span class="hint petunjuk-hari">{{ __('publik.form.date_cancel_hint') }}</span></div>
            <div class="pub-field"><label>{{ __('publik.form.room_replace') }}
                <select class="isi-ruangan" name="baris[__I__][ruangan_id]" required>
                    <option value="">{{ __('publik.form.choose_room') }}</option>
                    @foreach($daftarRuangan as $r)
                        <option value="{{ $r->id }}">{{ $r->kode }} — {{ $r->gedung->kode_pendek ?? $r->gedung->nama }}</option>
                    @endforeach
                </select></label></div>
            <div class="pub-field"><label>{{ __('publik.form.date_replace') }}
                <input type="date" class="isi-tanggal" name="baris[__I__][tanggal]" required></label></div>
            <div class="pub-form-jam">
                <div class="pub-field"><label>{{ __('publik.find.start') }}
                    <input type="time" class="isi-mulai" name="baris[__I__][mulai]" required></label></div>
                <div class="pub-field"><label>{{ __('publik.find.end') }}
                    <input type="time" class="isi-selesai" name="baris[__I__][selesai]" required></label>
                    <span class="hint catatan-durasi"></span></div>
            </div>
        </div>
    </div>
</template>
@endsection

@push('skrip')
<script>
(function () {
    var TEKS = @json(__('publik.js'));
    var BARIS_LAMA = @json($barisLama);

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }
    function teks(kunci, ganti) {
        var t = TEKS[kunci] || kunci;
        Object.keys(ganti || {}).forEach(function (k) { t = t.split(':' + k).join(ganti[k]); });
        return t;
    }
    function hariTeks(nama) { return (TEKS.day_map && TEKS.day_map[nama]) || nama; }

    var ringkasan = document.querySelector('[data-ringkasan-galat]');

    // Isi otomatis tanggal dan jam dari kotak pencarian di atas.
    function salinDariPencarian(modal) {
        var sumber = {
            tanggal: document.querySelector('input[name="tanggal"]'),
            mulai:   document.querySelector('input[name="mulai"]'),
            selesai: document.querySelector('input[name="selesai"]')
        };
        modal.querySelectorAll('[data-isi]').forEach(function (el) {
            var s = sumber[el.dataset.isi];
            if (s && s.value && !el.value) { el.value = s.value; }
        });
    }


    // ================= Pergantian jadwal (banyak baris) =================
    var daftarBaris = document.getElementById('daftarBaris');
    var cetakan     = document.getElementById('cetakanBaris');
    var tombolTambah = document.getElementById('tambahBaris');
    var HARI = TEKS.days;
    var nomorBaris = 0;

    function keMenit(t) {
        if (!t) { return null; }
        var b = t.split(':');
        return parseInt(b[0], 10) * 60 + parseInt(b[1], 10);
    }

    function keJam(m) {
        m = ((m % 1440) + 1440) % 1440;
        return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
    }

    function teksDurasi(menit) {
        var jam = Math.floor(menit / 60), sisa = menit % 60;
        return (jam ? jam + ' ' + TEKS.hour : '') + (sisa ? (jam ? ' ' : '') + sisa + ' ' + TEKS.minute : '');
    }

    function nomoriUlang() {
        daftarBaris.querySelectorAll('.baris-ganti').forEach(function (el, i) {
            el.querySelector('.baris-nomor').textContent = teks('row', {n: i + 1});
            el.querySelector('.hapus-baris').style.display =
                daftarBaris.querySelectorAll('.baris-ganti').length > 1 ? '' : 'none';
        });
    }

    function pasangBaris(el) {
        var cari      = el.querySelector('.cari-mk');
        var hasil     = el.querySelector('.hasil-cari');
        var isiJadwal = el.querySelector('.isi-jadwal');
        var kartu     = el.querySelector('.kartu-jadwal');
        var isiAsal   = el.querySelector('.isi-asal');
        var petunjuk  = el.querySelector('.petunjuk-hari');
        var isiMulai  = el.querySelector('.isi-mulai');
        var isiSelesai = el.querySelector('.isi-selesai');
        var isiRuangan = el.querySelector('.isi-ruangan');
        var catatan   = el.querySelector('.catatan-durasi');
        var pilih = null;
        var jeda = null;

        function tampilkanKartu() {
            if (!pilih) { return; }
            kartu.hidden = false;
            var isi = '<b>' + esc(pilih.kode) + ' · ' + esc(pilih.nama) + '</b><br>' +
                esc(TEKS['class']) + ' <b>' + esc(pilih.kelas) + '</b> · ' + esc(TEKS.lecturer) + ' <b>' + esc(pilih.dosen) + '</b> · ' +
                esc(TEKS.originally) + ' <b>' + esc(hariTeks(pilih.hari)) + ' ' + esc(pilih.mulai) + '–' + esc(pilih.selesai) +
                '</b> ' + esc(TEKS['in']) + ' <b>' + esc(pilih.ruangan) + '</b> (' + esc(teksDurasi(pilih.durasi)) + ')';

            // Pertemuan yang sudah pernah dipindah, supaya pemohon tidak
            // memilih tanggal yang pertemuannya sudah tidak ada.
            var dipindah = (pilih.pindah || []);
            if (dipindah.length) {
                isi += '<div class="pub-jadwal-pindah">' + esc(TEKS.moved);
                dipindah.forEach(function (p) {
                    // Pemindahan yang tujuannya sudah dipindah lagi tidak
                    // berlaku lagi — ditandai agar rantainya terbaca.
                    var lanjut = cariPemindahan(p.ke, 'dari');
                    isi += '<br>· ' + esc(p.dari_teks) + ' → <b>' + esc(p.ke_teks) + '</b>'
                         + (p.status === 'disetujui' ? '' : ' ' + esc(TEKS.awaiting))
                         + (lanjut ? ' ' + esc(TEKS.moved_again) : '');
                });
                isi += '</div>';
            }

            kartu.innerHTML = isi;
            samakanDurasi();
        }

        function samakanDurasi() {
            if (!pilih || !isiMulai.value) { return; }
            isiSelesai.value = keJam(keMenit(isiMulai.value) + pilih.durasi);
            periksaDurasi();
        }

        function periksaDurasi() {
            if (!pilih) { catatan.textContent = ''; return; }
            var nyata = keMenit(isiSelesai.value) - keMenit(isiMulai.value);
            if (nyata === pilih.durasi) {
                catatan.textContent = TEKS.same_duration;
                catatan.style.color = 'var(--ic-ok-text)';
            } else if (nyata > 0) {
                catatan.textContent = teks('duration_diff', {a: teksDurasi(pilih.durasi), b: teksDurasi(nyata)});
                catatan.style.color = 'var(--ic-soon-text)';
            }
        }

        /* Pemindahan yang sudah tercatat untuk mata kuliah terpilih.
           Satu pertemuan hanya bisa dipindah sekali; yang berikutnya
           dipindah adalah pertemuan penggantinya. */
        function cariPemindahan(tgl, arah) {
            var daftar = (pilih && pilih.pindah) || [];
            for (var i = 0; i < daftar.length; i++) {
                if (daftar[i][arah] === tgl) { return daftar[i]; }
            }
            return null;
        }

        /* Telusuri rantai sampai ujung: 12 Okt -> 13 Okt -> 20 Okt
           menghasilkan pemindahan 13 -> 20, supaya peringatan menyebut
           tanggal yang benar-benar berlaku sekarang. */
        function tujuanAkhir(tgl) {
            var kini = tgl, awal = null, akhir = null, lewat = {};
            while (!lewat[kini]) {
                lewat[kini] = true;
                var lanjut = cariPemindahan(kini, 'dari');
                if (!lanjut) { break; }
                if (!awal) { awal = lanjut; }
                akhir = lanjut;
                kini = lanjut.ke;
            }
            return akhir ? { awal: awal, akhir: akhir } : null;
        }

        function periksaHari() {
            if (!pilih || !isiAsal.value) { return; }

            // Sudah pernah dipindah? Tunjukkan ke mana, jangan biarkan
            // pemohon mengisi terus lalu ditolak setelah mengirim.
            var rantai = tujuanAkhir(isiAsal.value);
            if (rantai) {
                var tujuan = rantai.akhir;
                petunjuk.textContent = teks('moved_note', {
                    a: rantai.awal.dari_teks, b: tujuan.ke_teks,
                    c: tujuan.status === 'disetujui' ? '.' : TEKS.awaiting_inline
                });
                petunjuk.style.color = 'var(--ic-brand-red-dark)';
                return;
            }

            // Tanggal pengganti dari pemindahan sebelumnya memang bukan hari
            // kuliah aslinya, tetapi sah dibatalkan.
            var pengganti = cariPemindahan(isiAsal.value, 'ke');
            if (pengganti) {
                petunjuk.textContent = teks('replacement_note', {a: pengganti.dari_teks});
                petunjuk.style.color = 'var(--ic-ok-text)';
                return;
            }

            var h = (new Date(isiAsal.value + 'T00:00:00').getDay() + 6) % 7;
            if (h !== pilih.hari_ke) {
                petunjuk.textContent = teks('wrong_day', {day: HARI[h], class_day: hariTeks(pilih.hari)});
                petunjuk.style.color = 'var(--ic-brand-red-dark)';
            } else {
                petunjuk.textContent = teks('right_day', {day: hariTeks(pilih.hari)});
                petunjuk.style.color = 'var(--ic-ok-text)';
            }
        }

        cari.addEventListener('input', function () {
            clearTimeout(jeda);
            var kata = cari.value.trim();
            if (kata.length < 2) { hasil.hidden = true; return; }

            jeda = setTimeout(function () {
                fetch('{{ route('jadwal.cari') }}?q=' + encodeURIComponent(kata))
                    .then(function (r) { return r.json(); })
                    .then(function (daftar) {
                        hasil.hidden = false;
                        if (!daftar.length) {
                            hasil.innerHTML = '<div class="hasil-kosong">' + esc(TEKS.no_match) + '</div>';
                            return;
                        }
                        hasil.innerHTML = daftar.map(function (d) {
                            return '<button type="button" class="hasil-baris" data-jadwal="' + esc(JSON.stringify(d)) + '">' +
                                '<b>' + esc(d.kode) + ' · ' + esc(d.nama) + '</b>' +
                                '<span>' + esc(d.kelas) + ' · ' + esc(d.dosen) + ' · ' + esc(hariTeks(d.hari)) + ' ' +
                                esc(d.mulai) + '–' + esc(d.selesai) + ' ' + esc(TEKS['in']) + ' ' + esc(d.ruangan) + '</span></button>';
                        }).join('');
                    })
                    .catch(function () { hasil.hidden = true; });
            }, 250);
        });

        hasil.addEventListener('click', function (e) {
            var b = e.target.closest('[data-jadwal]');
            if (!b) { return; }
            pilih = JSON.parse(b.dataset.jadwal);
            isiJadwal.value = pilih.id;
            cari.value = pilih.kode + ' · ' + pilih.nama + ' (' + pilih.kelas + ')';
            hasil.hidden = true;
            if (!isiRuangan.value) { isiRuangan.value = pilih.ruangan_id; }
            tampilkanKartu();
            periksaHari();
        });

        isiAsal.addEventListener('change', periksaHari);
        isiMulai.addEventListener('change', samakanDurasi);
        isiSelesai.addEventListener('change', periksaDurasi);

        el.querySelector('.hapus-baris').addEventListener('click', function () {
            el.remove();
            nomoriUlang();
        });

        // Memulihkan baris yang ditolak server, lengkap dengan kartu jadwalnya.
        el.pulihkan = function (lama) {
            isiAsal.value = lama.tanggal_asal || '';
            isiRuangan.value = lama.ruangan_id || '';
            el.querySelector('.isi-tanggal').value = lama.tanggal || '';
            isiMulai.value = lama.mulai || '';
            isiSelesai.value = lama.selesai || '';
            isiJadwal.value = lama.jadwal_id || '';

            if (lama.jadwal) {
                pilih = lama.jadwal;
                cari.value = pilih.kode + ' · ' + pilih.nama + ' (' + pilih.kelas + ')';
                tampilkanKartu();
                isiMulai.value = lama.mulai || '';
                isiSelesai.value = lama.selesai || '';
                periksaDurasi();
                periksaHari();
            }

            (lama.galat || []).forEach(function (g) {
                var d = document.createElement('div');
                d.className = 'pub-field-galat';
                d.textContent = g;
                el.appendChild(d);
            });
            if ((lama.galat || []).length) { el.classList.add('baris-ganti--galat'); }
        };
    }

    function tambahBaris() {
        if (!cetakan || !daftarBaris) { return; }
        var html = cetakan.innerHTML.replace(/__I__/g, nomorBaris++);
        var bungkus = document.createElement('div');
        bungkus.innerHTML = html;
        var el = bungkus.firstElementChild;
        daftarBaris.appendChild(el);
        pasangBaris(el);
        nomoriUlang();
        return el;
    }

    if (tombolTambah) { tombolTambah.addEventListener('click', tambahBaris); }

    // Baris yang ditolak dipulihkan dengan nomor aslinya, supaya kunci galat cocok.
    if (daftarBaris && BARIS_LAMA.length) {
        BARIS_LAMA.forEach(function (lama) {
            nomorBaris = lama.i;
            tambahBaris().pulihkan(lama);
        });
        nomorBaris = Math.max.apply(null, BARIS_LAMA.map(function (l) { return l.i; })) + 1;
        nomoriUlang();
    }

    // Selalu ada satu baris saat formulir dibuka.
    if (daftarBaris && !daftarBaris.children.length) { tambahBaris(); }

    document.addEventListener('click', function (e) {
        document.querySelectorAll('.hasil-cari').forEach(function (h) {
            if (!h.contains(e.target) && !e.target.classList.contains('cari-mk')) { h.hidden = true; }
        });
    });

    // Tombol jalan keluar pada pop-up reservasi ruangan
    var ruanganReservasi = document.getElementById('ruanganReservasi');
    document.querySelectorAll('[data-pilih-ruangan-rsv]').forEach(function (b) {
        b.addEventListener('click', function () {
            if (ruanganReservasi) { ruanganReservasi.value = b.dataset.pilihRuanganRsv; }
        });
    });

    // ---- Alur: ketentuan dibaca lebih dulu, baru formulir terbuka ----
    var pemicuModal = null;

    function modalTerbuka() {
        return document.querySelector('.modal:not([hidden])');
    }

    function fokusKeJudul(m) {
        var fokus = m.querySelector('[data-ringkasan-galat]') || m.querySelector('[data-judul-modal]');
        if (fokus) { fokus.focus(); }
    }

    function bukaModal(id) {
        var m = document.getElementById(id);
        if (!m) { return; }
        m.hidden = false;
        document.body.style.overflow = 'hidden';
        fokusKeJudul(m);
    }

    // Formulir yang ditolak server terbuka lagi saat halaman dimuat.
    if (modalTerbuka()) {
        document.body.style.overflow = 'hidden';
        fokusKeJudul(modalTerbuka());
    } else if (ringkasan) {
        ringkasan.focus();
    }

    document.querySelectorAll('[data-aturan]').forEach(function (b) {
        b.addEventListener('click', function () {
            pemicuModal = b;
            // Tombol di kartu hasil langsung mengisi ruangannya.
            if (b.dataset.ruang && ruanganReservasi) { ruanganReservasi.value = b.dataset.ruang; }
            bukaModal('aturan' + b.dataset.aturan.charAt(0).toUpperCase() + b.dataset.aturan.slice(1));
        });
    });

    // Tombol Lanjutkan baru hidup setelah centang persetujuan diisi.
    document.querySelectorAll('.setuju-aturan').forEach(function (c) {
        c.addEventListener('change', function () {
            var tombol = document.querySelector('.lanjut-aturan[data-untuk="' + c.dataset.untuk + '"]');
            if (tombol) { tombol.disabled = !c.checked; }
        });
    });

    document.querySelectorAll('.lanjut-aturan').forEach(function (b) {
        b.addEventListener('click', function () {
            var jenis = b.dataset.untuk;
            document.getElementById('aturan' + jenis.charAt(0).toUpperCase() + jenis.slice(1)).hidden = true;

            var formulir = jenis === 'ruangan' ? 'modalRuangan' : 'modalPergantian';
            bukaModal(formulir);
            salinDariPencarian(document.getElementById(formulir));
        });
    });

    function tutupSemua() {
        document.querySelectorAll('.modal').forEach(function (m) { m.hidden = true; });
        document.body.style.overflow = '';
        if (pemicuModal) { pemicuModal.focus(); }
    }

    document.querySelectorAll('[data-tutup]').forEach(function (b) {
        b.addEventListener('click', tutupSemua);
    });

    // Klik di luar hanya menutup pop-up ketentuan. Pop-up formulir tetap terbuka
    // supaya isiannya tidak hilang karena salah klik.
    document.querySelectorAll('[data-modal-ketentuan]').forEach(function (m) {
        m.addEventListener('click', function (e) { if (e.target === m) { tutupSemua(); } });
    });

    document.addEventListener('keydown', function (e) {
        var m = modalTerbuka();
        if (!m) { return; }

        if (e.key === 'Escape') { tutupSemua(); return; }

        // Fokus berputar di dalam pop-up.
        if (e.key === 'Tab') {
            var bisa = Array.prototype.filter.call(
                m.querySelectorAll('a[href],button,input,select,textarea'),
                function (el) { return !el.disabled && el.type !== 'hidden' && el.offsetParent !== null; }
            );
            if (!bisa.length) { return; }
            var awal = bisa[0], akhir = bisa[bisa.length - 1];
            if (e.shiftKey && (document.activeElement === awal || !m.contains(document.activeElement))) {
                e.preventDefault(); akhir.focus();
            } else if (!e.shiftKey && (document.activeElement === akhir || !m.contains(document.activeElement))) {
                e.preventDefault(); awal.focus();
            }
        }
    });

    /* Unit / organisasi hanya ditanyakan bila status pemohon "Lainnya".
       Dosen dan mahasiswa international sudah jelas unitnya, jadi kolomnya
       disembunyikan sekaligus dikosongkan agar tidak ikut terkirim. */
    var peran = document.getElementById('peranReservasi');
    var unit  = document.getElementById('unitReservasi');

    if (peran && unit) {
        var isian = unit.querySelector('input');

        function selaraskanUnit() {
            var tampil = peran.value === 'Lainnya';
            unit.hidden = !tampil;
            if (isian) {
                isian.required = tampil;
                if (!tampil) { isian.value = ''; }
            }
        }

        peran.addEventListener('change', selaraskanUnit);
        selaraskanUnit();
    }
})();
</script>
@endpush

