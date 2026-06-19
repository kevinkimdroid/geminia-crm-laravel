@extends('layouts.app')

@section('title', 'Receipt Reprint')

@push('head')
<style>
    .rcpt-hero {
        position: relative;
        overflow: hidden;
        border-radius: 1.25rem;
        background: linear-gradient(135deg, #1A468A 0%, #133A6F 55%, #0f2d57 100%);
        box-shadow: 0 18px 40px -18px rgba(19, 58, 111, 0.6);
        color: #fff;
    }
    .rcpt-hero__bubble { position: absolute; border-radius: 9999px; pointer-events: none; }
    .rcpt-hero__bubble--1 { top: -70px; right: -50px; width: 230px; height: 230px; background: rgba(255,255,255,0.06); }
    .rcpt-hero__bubble--2 { bottom: -100px; right: 110px; width: 230px; height: 230px; background: rgba(51,180,227,0.18); }
    .rcpt-pill {
        display: inline-flex; align-items: center; gap: .5rem;
        background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.18);
        border-radius: 9999px; padding: .25rem .75rem;
        font-size: .68rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
    }
    .rcpt-pill .dot { width: 7px; height: 7px; border-radius: 9999px; background: #33B4E3; display: inline-block; }
    .rcpt-hero h1 { font-weight: 800; letter-spacing: -0.02em; line-height: 1.1; }
    .rcpt-seg { display: inline-flex; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.18); border-radius: .8rem; padding: .25rem; gap: .15rem; }
    .rcpt-seg .seg-btn {
        border: 0; background: transparent; color: rgba(255,255,255,0.8);
        border-radius: .6rem; padding: .35rem .9rem; font-size: .8rem; font-weight: 600;
        transition: all .15s ease;
    }
    .rcpt-seg .seg-btn:hover { color: #fff; }
    .rcpt-seg .seg-btn.active { background: #fff; color: #133A6F; box-shadow: 0 2px 6px rgba(0,0,0,0.12); }
    .rcpt-search { background: #fff; border-radius: 1rem; box-shadow: 0 10px 24px -12px rgba(0,0,0,0.35); }
    .rcpt-search input {
        border: 0; height: 56px; font-size: .95rem; font-weight: 500; color: #1e293b;
        background: transparent; box-shadow: none !important;
    }
    .rcpt-search input:focus { outline: none; }
    .rcpt-search .input-icon { color: #94a3b8; }
    .rcpt-btn-go {
        border: 0; border-radius: .85rem; height: 56px; padding: 0 1.6rem;
        background: linear-gradient(135deg, #33B4E3, #1A468A);
        color: #fff; font-weight: 700; font-size: .9rem;
        box-shadow: 0 8px 18px -8px rgba(26,70,138,0.7); transition: transform .12s ease, box-shadow .15s ease;
        white-space: nowrap;
    }
    .rcpt-btn-go:hover { box-shadow: 0 12px 22px -8px rgba(26,70,138,0.8); }
    .rcpt-btn-go:active { transform: scale(.98); }
    .rcpt-chip {
        border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.85);
        border-radius: 9999px; padding: .2rem .7rem; font-size: .75rem; font-weight: 500; transition: all .15s ease;
    }
    .rcpt-chip:hover { background: rgba(255,255,255,0.14); color: #fff; border-color: rgba(255,255,255,0.4); }
    .rcpt-avatar {
        width: 40px; height: 40px; flex: none; border-radius: .7rem; color: #fff; font-weight: 700; font-size: .8rem;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #33B4E3, #133A6F);
    }
    .rcpt-step-ic {
        width: 38px; height: 38px; border-radius: .7rem; display: flex; align-items: center; justify-content: center;
        background: rgba(51,180,227,0.14); color: #1A468A; font-size: 1.05rem;
    }
    .rcpt-results-card { border: 1px solid #e9eef5; border-radius: 1rem; overflow: hidden; }
</style>
@endpush

@section('content')
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-octagon-fill me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="rcpt-hero p-4 p-sm-5">
    <span class="rcpt-hero__bubble rcpt-hero__bubble--1"></span>
    <span class="rcpt-hero__bubble rcpt-hero__bubble--2"></span>

    <div class="position-relative" style="max-width: 640px;">
        <span class="rcpt-pill"><span class="dot"></span> Receipt Reprint</span>
        <h1 class="h2 mt-3 mb-2">Find &amp; reprint an official premium receipt</h1>
        <p class="mb-0" style="color: rgba(255,255,255,0.75); max-width: 30rem;">
            Look up any receipt by its number, the policy number, or the client&rsquo;s name — then preview the full breakdown and reprint a sealed duplicate.
        </p>
    </div>

    <form action="{{ route('finance.receipts.search') }}" method="GET" id="receiptSearchForm" class="position-relative mt-4">
        <input type="hidden" name="type" id="type-input" value="{{ $type }}">

        <div class="rcpt-seg mb-3">
            @foreach(['receipt' => 'Receipt No.', 'policy' => 'Policy No.', 'client' => 'Client Name'] as $value => $label)
            <button type="button" data-type="{{ $value }}" class="seg-btn {{ $type === $value ? 'active' : '' }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="d-flex flex-column flex-sm-row gap-2" style="max-width: 720px;">
            <div class="rcpt-search d-flex align-items-center flex-grow-1 px-3">
                <i class="bi bi-search input-icon me-2"></i>
                <input id="query" name="query" type="text" value="{{ $query }}" autofocus autocomplete="off"
                       class="form-control" placeholder="e.g. 604195 or 1/HO/111640">
            </div>
            <button type="submit" class="rcpt-btn-go"><i class="bi bi-search me-1"></i> Search</button>
        </div>

        @error('query')
            <p class="mt-2 mb-0 small fw-semibold" style="color:#fecaca;"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</p>
        @enderror

        <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
            <span class="small" style="color: rgba(255,255,255,0.6);">Search by:</span>
            <span class="rcpt-chip"><i class="bi bi-hash me-1"></i>Internal no. (digits)</span>
            <span class="rcpt-chip"><i class="bi bi-upc me-1"></i>Branch receipt code</span>
        </div>
    </form>
</div>

<div class="alert alert-warning border d-flex align-items-center gap-2 mt-3 mb-0 py-2" role="alert">
    <i class="bi bi-shield-lock"></i>
    <span class="small mb-0"><strong>Restricted module:</strong> accessible by Finance department users and Administrators only.</span>
</div>

@if(!empty($error))
    <div class="alert alert-danger border mt-3 d-flex align-items-start gap-3" role="alert">
        <i class="bi bi-database-exclamation fs-5"></i>
        <div>
            <div class="fw-bold">Receipt service unavailable</div>
            <div class="small">{{ $error }}</div>
        </div>
    </div>
@elseif(!is_null($results))
    <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
        <h2 class="h6 mb-0 text-muted">Search results</h2>
        <span class="badge bg-light text-dark border rounded-pill">{{ count($results) }} {{ \Illuminate\Support\Str::plural('receipt', count($results)) }}</span>
    </div>

    @if(count($results) === 0)
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body text-center py-5 text-muted">
                <div class="rcpt-step-ic mx-auto mb-3"><i class="bi bi-search"></i></div>
                <div class="fw-semibold text-dark">No receipts found</div>
                <div class="small">Nothing matched <strong>&ldquo;{{ $query }}&rdquo;</strong>. Try a different search type or check the spelling.</div>
            </div>
        </div>
    @else
        <div class="rcpt-results-card bg-white shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-uppercase small text-muted">
                            <th>Client &amp; Receipt</th>
                            <th>Date</th>
                            <th>Policy No.</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $row)
                        @php
                            $linkParams = array_filter([
                                'receiptNo' => $row['rct_no'] ?? $row['receipt_no'] ?? '',
                                'branch' => $row['branch_code'] ?? null,
                            ], fn ($v) => $v !== null && $v !== '');
                            $name = trim((string) ($row['client_name'] ?? ''));
                            $parts = preg_split('/\s+/', $name);
                            $initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr(end($parts) ?: '', 0, 1));
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="rcpt-avatar">{{ $initials ?: 'R' }}</span>
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-truncate" style="max-width:260px;">{{ $name ?: '—' }}</div>
                                        <div class="small text-primary font-monospace">{{ $row['receipt_no'] ?? '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="text-nowrap">{{ $row['receipt_date'] ?? '' }}</td>
                            <td class="small font-monospace text-truncate" style="max-width:180px;">{{ $row['policy_no'] ?? '' }}</td>
                            <td class="text-end text-nowrap fw-semibold">
                                <span class="small text-muted">{{ $row['currency'] ?? '' }}</span>
                                {{ number_format((float) ($row['amount'] ?? 0), 2) }}
                            </td>
                            <td class="text-end">
                                <a href="{{ route('finance.receipts.preview', $linkParams) }}" class="btn btn-sm btn-primary rounded-pill px-3">
                                    Preview <i class="bi bi-chevron-right ms-1"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@else
    <div class="row g-3 mt-1">
        @php
            $steps = [
                ['1', 'bi-search', 'Search', 'Look up by receipt, policy, or client name.'],
                ['2', 'bi-eye', 'Preview', 'Review the header and full breakdown.'],
                ['3', 'bi-printer', 'Reprint', 'Download a sealed PDF/RTF copy, or the raw XML.'],
            ];
        @endphp
        @foreach($steps as $s)
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="rcpt-step-ic"><i class="bi {{ $s[1] }}"></i></span>
                        <span class="text-uppercase small fw-bold text-muted">Step {{ $s[0] }}</span>
                    </div>
                    <div class="fw-semibold" style="color:#133A6F;">{{ $s[2] }}</div>
                    <div class="text-muted small">{{ $s[3] }}</div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
@endif

<script>
    (function () {
        const form = document.getElementById('receiptSearchForm');
        if (!form) return;
        const typeInput = form.querySelector('#type-input');
        const query = form.querySelector('#query');
        const placeholders = {
            receipt: 'e.g. 604195 or 1/HO/111640',
            policy: 'e.g. POL-558721',
            client: 'e.g. Jane Wanjiku',
        };
        form.querySelectorAll('.seg-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                typeInput.value = btn.dataset.type;
                form.querySelectorAll('.seg-btn').forEach((b) => b.classList.toggle('active', b === btn));
                if (placeholders[btn.dataset.type]) query.placeholder = placeholders[btn.dataset.type];
                query.focus();
            });
        });
    })();
</script>
@endsection
