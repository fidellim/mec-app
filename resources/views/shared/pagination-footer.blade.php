@php
    $label = $label ?? 'record';
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $pageStart = max(1, $currentPage - 1);
    $pageEnd = min($lastPage, $currentPage + 1);
    $pageRange = $paginator->getUrlRange($pageStart, $pageEnd);
@endphp

@if($paginator->hasPages())
    <div class="pagination-footer mt-3">
        <div class="pagination-footer-summary">
            Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }} of {{ $paginator->total() }} {{ \Illuminate\Support\Str::plural($label, $paginator->total()) }}
        </div>

        <nav class="pagination-footer-controls" aria-label="{{ ucfirst(\Illuminate\Support\Str::plural($label)) }} pagination">
            <div class="pagination-footer-actions">
                @if($paginator->onFirstPage())
                    <span class="pagination-footer-button is-disabled" aria-disabled="true">First</span>
                    <span class="pagination-footer-button is-disabled" aria-disabled="true">Previous</span>
                @else
                    <a class="pagination-footer-button" href="{{ $paginator->url(1) }}" rel="first">First</a>
                    <a class="pagination-footer-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
                @endif
            </div>

            <div class="pagination-footer-pages" aria-label="Page numbers">
                @foreach($pageRange as $page => $url)
                    @if($page === $currentPage)
                        <span class="pagination-footer-page is-active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="pagination-footer-page" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            </div>

            <div class="pagination-footer-current">Page {{ $currentPage }} of {{ $lastPage }}</div>

            <div class="pagination-footer-actions">
                @if($paginator->hasMorePages())
                    <a class="pagination-footer-button is-primary" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
                    <a class="pagination-footer-button" href="{{ $paginator->url($lastPage) }}" rel="last">Last</a>
                @else
                    <span class="pagination-footer-button is-disabled" aria-disabled="true">Next</span>
                    <span class="pagination-footer-button is-disabled" aria-disabled="true">Last</span>
                @endif
            </div>
        </nav>
    </div>
@endif
