@extends('layouts.app')

@section('content')
<style>
    .guide-shell {
        max-width: 980px;
    }
    .guide-content {
        line-height: 1.65;
    }
    .guide-content h1 {
        display: none;
    }
    .guide-content h2 {
        font-size: 1.25rem;
        font-weight: 800;
        margin: 2rem 0 .75rem;
        padding-top: 1.25rem;
        border-top: 1px solid var(--app-soft-border);
    }
    .guide-content h2:first-child,
    .guide-content h1 + h2 {
        margin-top: 0;
        padding-top: 0;
        border-top: 0;
    }
    .guide-content h3 {
        font-size: 1.05rem;
        font-weight: 800;
        margin: 1.5rem 0 .65rem;
    }
    .guide-content p,
    .guide-content ul,
    .guide-content ol,
    .guide-content table,
    .guide-content pre {
        margin-bottom: 1rem;
    }
    .guide-content ul,
    .guide-content ol {
        padding-left: 1.25rem;
    }
    .guide-content li + li {
        margin-top: .25rem;
    }
    .guide-content table {
        width: 100%;
        border-collapse: collapse;
        display: block;
        overflow-x: auto;
        border: 1px solid var(--app-soft-border);
        border-radius: .75rem;
    }
    .guide-content thead {
        background: var(--app-muted-bg);
    }
    .guide-content th,
    .guide-content td {
        padding: .8rem .9rem;
        border-bottom: 1px solid var(--app-soft-border);
        vertical-align: top;
    }
    .guide-content tr:last-child td {
        border-bottom: 0;
    }
    .guide-content th {
        color: var(--bs-secondary-color);
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .guide-content img[src^="/images/sidebar/"] {
        width: 1.1rem;
        height: 1.1rem;
        padding: .1rem;
        border-radius: .35rem;
        background: var(--app-sidebar-bg);
        margin-right: .45rem;
        vertical-align: -.22rem;
    }
    .guide-content img[src^="/images/status/"] {
        height: 1.5rem;
        width: auto;
        vertical-align: -.4rem;
    }
    .guide-content code {
        color: var(--bs-body-color);
        background: var(--app-muted-bg);
        border: 1px solid var(--app-soft-border);
        border-radius: .35rem;
        padding: .1rem .35rem;
    }
    .guide-content pre {
        background: var(--app-muted-bg);
        border: 1px solid var(--app-soft-border);
        border-radius: .75rem;
        padding: 1rem;
        overflow-x: auto;
    }
    .guide-content pre code {
        background: transparent;
        border: 0;
        padding: 0;
    }
</style>

<div class="guide-shell">
    <div class="section-header">
        <div>
            <h1 class="h3 page-heading mb-1">My Guide</h1>
            <div class="text-muted">{{ $roleLabel }} guide for using MEC Group Portal.</div>
        </div>
        <div class="badge text-bg-light border text-dark px-3 py-2">
            Updated {{ date('M d, Y', $updatedAt) }}
        </div>
    </div>

    <div class="content-card">
        <div class="content-card-body guide-content">
            {!! $guideHtml !!}
        </div>
    </div>
</div>
@endsection
