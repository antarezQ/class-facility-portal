{{-- Sumber ikon status bagi public/js/publik.js. --}}
<template id="ikonStatus">
    @foreach(\App\Support\StatusRuangan::IKON as $kunci => $ikon)
        <span data-kunci="{{ $kunci }}">@ikon($ikon, 16)</span>
    @endforeach
</template>
