<nav class="pub-bawah" aria-label="{{ __('publik.main_nav') }}">
    @foreach([
        ['menu', 'home', 'house'],
        ['dashboard', 'building', 'layout-grid'], ['papan.index', 'board', 'monitor'],
    ] as [$rute, $kunci, $ikon])
        <a href="{{ $rute === 'dashboard' ? route('dashboard', 'ruangan') : route($rute) }}"
           @if(request()->routeIs($rute)) aria-current="page" @endif>
            @ikon($ikon, 24)
            <span>{{ __('publik.nav.' . $kunci) }}</span>
        </a>
    @endforeach
</nav>
