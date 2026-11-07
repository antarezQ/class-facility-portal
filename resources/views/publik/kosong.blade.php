{{-- Kondisi kosong. $ikon, $judul, $isi; $aksi dan $tautan opsional. --}}
<div class="pub-empty">
    @ikon($ikon, 48)
    <b>{{ $judul }}</b>
    <p>{{ $isi }}</p>
    @isset($aksi)
        <a class="pub-btn pub-btn--secondary" href="{{ $tautan }}">{{ $aksi }}</a>
    @endisset
</div>
