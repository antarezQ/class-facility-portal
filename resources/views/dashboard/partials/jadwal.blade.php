@php
    $adaFilter = request()->hasAny(['q', 'hari', 'prodi', 'ruangan']) && collect(request()->only(['q', 'hari', 'prodi', 'ruangan']))->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
    $jumlahFilter = collect(request()->only(['hari', 'prodi', 'ruangan']))->filter(fn ($v) => $v !== null && $v !== '')->count();
    $hariPublik = app()->getLocale() === 'en'
        ? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
        : \App\Models\Jadwal::HARI;
@endphp

<form method="GET" action="{{ route('dashboard', 'jadwal') }}" class="pub-card pub-filter">
    <input type="hidden" name="periode" value="{{ $periode->kode }}">
    @if($gedung)<input type="hidden" name="gedung" value="{{ $gedung->id }}">@endif

    <div class="pub-filter-cari">
        <div class="pub-field pub-field--lebar">
            <label for="jadwalCari">{{ __('publik.sched.filter') }}</label>
            <input type="text" id="jadwalCari" name="q" value="{{ request('q') }}" placeholder="{{ __('publik.sched.search_ph') }}">
        </div>
        <button type="button" class="pub-btn pub-btn--secondary hanya-hp" data-lipat="filterLanjut" aria-expanded="false" aria-controls="filterLanjut">
            @ikon('sliders-horizontal', 20) {{ __('publik.sched.show_filters') }}{{ $jumlahFilter ? ' (' . $jumlahFilter . ')' : '' }}
        </button>
    </div>

    <div class="pub-filter-lanjut" id="filterLanjut">
        <div class="pub-field">
            <label for="jadwalHari">{{ __('publik.sched.day') }}</label>
            <select id="jadwalHari" name="hari">
                <option value="">{{ __('publik.sched.all_days') }}</option>
                @foreach(\App\Models\Jadwal::HARI as $i => $h)
                    <option value="{{ $i }}" @selected(request('hari') !== null && request('hari') !== '' && (int) request('hari') === $i)>{{ $hariPublik[$i] }}</option>
                @endforeach
            </select>
        </div>
        <div class="pub-field">
            <label for="jadwalProdi">{{ __('publik.sched.program') }}</label>
            <select id="jadwalProdi" name="prodi">
                <option value="">{{ __('publik.sched.all_programs') }}</option>
                @foreach($daftarProdi as $p)
                    <option value="{{ $p }}" @selected(request('prodi') === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div class="pub-field">
            <label for="jadwalRuangan">{{ __('publik.sched.room') }}</label>
            <input type="text" id="jadwalRuangan" name="ruangan" value="{{ request('ruangan') }}" placeholder="{{ __('publik.sched.room_ph') }}">
        </div>
        {{-- Berada di dalam formulir yang sama, jadi memilih jumlah baris
             tidak menghapus kata kunci maupun saringan yang sedang aktif. --}}
        <div class="pub-field">
            <label for="jadwalPer">{{ __('publik.sched.rows_label') }}</label>
            <select id="jadwalPer" name="per" onchange="this.form.submit()">
                @foreach([10, 20, 50] as $nilai)
                    <option value="{{ $nilai }}" @selected((string) request('per', 50) === (string) $nilai)>{{ __('publik.sched.rows', ['n' => $nilai]) }}</option>
                @endforeach
                <option value="semua" @selected(request('per') === 'semua')>{{ __('publik.sched.show_all') }}</option>
            </select>
        </div>
        <div class="pub-filter-aksi">
            @if($adaFilter)
                <a class="pub-btn pub-btn--ghost" href="{{ route('dashboard', 'jadwal') }}?periode={{ $periode->kode }}">{{ __('publik.sched.clear') }}</a>
            @endif
            <button type="submit" class="pub-btn pub-btn--primary">{{ __('publik.sched.apply') }}</button>
        </div>
    </div>
</form>

<div class="pub-baris-info">
    <p class="pub-meta">
        {{ __('publik.sched.showing', ['a' => $jadwals->count(), 'b' => $jadwals->total()]) }}
        @if($jadwals->total() !== $totalTanpaFilter)
            {{ __('publik.sched.filtered', ['n' => $totalTanpaFilter]) }}
        @endif
        @if($periode->punyaTanggal())
            {{ __('publik.sched.valid', ['a' => $periode->tanggal_mulai->translatedFormat('j M Y'), 'b' => $periode->tanggal_selesai->translatedFormat('j M Y')]) }}
        @endif
    </p>
</div>

@if($jadwals->isEmpty())
    <div class="pub-card">
        @include('publik.kosong', [
            'ikon' => 'search-x', 'judul' => __('publik.sched.none_title'), 'isi' => __('publik.sched.none_body'),
            'aksi' => __('publik.sched.clear'), 'tautan' => route('dashboard', 'jadwal') . '?periode=' . $periode->kode,
        ])
    </div>
@else
    {{-- HP: kartu per kelas --}}
    <div class="pub-tumpuk hanya-hp">
        @foreach($jadwals as $j)
            <article class="pub-card pub-kelas">
                <p class="pub-meta pub-angka">{{ $hariPublik[$j->hari] ?? '-' }} · {{ $j->rentang_jam }} · {{ __('publik.sched.hours', ['n' => $j->durasi_jam]) }}</p>
                <h3>{{ $j->mataKuliah->nama ?? '-' }}</h3>
                <p class="pub-kode-baris"><b class="pub-kode">{{ $j->ruangan->kode }}</b> · {{ $j->mataKuliah->kode ?? '-' }}</p>
                <p class="pub-meta">
                    {{ collect([$j->kelas, $j->dosen->kode ?? null, $j->prodi, __('publik.sched.students', ['n' => $j->jml_mhs])])->filter()->join(' · ') }}
                </p>
            </article>
        @endforeach
    </div>

    {{-- iPad dan desktop: tabel --}}
    <div class="pub-table-wrap bukan-hp" tabindex="0">
        <table class="pub-table">
            <thead><tr>
                <th>{{ __('publik.sched.col_day') }}</th>
                <th>{{ __('publik.sched.col_time') }}</th>
                <th>{{ __('publik.sched.col_room') }}</th>
                <th class="kolom-lebar">{{ __('publik.sched.col_code') }}</th>
                <th>{{ __('publik.sched.col_course') }}</th>
                <th>{{ __('publik.sched.col_lecturer') }}</th>
                <th>{{ __('publik.sched.col_class') }}</th>
                <th class="kolom-lebar">{{ __('publik.sched.col_program') }}</th>
                <th class="kolom-lebar pub-kanan">{{ __('publik.sched.col_students') }}</th>
            </tr></thead>
            <tbody>
            @foreach($jadwals as $j)
                <tr>
                    <td>{{ $hariPublik[$j->hari] ?? '-' }}</td>
                    <td class="pub-angka pub-nowrap">{{ $j->rentang_jam }}</td>
                    <td class="pub-kode">{{ $j->ruangan->kode }}</td>
                    <td class="kolom-lebar pub-angka">{{ $j->mataKuliah->kode ?? '-' }}</td>
                    <td class="pub-tebal">{{ $j->mataKuliah->nama ?? '-' }}</td>
                    <td>{{ $j->dosen->kode ?? '-' }}</td>
                    <td class="pub-angka pub-nowrap">{{ $j->kelas }}</td>
                    <td class="kolom-lebar">{{ $j->prodi }}</td>
                    <td class="kolom-lebar pub-kanan pub-angka">{{ $j->jml_mhs }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if($jadwals->hasPages())
    {{ $jadwals->links('publik.paginasi') }}
@endif
