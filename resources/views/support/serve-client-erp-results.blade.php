@foreach($items as $idx => $item)
@php
    $name = $item['name'] ?? $item['client_name'] ?? $item['life_assur'] ?? $item['life_assured'] ?? trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? '')) ?: 'Client';
    $policy = $item['policy_no'] ?? $item['policy_number'] ?? '';
    $phone = $item['mobile'] ?? $item['phone'] ?? '';
    $email = personal_email_only($item['email'] ?? $item['email_adr'] ?? null) ?? '';
    $product = $item['product'] ?? '';
    $status = $item['status'] ?? '';
    $paidAmt = $item['bal'] ?? $item['paid_mat_amt'] ?? null;
    $maturity = $item['maturity'] ?? $item['maturity_date'] ?? '';
    $effectiveDate = $item['effective_date'] ?? '';
    $intermediary = $item['intermediary'] ?? '';
    $checkoff = $item['checkoff'] ?? '';
    $kraPin = $item['kra_pin'] ?? '';
    $idNo = $item['id_no'] ?? '';
    $prpDob = $item['prp_dob'] ?? '';
    $schemeName = $item['scheme_name'] ?? '';
    $statusClass = $status === 'A' ? 'status-active' : ($status === 'FL' ? 'status-lapsed' : '');
    $storeId = 'erp_' . $idx;
@endphp
<div class="serve-client-item">
    <div class="serve-client-item-name">{{ $name }}</div>
    <div class="serve-client-item-meta">{{ implode(' · ', array_filter([$policy ? 'Policy: ' . $policy : '', $phone, $email])) }}</div>
    @if($product || $status || ($paidAmt !== null && $paidAmt !== '') || $maturity || $effectiveDate || $intermediary || $kraPin || $idNo || $prpDob || $schemeName || $checkoff)
    <div class="serve-client-item-details">
        @if($product)<div class="serve-client-detail-row"><span class="serve-client-detail-label">Product</span><span class="serve-client-detail-value">{{ $product }}</span></div>@endif
        @if($schemeName)<div class="serve-client-detail-row"><span class="serve-client-detail-label">Scheme</span><span class="serve-client-detail-value">{{ $schemeName }}</span></div>@endif
        @if($status)<div class="serve-client-detail-row"><span class="serve-client-detail-label">Status</span><span class="serve-client-detail-value {{ $statusClass }}">{{ $status }}</span></div>@endif
        @if($paidAmt !== null && $paidAmt !== '')<div class="serve-client-detail-row"><span class="serve-client-detail-label">Total Paid Amount</span><span class="serve-client-detail-value amount">KES {{ number_format((float) preg_replace('/[^0-9.-]/', '', (string) $paidAmt), 0) }}</span></div>@endif
        @if($effectiveDate)<div class="serve-client-detail-row"><span class="serve-client-detail-label">Effective</span><span class="serve-client-detail-value">{{ $effectiveDate }}</span></div>@endif
        @if($maturity)<div class="serve-client-detail-row"><span class="serve-client-detail-label">Maturity</span><span class="serve-client-detail-value">{{ $maturity }}</span></div>@endif
        @if($prpDob)<div class="serve-client-detail-row"><span class="serve-client-detail-label">Date of Birth</span><span class="serve-client-detail-value">{{ $prpDob }}</span></div>@endif
        @if($kraPin)<div class="serve-client-detail-row"><span class="serve-client-detail-label">KRA PIN</span><span class="serve-client-detail-value font-monospace">{{ $kraPin }}</span></div>@endif
        @if($idNo)<div class="serve-client-detail-row"><span class="serve-client-detail-label">ID Number</span><span class="serve-client-detail-value font-monospace">{{ $idNo }}</span></div>@endif
        @if($intermediary)<div class="serve-client-detail-row"><span class="serve-client-detail-label">Agent</span><span class="serve-client-detail-value">{{ $intermediary }}</span></div>@endif
        @if($checkoff)<div class="serve-client-detail-row"><span class="serve-client-detail-label">Checkoff</span><span class="serve-client-detail-value">{{ $checkoff }}</span></div>@endif
    </div>
    @endif
    <div class="serve-client-actions">
        @if($policy)
        <form method="GET" action="{{ url('/support/clients/show') }}" class="d-inline">
            <input type="hidden" name="policy" value="{{ $policy }}">
            <input type="hidden" name="from" value="serve-client">
            <button type="submit" class="serve-client-cta serve-client-cta-outline" title="View full details"><i class="bi bi-eye"></i> View Details</button>
        </form>
        @endif
        <button type="button" class="serve-client-cta serve-client-cta-success serve-client-create-ticket" data-erp-store="{{ $storeId }}" title="Create support ticket"><i class="bi bi-ticket-perforated"></i> Create Ticket</button>
        @if($email)<a href="mailto:{{ e($email) }}" class="serve-client-cta serve-client-cta-outline" title="Send email"><i class="bi bi-envelope"></i> Email</a>@endif
        @if($phone)<a href="tel:{{ tel_href($phone) }}" class="serve-client-cta serve-client-cta-outline" title="Call"><i class="bi bi-telephone"></i> Call</a>@endif
    </div>
</div>
@endforeach
@if(empty($items))
<div class="serve-client-empty-detail py-4">
    <p class="mb-2 text-muted small"><i class="bi bi-database me-1"></i> No ERP clients found{{ !empty($searchTerm ?? '') ? ' for "' . e($searchTerm ?? '') . '"' : '' }}.</p>
    <p class="mb-0 small text-muted">Try searching by: <span class="text-dark">exact policy number</span> (e.g. GEMPPP0334), <span class="text-dark">client name</span>, or <span class="text-dark">phone number</span>. Ensure the ERP API is running (erp-clients-api).</p>
</div>
@endif
