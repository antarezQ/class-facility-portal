{{-- Tautan biasa supaya query string halaman tetap terbawa. --}}
<div class="pub-lang" role="group" aria-label="{{ __('publik.language') }}">
    @foreach(['en' => 'English', 'id' => 'Bahasa Indonesia'] as $kode => $nama)
        <a href="{{ request()->fullUrlWithQuery(['lang' => $kode]) }}" aria-label="{{ $nama }}" hreflang="{{ $kode }}" lang="{{ $kode }}"
           @if(app()->getLocale() === $kode) aria-current="true" @endif>{{ strtoupper($kode) }}</a>
    @endforeach
</div>
