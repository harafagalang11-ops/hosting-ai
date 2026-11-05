@if ($paginator->hasPages())
<nav class="hosting-pagination" aria-label="Navigasi halaman">
    <div class="hosting-pagination-info">
        Menampilkan
        <strong>{{ $paginator->firstItem() ?? 0 }}</strong>–<strong>{{ $paginator->lastItem() ?? 0 }}</strong>
        dari <strong>{{ $paginator->total() }}</strong> riwayat
    </div>

    <ul class="hosting-pagination-list">
        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <li class="hosting-page-item disabled">
                <span class="hosting-page-link" aria-disabled="true">&lsaquo;</span>
            </li>
        @else
            <li class="hosting-page-item">
                <a class="hosting-page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">&lsaquo;</a>
            </li>
        @endif

        {{-- Page numbers --}}
        @foreach ($elements as $element)
            @if (is_string($element))
                <li class="hosting-page-item disabled">
                    <span class="hosting-page-link">{{ $element }}</span>
                </li>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <li class="hosting-page-item active" aria-current="page">
                            <span class="hosting-page-link">{{ $page }}</span>
                        </li>
                    @else
                        <li class="hosting-page-item">
                            <a class="hosting-page-link" href="{{ $url }}">{{ $page }}</a>
                        </li>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <li class="hosting-page-item">
                <a class="hosting-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">&rsaquo;</a>
            </li>
        @else
            <li class="hosting-page-item disabled">
                <span class="hosting-page-link" aria-disabled="true">&rsaquo;</span>
            </li>
        @endif
    </ul>
</nav>
@endif
