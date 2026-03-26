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
        @if(!empty($item['email']))<a href="{{ route('support.email-client', ['contact_id' => $item['contactid']]) }}" class="serve-client-cta serve-client-cta-outline" title="Send email from CRM"><i class="bi bi-envelope"></i> Email</a>@endif
        @if(!empty($item['phone']))<a href="tel:{{ tel_href($item['phone']) }}" class="serve-client-cta serve-client-cta-outline" title="Call"><i class="bi bi-telephone"></i> Call</a>@endif
    </div>
</div>
@endforeach
@if(empty($items))
<div class="serve-client-empty-detail py-4">
    <p class="mb-2 text-muted small"><i class="bi bi-person me-1"></i> No CRM contacts found{{ !empty($searchTerm ?? '') ? ' for "' . e($searchTerm ?? '') . '"' : '' }}.</p>
    <p class="mb-0 small text-muted">Try a different name, phone, or email. You can still create a ticket from an ERP client above.</p>
</div>
@endif
