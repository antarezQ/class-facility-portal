<ul class="pub-legend">
    @foreach([['kuliah', 'book-open'], ['rsv', 'calendar-check'], ['ganti', 'repeat'], ['tutup', 'lock']] as [$jenis, $ikon])
        <li>@ikon($ikon, 16) {{ __('publik.event.' . $jenis) }}</li>
    @endforeach
    @isset($catatan)
        <li>{{ $catatan }}</li>
    @endisset
</ul>
