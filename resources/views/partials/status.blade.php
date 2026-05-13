@php
    $map = ['draft' => 'secondary', 'submitted' => 'primary', 'approved' => 'success', 'rejected' => 'danger', 'open' => 'success', 'closed' => 'dark'];
@endphp
<span class="badge text-bg-{{ $map[$status] ?? 'secondary' }}">{{ ucfirst($status) }}</span>
