@php
    $v = fn (string $berkas) => asset($berkas) . '?v=' . @filemtime(public_path($berkas));
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('judul', 'Dashboard') — {{ config('jadwal.nama_pendek') }}</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="stylesheet" href="{{ $v('css/publik.css') }}">
@stack('gaya')
</head>
<body class="pub {{ ($tanpaHeader ?? false) ? 'pub-gelap' : '' }}">
<a class="pub-skip" href="#isi">{{ __('publik.skip') }}</a>
@include('publik.ikon-status')

@unless($tanpaHeader ?? false)
    @include('publik.header')
@endunless

<main class="main pub-main" id="isi">
    @unless($tanpaHeader ?? false)
        @include('publik.sukses')
    @endunless
    @yield('isi')
</main>

@include('publik.nav-bawah')

<script>
window.PUB_TEKS = {!! json_encode([
    'available' => __('publik.status.available'), 'soon' => __('publik.status.soon'),
    'occupied' => __('publik.status.occupied'), 'closed' => __('publik.status.closed'),
    'free_until' => __('publik.status.free_until'), 'free_rest' => __('publik.status.free_rest'),
    'busy_until' => __('publik.status.busy_until'), 'starts_in' => __('publik.status.starts_in'),
    'copied' => __('publik.copied'),
], JSON_UNESCAPED_UNICODE) !!};
</script>
<script src="{{ $v('js/publik.js') }}"></script>
@stack('skrip')
</body>
</html>
