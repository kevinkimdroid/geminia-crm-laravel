@foreach($items as $item)
@php
    $name = $item['name'] ?? 'Contact #' . ($item['contactid'] ?? '');
    $meta = implode(' · ', array_filter([$item['phone'] ?? '', $item['email'] ?? '']));
@endphp
<div class="serve-client-item">
    <div class="serve-client-item-name">{{ $name }}</div>
    @if($meta)<div class="serve-client-item-meta">{{ $meta }}</div>@endif
    <div class="serve-client-actions">
        <a href="{{ url('/contacts') }}/{{ $item['contactid'] }}" class="serve-client-cta serve-client-cta-outline" title="View contact"><i class="bi bi-eye"></i> View Details</a>
        <a href="{{ route('tickets.create') }}?contact_id={{ $item['contactid'] }}&from=serve-client" class="serve-client-cta serve-client-cta-success" title="Create ticket"><i class="bi bi-ticket-perforated"></i> Create Ticket</a>
        @if(!empty($item['email']))<a href="mailto:{{ e($item['email']) }}" class="serve-client-cta serve-client-cta-outline" title="Email"><i class="bi bi-envelope"></i> Email</a>@endif
        @if(!empty($item['phone']))<a href="tel:{{ tel_href($item['phone']) }}" class="serve-client-cta serve-client-cta-outline" title="Call"><i class="bi bi-telephone"></i> Call</a>@endif
    </div>
</div>
@endforeach
@if(empty($items))
<div class="text-muted small py-3">No CRM contacts found.</div>
@endif
