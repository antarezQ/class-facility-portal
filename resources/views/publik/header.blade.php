<header class="pub-header">
    <a class="pub-logo" href="{{ route('menu') }}" aria-label="{{ __('publik.logo_label') }}">
        <span class="pub-merek">@ikon('building-2', 24)<b>{{ config('jadwal.nama_pendek') }}</b></span>
    </a>

    <nav class="pub-nav" aria-label="{{ __('publik.main_nav') }}">
        @foreach(['menu' => 'home', 'dashboard' => 'building', 'papan.index' => 'board'] as $rute => $kunci)
            <a href="{{ $rute === 'dashboard' ? route('dashboard', 'ruangan') : route($rute) }}"
               @if(request()->routeIs($rute)) aria-current="page" @endif>{{ __('publik.nav.' . $kunci) }}</a>
        @endforeach
    </nav>
</header>
