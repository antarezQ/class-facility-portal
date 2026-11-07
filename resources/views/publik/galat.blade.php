@error($nama)
    <div class="pub-field-galat" id="galat-{{ $id ?? $nama }}">@ikon('circle-alert', 16) <span>@pesan($message)</span></div>
@enderror
