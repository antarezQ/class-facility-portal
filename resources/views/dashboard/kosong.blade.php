@extends('layouts.app')
@section('publik', '1')
@section('judul', __('publik.dash.title'))

@section('isi')
<div class="pub-page">
    <h1 class="pub-h1">{{ __('publik.dash.title') }}</h1>
    <p class="pub-lead">{{ __('publik.dash.empty_lead') }}</p>

    <div class="pub-card">
        @include('publik.kosong', ['ikon' => 'calendar-off', 'judul' => __('publik.dash.empty_title'), 'isi' => __('publik.dash.empty_body')])
    </div>
</div>
@endsection
