{{-- Badge status. $kunci: available, soon, occupied, closed. $teks opsional. --}}
<span class="pub-badge pub-badge--{{ $kunci }}">
    @ikon(\App\Support\StatusRuangan::IKON[$kunci] ?? 'info', 16)
    <span>{{ $teks ?? __('publik.status.' . $kunci) }}</span>
</span>
