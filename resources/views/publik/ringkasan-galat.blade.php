{{-- Ringkasan galat di atas formulir. Fokus dipindah ke sini oleh skrip halaman. --}}
@if($errors->any())
    <div class="pub-msg pub-msg--galat" role="alert" tabindex="-1" data-ringkasan-galat>
        @ikon('circle-alert', 20)
        <div class="pub-msg-isi">
            <b>{{ __('publik.error_summary') }}</b>
            <ul>
                @foreach($errors->all() as $g)
                    <li>@pesan($g)</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
