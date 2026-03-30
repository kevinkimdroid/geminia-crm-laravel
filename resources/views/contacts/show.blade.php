@extends('layouts.app')

@section('title', $contact->full_name . ' — Contact')

@section('content')
<div class="contact-detail-header">
    <nav class="mb-2">
        <a href="{{ route('support.customers') }}" class="text-muted small">Clients</a>
        <span class="text-muted mx-1">/</span>
        <a href="{{ route('contacts.index') }}" class="text-muted small">All</a>
        <span class="text-muted mx-1">/</span>
        <span class="text-dark">{{ Str::limit($contact->full_name, 25) }}</span>
    </nav>
    <div class="d-flex flex-wrap align-items-start gap-4">
        <div class="contact-avatar-lg">
            {{ strtoupper(substr($contact->firstname ?? '?', 0, 1)) }}{{ strtoupper(substr($contact->lastname ?? '', 0, 1)) }}
        </div>
        <div class="flex-grow-1">
            <h1 class="page-title mb-2">{{ $contact->full_name }}</h1>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <a href="{{ route('contacts.edit', $contact->contactid) }}" class="btn btn-sm btn-primary-custom">Edit</a>
                <a href="mailto:{{ $contact->email }}" class="btn btn-sm btn-outline-secondary">Send Email</a>
                <div class="btn-group">
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">More</button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#followupModal"><i class="bi bi-calendar-check me-2"></i>Log Follow-up</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('contacts.destroy', $contact->contactid) }}" method="POST" onsubmit="return confirm('Delete this contact?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="dropdown-item text-danger">Delete</button>
                            </form>
                        </li>
                    </ul>
                </div>
                @if(($prevContactId ?? null) || ($nextContactId ?? null))
                <div class="btn-group ms-1">
                    @if($prevContactId ?? null)
                    <a href="{{ route('contacts.show', $prevContactId) }}{{ in_array($activeTab ?? '', ['tickets','policies','calls','sms','emails','campaigns']) ? '?tab=' . $activeTab : '' }}" class="btn btn-sm btn-outline-secondary" title="Previous client">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    @endif
                    @if($nextContactId ?? null)
                    <a href="{{ route('contacts.show', $nextContactId) }}{{ in_array($activeTab ?? '', ['tickets','policies','calls','sms','emails','campaigns']) ? '?tab=' . $activeTab : '' }}" class="btn btn-sm btn-outline-secondary" title="Next client">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

{{-- Module tabs --}}
<ul class="nav contact-module-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? 'summary') === 'summary' ? 'active' : '' }}" href="{{ route('contacts.show', $contact->contactid) }}?tab=summary">Summary</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'details' ? 'active' : '' }}" href="{{ route('contacts.show', $contact->contactid) }}?tab=details">Details</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'updates' ? 'active' : '' }}" href="{{ route('contacts.show', $contact->contactid) }}?tab=updates">Updates</a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('activities.index', ['contact_id' => $contact->contactid]) }}" title="Activities"><i class="bi bi-calendar3"></i></a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'tickets' ? 'active' : '' }}" href="{{ route('contacts.show', $contact->contactid) }}?tab=tickets" title="Tickets">
            <i class="bi bi-ticket-perforated"></i>
            @if(($ticketsCount ?? 0) > 0)
            <span class="badge bg-primary ms-1">{{ $ticketsCount }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#deals" title="Deals"><i class="bi bi-currency-dollar"></i></a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'emails' ? 'active' : '' }}" href="{{ route('contacts.show', $contact->contactid) }}?tab=emails" title="Emails from life@geminialife.co.ke">
            <i class="bi bi-envelope"></i>
            @if(($emailsCount ?? 0) > 0)
            <span class="badge bg-primary ms-1">{{ $emailsCount }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="#" title="Documents"><i class="bi bi-file-earmark"></i></a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'policies' ? 'active' : '' }}" href="{{ route('contacts.show', $contact->contactid) }}?tab=policies" title="Policies"><i class="bi bi-box"></i></a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'calls' ? 'active' : '' }}" href="{{ route('contacts.show', $contact->contactid) }}?tab=calls" title="Calls (PBX)"><i class="bi bi-telephone"></i></a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'campaigns' ? 'active' : '' }}" href="{{ route('contacts.show', $contact->contactid) }}?tab=campaigns" title="Campaigns">
            <i class="bi bi-megaphone"></i>
            @if(($campaigns ?? collect())->isNotEmpty())
            <span class="badge bg-primary ms-1">{{ ($campaigns ?? collect())->count() }}</span>
            @endif
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ ($activeTab ?? '') === 'sms' ? 'active' : '' }}" href="{{ route('contacts.show', $contact->contactid) }}?tab=sms" title="SMS sent"><i class="bi bi-chat-dots"></i></a>
    </li>
</ul>

<div class="row g-4">
    {{-- Left: Key Fields (Summary: 3 fields only; Details: full list) --}}
    <div class="col-lg-8">
        <div class="card contact-detail-card mb-4">
            <div class="card-body p-4">
                @if(($activeTab ?? 'summary') === 'summary')
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Key Fields</h6>
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted small">Last Name</dt>
                    <dd class="col-sm-8 mb-2">{{ $contact->lastname ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted small">First Name</dt>
                    <dd class="col-sm-8 mb-2">{{ $contact->firstname ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted small">Primary Email</dt>
                    <dd class="col-sm-8 mb-0">{{ $contact->email ?: '—' }}</dd>
                </dl>
                @elseif(($activeTab ?? '') === 'details')
                @php
                    $detailFields = [
                        ['Last Name', $contact->lastname ?? null],
                        ['First Name', $contact->firstname ?? null],
                        ['Id Number', $contact->idNumber ?? null],
                        ['Policy Number', $contact->policy_number ?? null],
                        ['Contact Id', $contact->contact_no ?? null],
                        ['PIN', $contact->pin ?? null],
                        ['Office Phone', $contact->phone ?? null],
                        ['Mobile Phone', $contact->mobile ?? null],
                        ['Primary Email', $contact->email ?? null],
                        ['Secondary Email', $contact->secondaryemail ?? $contact->otheremail ?? null],
                        ['Title', $contact->title ?? null],
                        ['Department', $contact->department ?? null],
                        ['Fax', $contact->fax ?? null],
                        ['Date of Birth', null],
                        ['Organization Name', null],
                        ['Lead Source', null],
                        ['Reports To', $contact->reportsto ?? null],
                        ['Assistant', null],
                        ['Assistant Phone', null],
                        ['Do Not Call', isset($contact->donotcall) ? ($contact->donotcall ? 'Yes' : 'No') : null],
                        ['Email Opt Out', isset($contact->emailoptout) ? ($contact->emailoptout ? 'Yes' : 'No') : null],
                        ['Reference', isset($contact->reference) ? ($contact->reference ? 'Yes' : 'No') : null],
                        ['Assigned To', $contact->assigned_to_name ?? null],
                        ['Notify Owner', isset($contact->notify_owner) ? ($contact->notify_owner ? 'Yes' : 'No') : null],
                        ['Is Converted From Lead', isset($contact->isconvertedfromlead) ? ($contact->isconvertedfromlead ? 'Yes' : 'No') : null],
                        ['Created Time', $contact->createdtime ? date('d-m-Y H:i', strtotime($contact->createdtime)) : null],
                        ['Modified Time', $contact->modifiedtime ? date('d-m-Y H:i', strtotime($contact->modifiedtime)) : null],
                        ['Source', $contact->source ?? null],
                    ];
                    $filledDetails = array_filter($detailFields, fn($f) => $f[1] !== null && $f[1] !== '');
                    $left = array_slice($filledDetails, 0, (int) ceil(count($filledDetails) / 2));
                    $right = array_slice($filledDetails, (int) ceil(count($filledDetails) / 2));
                @endphp
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Basic Information</h6>
                @if(count($filledDetails) > 0)
                <div class="row">
                    <div class="col-md-6">
                        <dl class="row mb-0">
                            @foreach($left as $f)
                            <dt class="col-sm-5 text-muted small">{{ $f[0] }}</dt>
                            <dd class="col-sm-7 mb-2">{{ $f[1] }}</dd>
                            @endforeach
                        </dl>
                    </div>
                    <div class="col-md-6">
                        <dl class="row mb-0">
                            @foreach($right as $f)
                            <dt class="col-sm-5 text-muted small">{{ $f[0] }}</dt>
                            <dd class="col-sm-7 mb-2">{{ $f[1] }}</dd>
                            @endforeach
                        </dl>
                    </div>
                </div>
                @else
                <p class="text-muted mb-0">No details available.</p>
                @endif
                @elseif(($activeTab ?? '') === 'updates')
                <p class="text-muted mb-0">Activity and update history coming soon.</p>
                @else
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Key Fields</h6>
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted small">Last Name</dt>
                    <dd class="col-sm-8 mb-2">{{ $contact->lastname ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted small">First Name</dt>
                    <dd class="col-sm-8 mb-2">{{ $contact->firstname ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted small">Primary Email</dt>
                    <dd class="col-sm-8 mb-0">{{ $contact->email ?: '—' }}</dd>
                </dl>
                @endif
            </div>
        </div>

        @if($deals->isNotEmpty())
        <div class="card contact-detail-card mb-4" id="deals">
            <div class="card-body p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Deals</h6>
                <div class="list-group list-group-flush">
                    @foreach($deals as $deal)
                    <a href="{{ route('deals.show', $deal->potentialid) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center px-0">
                        <span>{{ $deal->potentialname ?? 'Untitled' }}</span>
                        <span class="badge bg-primary">{{ $deal->sales_stage ?? '—' }}</span>
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Tickets tab content (full width) --}}
    @if(($activeTab ?? '') === 'tickets')
    <div class="col-12 mb-4">
        <div class="card contact-detail-card">
            <div class="card-body p-0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 border-bottom bg-light">
                    <h6 class="text-uppercase small fw-bold text-muted mb-0">Tickets</h6>
                    <a href="{{ route('tickets.create', ['contact_id' => $contact->contactid]) }}" class="btn btn-success btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Add Ticket
                    </a>
                </div>
                <form action="{{ route('contacts.show', $contact->contactid) }}" method="GET" class="p-3 border-bottom">
                    <input type="hidden" name="tab" value="tickets">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold mb-1">Search</label>
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search tickets..." value="{{ $ticketSearch ?? '' }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold mb-1">Status</label>
                            <select name="list" class="form-select form-select-sm">
                                <option value="">All</option>
                                <option value="Open" {{ ($ticketStatus ?? '') === 'Open' ? 'selected' : '' }}>Open</option>
                                <option value="In Progress" {{ ($ticketStatus ?? '') === 'In Progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="Wait For Response" {{ ($ticketStatus ?? '') === 'Wait For Response' ? 'selected' : '' }}>Wait For Response</option>
                                <option value="Closed" {{ ($ticketStatus ?? '') === 'Closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-search me-1"></i> Search</button>
                        </div>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small text-uppercase fw-bold">Ticket</th>
                                <th class="small text-uppercase fw-bold">Title</th>
                                <th class="small text-uppercase fw-bold">Policy</th>
                                <th class="small text-uppercase fw-bold">Status</th>
                                <th class="small text-uppercase fw-bold">Priority</th>
                                <th class="small text-uppercase fw-bold">Assigned To</th>
                                <th class="small text-uppercase fw-bold">Assigned By</th>
                                <th class="small text-uppercase fw-bold">Created</th>
                                <th class="small text-uppercase fw-bold text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($tickets ?? [] as $ticket)
                            @php
                                // POLICY = policy_number only (from contact cf or "Related policy"). Never contact_id.
                                $policyNum = pick_policy_excluding_pin($ticket->cf_860 ?? null, $ticket->cf_856 ?? null, $ticket->cf_872 ?? null);
                                if (!$policyNum && !empty($ticket->description ?? '') && preg_match('/Related policy:\s*([^\n]+)/i', $ticket->description, $m)) {
                                    $p = trim($m[1]);
                                    $cid = (string)($ticket->contact_id ?? '');
                                    if ($p !== '' && $p !== $cid && !looks_like_kra_pin($p) && !looks_like_client_id($p)) {
                                        $policyNum = $p;
                                    }
                                }
                                $ownerName = trim(($ticket->owner_first ?? '') . ' ' . ($ticket->owner_last ?? '')) ?: ($ticket->owner_username ?? '—');
                                $assignedByName = trim(($ticket->assigned_by_first ?? '') . ' ' . ($ticket->assigned_by_last ?? '')) ?: ($ticket->assigned_by_username ?? '—');
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('tickets.show', $ticket->ticketid) }}" class="fw-semibold text-primary text-decoration-none">
                                        {{ $ticket->ticket_no ?? 'TT' . $ticket->ticketid }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('tickets.show', $ticket->ticketid) }}" class="text-decoration-none">{{ $ticket->title ?? 'Untitled' }}</a>
                                </td>
                                <td><span class="text-muted">{{ $policyNum ?? '—' }}</span></td>
                                <td>
                                    <span class="badge tickets-badge-{{ Str::slug($ticket->status ?? '') }}">
                                        {{ $ticket->status ?? '—' }}
                                    </span>
                                </td>
                                <td><span class="text-muted">{{ $ticket->priority ?? 'Normal' }}</span></td>
                                <td><span class="text-muted small">{{ $ownerName }}</span></td>
                                <td><span class="text-muted small">{{ $assignedByName }}</span></td>
                                <td><span class="text-muted small">{{ $ticket->createdtime ? date('d M Y', strtotime($ticket->createdtime)) : '—' }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('tickets.show', $ticket->ticketid) }}" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('tickets.edit', $ticket->ticketid) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-ticket-perforated display-6 d-block mb-2"></i>
                                        <p class="mb-2">No tickets for this client yet.</p>
                                        <a href="{{ route('tickets.create', ['contact_id' => $contact->contactid]) }}" class="btn btn-primary btn-sm">
                                            <i class="bi bi-plus-lg me-1"></i> Add Ticket
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($ticketsPaginator && $ticketsPaginator->hasPages())
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-top bg-light">
                    <span class="small text-muted">Showing {{ $ticketsPaginator->firstItem() ?? 0 }}–{{ $ticketsPaginator->lastItem() ?? 0 }} of {{ $ticketsPaginator->total() }}</span>
                    {{ $ticketsPaginator->withQueryString()->links('pagination::bootstrap-5') }}
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Policies tab content (from ERP) --}}
    @if(($activeTab ?? '') === 'policies')
    <div class="col-12 mb-4">
        <div class="card contact-detail-card">
            <div class="card-body p-0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 border-bottom bg-light">
                    <h6 class="text-uppercase small fw-bold text-muted mb-0">Policies</h6>
                    <a href="{{ route('support.serve-client') }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-search me-1"></i> Search more in ERP
                    </a>
                </div>
                @if($policiesError ?? null)
                <div class="p-4">
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>{{ $policiesError }}
                        @if(str_contains($policiesError, 'ORA-01017') || str_contains($policiesError, 'invalid username/password'))
                        <p class="mb-1 mt-2 small fw-semibold">Oracle login denied (ORA-01017). Check:</p>
                        <ul class="mb-0 small ps-3">
                            <li><strong>ERP_USERNAME</strong> and <strong>ERP_PASSWORD</strong> in <code>.env</code> — verify with your DBA or test in SQL*Plus/SQL Developer.</li>
                            <li>If the password contains <code>#</code> or special chars, use <code>ERP_CREDENTIALS_FILE</code> (JSON) instead — see <code>config/erp-credentials.php</code>.</li>
                            <li>Account may be locked or password expired on the Oracle server.</li>
                        </ul>
                        <p class="mb-0 mt-2 small">To temporarily disable ERP: set <code>ERP_ENABLED=false</code> in <code>.env</code>.</p>
                        @elseif(str_contains($policiesError, 'Lost connection') || str_contains($policiesError, 'no reconnector'))
                        <p class="mb-1 mt-2 small fw-semibold">Oracle connection lost. This usually means:</p>
                        <ul class="mb-0 small ps-3">
                            <li>Network unreachable — verify the app can reach <code>ERP_HOST</code> (<code>10.1.4.101</code>) and <code>ERP_PORT</code> (<code>18032</code>).</li>
                            <li>Oracle closed idle connections — try refreshing the page; the app will retry once.</li>
                            <li>Firewall/VPN — ensure traffic to the Oracle server is allowed.</li>
                        </ul>
                        <p class="mb-0 mt-2 small">To temporarily disable ERP: set <code>ERP_ENABLED=false</code> in <code>.env</code>.</p>
                        @else
                        <p class="mb-0 mt-2 small">Ensure ERP credentials and <code>ERP_CLIENTS_TABLE</code> are configured.</p>
                        @endif
                    </div>
                </div>
                @elseif(empty($policies ?? []))
                <div class="p-5 text-center text-muted">
                    <i class="bi bi-box display-6 d-block mb-2 opacity-50"></i>
                    <p class="mb-2">No policies found for this client in the ERP.</p>
                    <a href="{{ route('support.serve-client') }}" class="btn btn-primary btn-sm">Search in Serve Client</a>
                </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small text-uppercase fw-bold">Policy</th>
                                <th class="small text-uppercase fw-bold">Name</th>
                                <th class="small text-uppercase fw-bold">Phone</th>
                                <th class="small text-uppercase fw-bold">Email</th>
                                <th class="small text-uppercase fw-bold text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($policies ?? [] as $policy)
                            @php
                                $policyNo = $policy['policy_no'] ?? $policy['policy_number'] ?? $policy['POLICY_NO'] ?? $policy['POLICY_NUMBER'] ?? '—';
                                $name = $policy['name'] ?? $policy['client_name'] ?? $policy['CLIENT_NAME'] ?? '—';
                                $phone = $policy['phone'] ?? $policy['mobile'] ?? $policy['PHONE'] ?? $policy['MOBILE'] ?? '—';
                                $email = $policy['email'] ?? $policy['EMAIL'] ?? '—';
                            @endphp
                            <tr class="policy-row cursor-pointer" data-policy='@json($policy)' role="button" tabindex="0">
                                <td class="fw-semibold font-monospace">{{ $policyNo }}</td>
                                <td>{{ $name }}</td>
                                <td><span class="text-muted">{{ $phone }}</span></td>
                                <td><span class="text-muted">{{ $email }}</span></td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary policy-view-btn" data-policy='@json($policy)' title="View details">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    @php
                                        $ticketParams = ['contact_id' => $contact->contactid];
                                        if ($policyNo !== '—' && !looks_like_kra_pin($policyNo)) { $ticketParams['policy'] = $policyNo; }
                                    @endphp
                                    <a href="{{ route('tickets.create', $ticketParams) }}" class="btn btn-sm btn-success" title="Create ticket">
                                        <i class="bi bi-ticket-perforated"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Policy detail modal --}}
    <div class="modal fade" id="policyDetailModal" tabindex="-1" aria-labelledby="policyDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="policyDetailModalLabel">
                        <i class="bi bi-box me-2"></i>Policy details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="policyDetailBody">
                    <div class="row" id="policyDetailFields"></div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="policyDetailViewClient" class="btn btn-primary me-auto" style="display:none;" title="View full client details">
                        <i class="bi bi-eye me-1"></i> View full details
                    </a>
                    <a href="#" id="policyDetailCreateTicket" class="btn btn-success" style="display:none;">
                        <i class="bi bi-ticket-perforated me-1"></i> Create ticket
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Calls tab content (PBX call history) --}}
    @if(($activeTab ?? '') === 'calls')
    <div class="col-12 mb-4">
        <div class="card contact-detail-card">
            <div class="card-body p-0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 border-bottom bg-light">
                    <h6 class="text-uppercase small fw-bold text-muted mb-0">Calls (PBX)</h6>
                    <div class="d-flex gap-2 align-items-center">
                        @if($contact->mobile ?? $contact->phone)
                        <a href="tel:{{ tel_href($contact->mobile ?? $contact->phone ?? '') }}" class="btn btn-success btn-sm" title="Call (opens MicroSIP)">
                            <i class="bi bi-telephone-outbound-fill me-1"></i>Call
                        </a>
                        @endif
                        <a href="{{ route('tools.pbx-manager') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-telephone me-1"></i>All Calls
                        </a>
                    </div>
                </div>
                @if(!($contact->mobile ?? $contact->phone))
                <div class="p-4">
                    <p class="text-muted mb-0">No phone number on file. Add mobile or phone in the contact details to match PBX calls.</p>
                </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 contact-calls-table">
                        <thead class="table-light">
                            <tr>
                                <th class="small text-uppercase fw-bold">Status</th>
                                <th class="small text-uppercase fw-bold">Direction</th>
                                <th class="small text-uppercase fw-bold">Number</th>
                                <th class="small text-uppercase fw-bold">Agent</th>
                                <th class="small text-uppercase fw-bold">Recording</th>
                                <th class="small text-uppercase fw-bold">Duration</th>
                                <th class="small text-uppercase fw-bold">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($calls ?? [] as $call)
                            <tr>
                                <td>
                                    <span class="badge pbx-badge pbx-badge-{{ Str::slug($call->call_status ?? '') }}">
                                        {{ $call->call_status ?? '—' }}
                                    </span>
                                </td>
                                <td>{{ $call->direction ?? '—' }}</td>
                                <td>
                                    @if(!empty($call->customer_number))
                                    <a href="tel:{{ tel_href($call->customer_number) }}" class="btn btn-sm btn-link p-0 text-primary text-decoration-none me-1" title="Call (opens MicroSIP)">
                                        <i class="bi bi-telephone-outbound"></i>
                                    </a>
                                    @endif
                                    <span class="font-monospace">{{ $call->customer_number ?? '—' }}</span>
                                </td>
                                <td>{{ $call->user_name ?? '—' }}</td>
                                <td>
                                    @if(($call->from_vtiger ?? false) && !empty($call->id))
                                    <button type="button" class="btn btn-sm btn-outline-primary pbx-play-btn" data-recording-url="{{ route('tools.pbx-manager.recording.vtiger', $call->id) }}" data-call-info="{{ $call->customer_number ?? '' }} — {{ optional($call->start_time)->format('d/m H:i') ?: '' }}">
                                        <i class="bi bi-play-circle me-1"></i>Listen
                                    </button>
                                    @elseif(!empty($call->recording_url))
                                    <button type="button" class="btn btn-sm btn-outline-primary pbx-play-btn" data-recording-url="{{ route('tools.pbx-manager.recording', $call->id) }}" data-call-info="{{ $call->customer_number ?? '' }} — {{ optional($call->start_time)->format('d/m H:i') ?: '' }}">
                                        <i class="bi bi-play-circle me-1"></i>Listen
                                    </button>
                                    @else
                                    <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $call->duration_sec ?? 0 }}s</td>
                                <td class="text-nowrap">{{ optional($call->start_time)->format('d M Y H:i') ?: '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-telephone display-6 d-block mb-2 opacity-50"></i>
                                    <p class="mb-2">No PBX calls found for this client.</p>
                                    <a href="{{ route('tools.pbx-manager') }}" class="btn btn-outline-primary btn-sm">View all calls</a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($callsPaginator && $callsPaginator->hasPages())
                <div class="d-flex justify-content-between align-items-center p-3 border-top bg-light">
                    <span class="small text-muted">{{ $callsPaginator->firstItem() ?? 0 }}–{{ $callsPaginator->lastItem() ?? 0 }} of {{ $callsPaginator->total() }}</span>
                    {{ $callsPaginator->links('pagination::bootstrap-5') }}
                </div>
                @endif
                @endif
            </div>
        </div>
    </div>

    {{-- Make Call modal is in layout (partials.pbx-tel-handler) for use app-wide including tel: links --}}
    <div class="modal fade" id="pbxRecordingModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-mic-fill me-2"></i>Call Recording</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3" id="pbxRecordingInfo">—</p>
                    <audio id="pbxRecordingAudio" controls preload="metadata" class="w-100" style="height:48px;"></audio>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        document.querySelectorAll('.pbx-play-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const url = this.dataset.recordingUrl, info = this.dataset.callInfo || '—';
                if (!url) return;
                const modal = new bootstrap.Modal(document.getElementById('pbxRecordingModal'));
                const audio = document.getElementById('pbxRecordingAudio');
                document.getElementById('pbxRecordingInfo').textContent = info;
                audio.pause(); audio.src = url; audio.load();
                modal.show();
                audio.addEventListener('canplaythrough', () => audio.play(), { once: true });
            });
        });
    })();
    </script>
    @endif

    {{-- Emails tab content (sent to client via life@geminialife.co.ke) --}}
    @if(($activeTab ?? '') === 'emails')
    <div class="col-12 mb-4">
        <div class="card contact-detail-card">
            <div class="card-body p-0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 border-bottom bg-light">
                    <h6 class="text-uppercase small fw-bold text-muted mb-0">Emails <small class="text-muted">(to/from {{ config('email-service.sender', 'life@geminialife.co.ke') }})</small></h6>
                    <div class="d-flex gap-2">
                        <a href="{{ route('tools.mail-manager.create', ['from_address' => $contact->email ?? $contact->secondaryemail ?? $contact->otheremail ?? '', 'from_name' => trim(($contact->firstname ?? '') . ' ' . ($contact->lastname ?? ''))]) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>Create Email
                        </a>
                        <a href="{{ route('tools.mail-manager') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-inbox me-1"></i>Mail Manager
                        </a>
                    </div>
                </div>
                @if(!($contact->email ?? $contact->secondaryemail ?? $contact->otheremail))
                <div class="p-4">
                    <p class="text-muted mb-0">No email address on file for this client. Add a primary, secondary, or other email in contact details to match emails, or <a href="{{ route('tools.mail-manager.create') }}">create an email record</a> manually.</p>
                </div>
                @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small text-uppercase fw-bold">From</th>
                                <th class="small text-uppercase fw-bold">Subject</th>
                                <th class="small text-uppercase fw-bold">Date</th>
                                <th class="small text-uppercase fw-bold text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($emails ?? [] as $email)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $email->from_name ?: $email->from_address }}</span>
                                    @if($email->from_name && $email->from_address)
                                    <br><small class="text-muted">{{ $email->from_address }}</small>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('tools.mail-manager.show', $email->id) }}" class="text-decoration-none text-dark">
                                        {{ Str::limit($email->subject ?? '(No subject)', 60) }}
                                    </a>
                                </td>
                                <td class="text-nowrap text-muted small">{{ $email->date ? \Carbon\Carbon::parse($email->date)->format('d M Y H:i') : '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('tools.mail-manager.show', $email->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-envelope display-6 d-block mb-2 opacity-50"></i>
                                    <p class="mb-2">No emails for this client yet.</p>
                                    <p class="small mb-0"><a href="{{ route('tools.mail-manager.create', ['from_address' => $contact->email ?? $contact->secondaryemail ?? '', 'from_name' => trim(($contact->firstname ?? '') . ' ' . ($contact->lastname ?? ''))]) }}">Create Email</a> to log one, or <a href="{{ route('tools.mail-manager') }}">Fetch emails</a> from life@geminialife.co.ke.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($emailsPaginator && $emailsPaginator->hasPages())
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-top bg-light">
                    <span class="small text-muted">Showing {{ $emailsPaginator->firstItem() ?? 0 }}–{{ $emailsPaginator->lastItem() ?? 0 }} of {{ $emailsPaginator->total() }}</span>
                    {{ $emailsPaginator->withQueryString()->links('pagination::bootstrap-5') }}
                </div>
                @endif
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Campaigns tab content --}}
    @if(($activeTab ?? '') === 'campaigns')
    <div class="col-12 mb-4">
        <div class="card contact-detail-card">
            <div class="card-body p-0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 border-bottom bg-light">
                    <h6 class="text-uppercase small fw-bold text-muted mb-0">Campaigns this client is part of</h6>
                    @php $availableCampaigns = ($allCampaigns ?? collect())->whereNotIn('id', ($campaigns ?? collect())->pluck('id')); @endphp
                    <div class="d-flex gap-2 align-items-center">
                        @if($availableCampaigns->isNotEmpty())
                        <form action="{{ route('contacts.campaigns.add', $contact->contactid) }}" method="POST" class="d-flex gap-2 align-items-center">
                            @csrf
                            <select name="campaign_id" class="form-select form-select-sm" style="min-width: 200px" required>
                                <option value="">Select Campaign</option>
                                @foreach($availableCampaigns as $c)
                                <option value="{{ $c->id }}">{{ $c->campaign_name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-plus-lg me-1"></i>Add to Campaign</button>
                        </form>
                        @else
                        <span class="text-muted small">Client is in all campaigns</span>
                        @endif
                        <a href="{{ route('marketing.campaigns.index') }}" class="btn btn-outline-secondary btn-sm">All Campaigns</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small text-uppercase fw-bold">Campaign Name</th>
                                <th class="small text-uppercase fw-bold">Assigned To</th>
                                <th class="small text-uppercase fw-bold">Status</th>
                                <th class="small text-uppercase fw-bold">Type</th>
                                <th class="small text-uppercase fw-bold">Expected Close</th>
                                <th class="small text-uppercase fw-bold">Revenue</th>
                                <th class="small text-uppercase fw-bold text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($campaigns ?? [] as $campaign)
                            <tr>
                                <td>
                                    <a href="{{ route('marketing.campaigns.edit', $campaign->id) }}" class="fw-semibold text-decoration-none">{{ $campaign->campaign_name ?? '—' }}</a>
                                </td>
                                <td><span class="text-muted">{{ $campaign->assigned_to ?? '—' }}</span></td>
                                <td><span class="badge bg-{{ ($campaign->campaign_status ?? '') === 'Active' ? 'success' : (($campaign->campaign_status ?? '') === 'Completed' ? 'secondary' : 'warning') }} bg-opacity-25 text-dark">{{ $campaign->campaign_status ?? '—' }}</span></td>
                                <td><span class="text-muted">{{ $campaign->campaign_type ?? '—' }}</span></td>
                                <td><span class="text-muted small">{{ $campaign->expected_close_date ? \Carbon\Carbon::parse($campaign->expected_close_date)->format('d M Y') : '—' }}</span></td>
                                <td><strong class="text-primary">KES {{ number_format($campaign->expected_revenue ?? 0, 0) }}</strong></td>
                                <td class="text-end">
                                    <form action="{{ route('contacts.campaigns.remove', [$contact->contactid, $campaign->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this client from the campaign?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove from campaign"><i class="bi bi-dash-lg"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-megaphone display-6 d-block mb-2 opacity-50"></i>
                                    <p class="mb-2">This client is not part of any campaign yet.</p>
                                    <p class="small mb-0">Use "Select Campaign" above to add them to a marketing campaign.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- SMS tab content --}}
    @if(($activeTab ?? '') === 'sms')
    <div class="col-12 mb-4">
        <div class="card contact-detail-card">
            <div class="card-body p-0">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 border-bottom bg-light">
                    <h6 class="text-uppercase small fw-bold text-muted mb-0">SMS sent</h6>
                    <a href="{{ route('support.sms-notifier', ['contact_id' => $contact->contactid]) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-send-fill me-1"></i>Send SMS
                    </a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small text-uppercase fw-bold">Date</th>
                                <th class="small text-uppercase fw-bold">To</th>
                                <th class="small text-uppercase fw-bold">Message</th>
                                <th class="small text-uppercase fw-bold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($smsLogs ?? [] as $log)
                            <tr>
                                <td class="text-nowrap">{{ optional($log->sent_at)->format('d M Y H:i') ?: optional($log->created_at)->format('d M Y H:i') ?: '—' }}</td>
                                <td class="font-monospace">{{ $log->phone ?? '—' }}</td>
                                <td><span class="text-muted">{{ Str::limit($log->message ?? '', 80) }}</span></td>
                                <td>
                                    @if(($log->status ?? '') === 'sent')
                                    <span class="badge bg-success bg-opacity-10 text-success">Sent</span>
                                    @else
                                    <span class="badge bg-danger bg-opacity-10 text-danger" title="{{ $log->error_message ?? 'Failed' }}">Failed</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">
                                    <i class="bi bi-chat-dots display-6 d-block mb-2 opacity-50"></i>
                                    <p class="mb-2">No SMS sent to this client yet.</p>
                                    <a href="{{ route('support.sms-notifier', ['contact_id' => $contact->contactid]) }}" class="btn btn-primary btn-sm">
                                        <i class="bi bi-send-fill me-1"></i>Send SMS
                                    </a>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($smsPaginator && $smsPaginator->hasPages())
                <div class="d-flex justify-content-between align-items-center p-3 border-top bg-light">
                    <span class="small text-muted">{{ $smsPaginator->firstItem() ?? 0 }}–{{ $smsPaginator->lastItem() ?? 0 }} of {{ $smsPaginator->total() }}</span>
                    {{ $smsPaginator->links('pagination::bootstrap-5') }}
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    {{-- Right: Activities first, then Comments, then Recent Comments (per Summary layout) --}}
    <div class="col-lg-4">
        <div class="card contact-detail-card mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase small fw-bold text-muted mb-0">Activities</h6>
                    <div class="d-flex gap-1">
                        <a href="{{ route('activities.create', ['type' => 'Task', 'related_to' => $contact->contactid]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg me-1"></i>Add Task</a>
                        <a href="{{ route('activities.create', ['type' => 'Event', 'related_to' => $contact->contactid]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plus-lg me-1"></i>Add Event</a>
                    </div>
                </div>
                @if($activities->isNotEmpty())
                    <ul class="list-unstyled mb-0">
                        @foreach($activities as $act)
                        <li class="py-2 border-bottom">
                            <strong>{{ $act->subject ?? 'Untitled' }}</strong>
                            <span class="badge bg-secondary ms-1">{{ $act->activitytype ?? 'Task' }}</span>
                            <p class="text-muted small mb-0">{{ $act->date_start ?? '' }}</p>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <div class="summary-empty-box py-4 text-center text-muted">No pending activities</div>
                @endif
            </div>
        </div>

        <div class="card contact-detail-card mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase small fw-bold text-muted mb-0">Follow-ups</h6>
                    <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#followupModal"><i class="bi bi-plus-lg me-1"></i>Log Follow-up</a>
                </div>
                @if(($followups ?? collect())->isNotEmpty())
                <ul class="list-unstyled mb-0">
                    @foreach($followups as $fu)
                    <li class="py-2 border-bottom">
                        <p class="mb-0 small">{{ Str::limit($fu->note, 100) }}</p>
                        <small class="text-muted">{{ $fu->followup_date ? $fu->followup_date->format('d M Y') : optional($fu->created_at)->format('d M Y') }} · {{ $fu->status }}</small>
                    </li>
                    @endforeach
                </ul>
                @else
                <div class="summary-empty-box py-4 text-center text-muted">
                    <i class="bi bi-calendar-check opacity-50 d-block mb-2"></i>
                    No follow-ups yet. Use "Log Follow-up" to track client outreach.
                </div>
                @endif
            </div>
        </div>

        <div class="card contact-detail-card mb-4" id="comments">
            <div class="card-body p-4">
                <h6 class="text-uppercase small fw-bold text-muted mb-3">Comments</h6>
                <textarea class="form-control mb-2" rows="3" placeholder="Post your comment here" disabled></textarea>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-1">
                        <button class="btn btn-sm btn-primary" disabled><i class="bi bi-paperclip me-1"></i>Attach Files</button>
                        <i class="bi bi-info-circle text-muted" title="Attach files to your comment"></i>
                    </div>
                    <button class="btn btn-sm btn-success" disabled>Post</button>
                </div>
            </div>
        </div>

        <div class="card contact-detail-card mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase small fw-bold text-muted mb-0">Recent Comments</h6>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted">Roll up</span>
                        <i class="bi bi-info-circle text-muted" title="Roll up comments"></i>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="rollupComments" disabled>
                            <label class="form-check-label small text-muted" for="rollupComments">OFF</label>
                        </div>
                    </div>
                </div>
                @if($comments->isNotEmpty())
                    <ul class="list-unstyled mb-0">
                        @foreach($comments as $c)
                        <li class="py-2 border-bottom">
                            <p class="mb-0">{{ $c->commentcontent ?? $c->comments ?? '' }}</p>
                            <small class="text-muted">{{ $c->createdtime ?? '' }}</small>
                        </li>
                        @endforeach
                    </ul>
                @else
                    <div class="summary-empty-box py-4 text-center text-muted">No comments</div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Follow-up modal --}}
<div class="modal fade" id="followupModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('contacts.followup.store', $contact->contactid) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-check me-2"></i>Log Follow-up</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Note</label>
                        <textarea name="note" class="form-control" rows="4" placeholder="What was discussed? Next steps?" required></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Follow-up Date (optional)</label>
                        <input type="date" name="followup_date" class="form-control" value="{{ date('Y-m-d') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Log Follow-up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.contact-detail-header { margin-bottom: 1.5rem; }
.contact-avatar-lg { width: 80px; height: 80px; border-radius: 16px; background: var(--primary, #0E4385); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; flex-shrink: 0; }
.contact-module-tabs { border-bottom: 2px solid var(--card-border, #e2e8f0); }
.contact-module-tabs .nav-link { color: var(--text-muted, #64748b); font-weight: 500; padding: 0.75rem 1rem; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.contact-module-tabs .nav-link:hover { color: var(--primary, #0E4385); }
.contact-module-tabs .nav-link.active { color: var(--primary, #0E4385); border-bottom-color: var(--primary, #0E4385); }
.contact-module-tabs .nav-link i { font-size: 1.1rem; }
.contact-detail-card { border-radius: 16px; border: 1px solid var(--card-border, rgba(14, 67, 133, 0.12)); }
.policy-row { cursor: pointer; }
.policy-row:hover { background-color: rgba(14, 67, 133, 0.04); }
.pbx-badge { font-size: .7rem; }
.pbx-badge-completed { background: rgba(5, 150, 105, 0.15); color: #059669; }
.pbx-badge-busy, .pbx-badge-no-response, .pbx-badge-no-answer { background: rgba(217, 119, 6, 0.15); color: #d97706; }
.summary-empty-box { background: rgba(0,0,0,0.02); border-radius: 8px; min-height: 60px; }
.tickets-badge-open, .tickets-badge-Open { background: rgba(14, 67, 133, 0.12); color: var(--primary); }
.tickets-badge-in-progress, .tickets-badge-In-Progress { background: rgba(245, 158, 11, 0.2); color: #d97706; }
.tickets-badge-closed, .tickets-badge-Closed { background: rgba(5, 150, 105, 0.15); color: #059669; }
.tickets-badge-wait-for-response, .tickets-badge-Wait-For-Response { background: rgba(56, 189, 248, 0.2); color: #0ea5e9; }
</style>
@if(($activeTab ?? '') === 'policies' && !empty($policies ?? []))
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('policyDetailModal');
    if (!modal) return;
    const bsModal = new bootstrap.Modal(modal);
    const body = document.getElementById('policyDetailFields');
    const createTicketBtn = document.getElementById('policyDetailCreateTicket');
    const viewClientBtn = document.getElementById('policyDetailViewClient');
    const contactId = {{ $contact->contactid }};
    const clientsShowUrl = '{{ url("/support/clients/show") }}';

    function formatKey(k) {
        return k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    }

    function showPolicy(policy) {
        if (!policy || typeof policy !== 'object') return;
        const skip = ['POLICY_NO', 'POLICY_NUMBER', 'policy_no', 'policy_number'];
        const fields = [];
        for (const [k, v] of Object.entries(policy)) {
            if (skip.includes(k) || v === null || v === '') continue;
            fields.push({ key: formatKey(k), value: String(v) });
        }
        // Add policy number at top
        const policyNo = policy.policy_no ?? policy.policy_number ?? policy.POLICY_NO ?? policy.POLICY_NUMBER ?? '—';
        body.innerHTML = '<div class="col-12 mb-2"><strong>Policy number</strong>: <code>' + policyNo + '</code></div>' +
            fields.map(f => '<div class="col-md-6 mb-2"><strong>' + f.key + '</strong>: ' + f.value + '</div>').join('');
        if (createTicketBtn) {
            createTicketBtn.href = '{{ url("/tickets/create") }}?contact_id=' + contactId + '&policy=' + encodeURIComponent(policyNo);
            createTicketBtn.style.display = 'inline-block';
        }
        if (viewClientBtn && policyNo && policyNo !== '—') {
            viewClientBtn.href = clientsShowUrl + '?policy=' + encodeURIComponent(policyNo);
            viewClientBtn.style.display = 'inline-block';
        } else if (viewClientBtn) {
            viewClientBtn.style.display = 'none';
        }
        bsModal.show();
    }

    document.querySelectorAll('.policy-row, .policy-view-btn').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.target.closest('a')) return;
            const data = el.getAttribute('data-policy');
            if (data) {
                try {
                    showPolicy(JSON.parse(data));
                } catch (err) {}
            }
        });
    });

    document.querySelectorAll('.policy-row').forEach(function(row) {
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const data = row.getAttribute('data-policy');
                if (data) {
                    try { showPolicy(JSON.parse(data)); } catch (err) {}
                }
            }
        });
    });
});
</script>
@endif

@if(($prevContactId ?? null) || ($nextContactId ?? null))
<script>
(function() {
    const prevId = @json($prevContactId ?? null);
    const nextId = @json($nextContactId ?? null);
    const tab = {{ in_array($activeTab ?? '', ['tickets','policies','calls','sms','emails','campaigns']) ? "'?tab=" . ($activeTab ?? '') . "'" : "''" }};
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.isContentEditable) return;
        if (e.key === 'ArrowLeft' && prevId) {
            e.preventDefault();
            window.location.href = '{{ url('/contacts') }}/' + prevId + tab;
        } else if (e.key === 'ArrowRight' && nextId) {
            e.preventDefault();
            window.location.href = '{{ url('/contacts') }}/' + nextId + tab;
        }
    });
})();
</script>
@endif
@endsection
