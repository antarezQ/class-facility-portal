{{-- Pesan setelah berhasil mengirim. Nomor pengajuan bisa disalin. --}}
@if(session('pesan'))
    @php
        $teksSukses = \App\Support\TerjemahPesan::teks(session('pesan'));
        $nomorSukses = preg_match('/\b[A-Z]{2,4}-\d[\w-]*/', $teksSukses, $cocok) ? $cocok[0] : null;
    @endphp
    <div class="pub-page pub-page--pesan">
        <div class="pub-msg pub-msg--sukses" role="status">
            @ikon('circle-check', 20)
            <div class="pub-msg-isi">
                <div>{{ $teksSukses }}</div>
                @if($nomorSukses)
                    <button type="button" class="pub-btn pub-btn--secondary pub-btn--kecil" data-salin="{{ $nomorSukses }}">
                        @ikon('copy', 16) <span>{{ __('publik.copy') }} {{ $nomorSukses }}</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
@endif
