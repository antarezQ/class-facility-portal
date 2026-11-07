{{-- Paginasi halaman publik. resources/views/pagination.blade.php tetap untuk petugas. --}}
@if($paginator->hasPages())
    <nav class="pub-pagination" aria-label="{{ __('publik.page.of', ['a' => $paginator->currentPage(), 'b' => $paginator->lastPage()]) }}">
        @if($paginator->onFirstPage())
            <span class="pub-btn pub-btn--disabled" aria-disabled="true">{{ __('publik.page.prev') }}</span>
        @else
            <a class="pub-btn pub-btn--secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('publik.page.prev') }}</a>
        @endif

        <span class="pub-pagination-info">{{ __('publik.page.of', ['a' => $paginator->currentPage(), 'b' => $paginator->lastPage()]) }}</span>

        @if($paginator->hasMorePages())
            <a class="pub-btn pub-btn--secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('publik.page.next') }}</a>
        @else
            <span class="pub-btn pub-btn--disabled" aria-disabled="true">{{ __('publik.page.next') }}</span>
        @endif
    </nav>
@endif
