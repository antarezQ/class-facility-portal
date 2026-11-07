@extends('layouts.app')
@section('publik', '1')
@section('judul', __('publik.dash.title'))

@php
    $semuaTab = [
        'ruangan'   => [__('publik.dash.tab_rooms'), __('publik.dash.tab_rooms')],
        'jadwal'    => [__('publik.dash.tab_schedule'), __('publik.dash.tab_schedule_short')],
        'timeline'  => [__('publik.dash.tab_timeline'), __('publik.dash.tab_timeline_short')],
    ];
    $tabs = collect($semuaTab)->only($tabBoleh)->all();
    $qs = fn (array $ganti = []) => http_build_query(array_merge(
        ['periode' => $periode->kode] + ($gedung ? ['gedung' => $gedung->id] : []),
        $ganti
    ));
@endphp

@section('isi')
<div class="pub-page">
    <h1 class="pub-h1">{{ __('publik.dash.title') }}</h1>
    <p class="pub-lead">
        {{ __('publik.dash.lead', ['building' => $gedung?->nama ?? $gedungs->pluck('kode_pendek')->filter()->join(' & ') ?: __('publik.dash.all_buildings_lead')]) }}
    </p>

    <nav class="pub-tabs {{ count($tabs) === 3 ? 'pub-tabs--tiga' : '' }}" aria-label="{{ __('publik.dash.title') }}">
        @foreach($tabs as $key => [$label, $pendek])
            <a href="{{ route('dashboard', $key) }}?{{ $qs() }}" @if($tab === $key) aria-current="page" @endif>
                <span class="bukan-hp-inline">{{ $label }}</span><span class="hanya-hp-inline">{{ $pendek }}</span>
            </a>
        @endforeach
    </nav>

    @if($gedungs->count() > 1)
        <div class="pub-chips pub-chips--gulir">
            <a class="pub-chip {{ $gedung ? '' : 'on' }}" href="{{ route('dashboard', $tab) }}?{{ $qs(['gedung' => null]) }}">
                @unless($gedung)@ikon('check', 16)@endunless <span>{{ __('publik.dash.all_buildings') }}</span>
            </a>
            @foreach($gedungs as $g)
                <a class="pub-chip {{ $gedung?->id === $g->id ? 'on' : '' }}"
                   href="{{ route('dashboard', $tab) }}?periode={{ $periode->kode }}&gedung={{ $g->id }}">
                    @if($gedung?->id === $g->id)@ikon('check', 16)@endif <span>{{ $g->kode_pendek ?? $g->nama }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @include('dashboard.partials.' . $tab)
</div>
@endsection
