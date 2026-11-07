@extends('layouts.app')
@section('publik', '1')
@section('judul', __('publik.menu.title'))

@php
    // Kartu dari controller memakai nama ikon lama; dipetakan ke Lucide di sini.
    $ikonKartu = ['search' => 'search', 'box' => 'package', 'grid' => 'layout-grid', 'db' => 'database'];

    // Kartu Papan ditambahkan di tampilan.
    $semuaKartu = $kartu->map(fn ($k) => [
        'judul' => \App\Support\TerjemahPesan::teks($k['judul']),
        'teks' => \App\Support\TerjemahPesan::teks($k['teks']),
        'tautan' => $k['tautan'],
        'ikon' => $ikonKartu[$k['ikon']] ?? 'layout-grid',
        'siap' => $k['siap'],
    ])->push([
        'judul' => __('publik.menu.board_title'),
        'teks' => __('publik.menu.board_body'),
        'tautan' => route('papan.index'),
        'ikon' => 'monitor',
        'siap' => true,
    ]);
@endphp

@section('isi')
<div class="pub-page">
    <h1 class="pub-h1">{{ __('publik.menu.welcome') }}</h1>
    <p class="pub-lead">{{ __('publik.menu.sub') }}</p>

    @unless($periode)
        @include('publik.pesan', ['jenis' => 'peringatan', 'judul' => __('publik.menu.no_data_title'), 'isi' => __('publik.menu.no_data_body')])
    @endunless

    <div class="pub-menu-grid">
        @foreach($semuaKartu as $k)
            <a class="pub-menu {{ $k['siap'] ? '' : 'pub-menu--mati' }}" href="{{ $k['tautan'] }}">
                <span class="pub-menu-ikon">@ikon($k['ikon'], 32)</span>
                <span class="pub-menu-teks">
                    <b>{{ $k['judul'] }}</b>
                    <span>{{ $k['teks'] }}</span>
                    @unless($k['siap'])
                        <span class="pub-badge pub-badge--closed">{{ __('publik.menu.soon') }}</span>
                    @endunless
                </span>
                <span class="pub-menu-panah">@ikon('chevron-right', 20)</span>
            </a>
        @endforeach
    </div>

    <p class="pub-catatan">{{ __('publik.zone') }}</p>
</div>
@endsection
