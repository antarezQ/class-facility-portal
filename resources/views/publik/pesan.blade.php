{{-- Kotak pesan. $jenis: info, sukses, peringatan, galat. $judul dan $isi opsional. --}}
@php
    $ikonPesan = ['info' => 'info', 'sukses' => 'circle-check', 'peringatan' => 'triangle-alert', 'galat' => 'circle-alert'][$jenis];
@endphp
<div class="pub-msg pub-msg--{{ $jenis }}" role="{{ $jenis === 'galat' ? 'alert' : 'status' }}" @isset($idPesan) id="{{ $idPesan }}" @endisset @if($sembunyi ?? false) hidden @endif>
    @ikon($ikonPesan, 20)
    <div class="pub-msg-isi">
        @isset($judul)<b>{{ $judul }}</b>@endisset
        @isset($isi)<div>{{ $isi }}</div>@endisset
    </div>
</div>
