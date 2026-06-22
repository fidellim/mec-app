@php
    $map = ['draft' => 'secondary', 'submitted' => 'primary', 'approved' => 'success', 'rejected' => 'danger', 'withdrawn' => 'warning', 'recalled' => 'warning', 'voided' => 'warning', 'not_submitted' => 'warning', 'open' => 'success', 'closed' => 'dark'];
@endphp
<span class="badge text-bg-{{ $map[$status] ?? 'secondary' }} px-3 py-2 text-capitalize">{{ str_replace('_', ' ', $status) }}</span>
