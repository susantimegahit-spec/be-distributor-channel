@if ($paginator->hasPages())
    <div class="custom-pagination-wrapper">
        <div class="custom-pagination-info">
            Menampilkan <strong>{{ $paginator->firstItem() }}</strong> - <strong>{{ $paginator->lastItem() }}</strong> dari <strong>{{ $paginator->total() }}</strong> hasil
        </div>

        <div class="custom-pagination-links">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <span class="page-btn disabled" aria-disabled="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    <span>Sebelumnya</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="page-btn" rel="prev">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    <span>Sebelumnya</span>
                </a>
            @endif

            {{-- Numbered Page Links --}}
            <div class="page-numbers">
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <span class="page-num disabled">{{ $element }}</span>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="page-num active" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="page-num">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="page-btn" rel="next">
                    <span>Berikutnya</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a>
            @else
                <span class="page-btn disabled" aria-disabled="true">
                    <span>Berikutnya</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </span>
            @endif
        </div>
    </div>
@endif
