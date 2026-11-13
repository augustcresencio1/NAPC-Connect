@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3" aria-label="{{ __('Pagination navigation') }}">
        @if ($paginator->onFirstPage())
            <span class="btn-ghost btn-sm cursor-not-allowed opacity-40" aria-disabled="true">{{ __('Previous') }}</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn-primary btn-sm">{{ __('Previous') }}</a>
        @endif

        <p class="muted font-semibold">{{ __('Page :page of :total', ['page' => $paginator->currentPage(), 'total' => $paginator->lastPage()]) }}</p>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn-primary btn-sm">{{ __('Next') }}</a>
        @else
            <span class="btn-ghost btn-sm cursor-not-allowed opacity-40" aria-disabled="true">{{ __('Next') }}</span>
        @endif
    </nav>
@endif
