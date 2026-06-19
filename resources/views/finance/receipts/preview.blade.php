@extends('layouts.app')

@section('title', 'Receipt ' . ($header['receipt_no'] ?? ''))

@push('head')
<style>
    .rcpt-paper {
        background: #fff; border-radius: 1rem;
        box-shadow: 0 24px 60px -28px rgba(15, 45, 87, 0.45);
        overflow: hidden; position: relative;
    }
    .rcpt-paper__strip { height: 6px; background: linear-gradient(90deg, #133A6F, #33B4E3, #133A6F); }
    .rcpt-watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; pointer-events: none; overflow: hidden; }
    .rcpt-watermark span { transform: rotate(-26deg); font-size: 7rem; font-weight: 900; letter-spacing: .2em; text-transform: uppercase; color: rgba(26,70,138,0.05); user-select: none; white-space: nowrap; }
    .rcpt-sec-title { background: #133A6F; color: #fff; font-size: .68rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; padding: .35rem .7rem; border-radius: .4rem; }
    .rcpt-label { text-transform: uppercase; color: #94a3b8; font-size: .62rem; letter-spacing: .09em; font-weight: 700; }
    .rcpt-val { font-weight: 600; color: #1e293b; }
    .rcpt-amounts { border: 1px solid #e9eef5; border-radius: .75rem; overflow: hidden; }
    .rcpt-amounts td { padding: .5rem .8rem; }
    .rcpt-stamp { height: 86px; width: 86px; transform: rotate(-12deg); border: 2px dashed rgba(26,70,138,0.3); border-radius: 9999px; display: flex; align-items: center; justify-content: center; }
    .rcpt-action {
        border: 0; border-radius: .7rem; padding: .5rem 1.1rem; font-weight: 700; font-size: .85rem; color: #fff;
        display: inline-flex; align-items: center; gap: .4rem; text-decoration: none; transition: transform .12s ease, box-shadow .15s ease;
    }
    .rcpt-action:active { transform: scale(.98); }
    .rcpt-action--print { background: linear-gradient(135deg, #1A468A, #133A6F); box-shadow: 0 8px 18px -8px rgba(19,58,111,.6); color:#fff; }
    .rcpt-action--print:hover { color:#fff; }
    .rcpt-action--get { background: linear-gradient(135deg, #33B4E3, #1A468A); box-shadow: 0 8px 18px -8px rgba(26,70,138,.6); color:#fff; }
    .rcpt-action--get:hover { color:#fff; }
    .rcpt-action--ghost { background: #fff; border: 1px solid #cbd5e1; color: #475569; }
    .rcpt-action--ghost:hover { border-color: #1A468A; color: #1A468A; }
    .border-dashed { border-style: dashed !important; }
    @media print {
        @page { size: A4 portrait; margin: 8mm; }
        .app-sidebar, .app-topbar, .no-print { display: none !important; }
        .app-main, .app-content { margin: 0 !important; padding: 0 !important; }
        .rcpt-paper { box-shadow: none !important; }
        body { background: #fff !important; }
    }
</style>
@endpush

@section('content')
@php
    $rid = $header['rct_no'] ?? $header['receipt_no'] ?? '';
    $rbranch = $header['branch_code'] ?? null;
    $linkParams = array_filter(['receiptNo' => $rid, 'branch' => $rbranch], fn ($v) => $v !== null && $v !== '');

    $cur = $header['currency'] ?? '';
    $fmtAmt = fn ($v) => ($v === null || $v === '') ? '' : trim($cur.' '.number_format((float) $v, 2));

    $policyDetails = array_filter([
        'Policy Number' => $header['policy_number'] ?? $header['policy_no'] ?? null,
        "Intermediaries' Name" => $header['intermediary'] ?? null,
        'Policyholder' => $header['client_name'] ?? null,
        'Payment Mode' => $header['payment_mode'] ?? null,
    ], fn ($v) => $v !== null && $v !== '');

    $paymentInfo = array_filter([
        'Payment Date' => $header['receipt_date'] ?? null,
        'Payment Mode' => $header['payment_mode'] ?? null,
        'Received From' => $header['received_from'] ?? null,
        'Drawers Bank' => $header['drawers_bank'] ?? null,
        'Cheque No' => $header['cheque_no'] ?? $header['reference_no'] ?? null,
        'Being Payment For' => $header['being_payment_for'] ?? $header['narration'] ?? null,
    ], fn ($v) => $v !== null && $v !== '');

    $amountRows = [
        ['Gross Amount', $header['gross_amount'] ?? null],
        ['Geog Ext. Amount', $header['geog_ext_amount'] ?? null],
        ['Other Amount', $header['other_amount'] ?? null],
    ];
@endphp

<div class="no-print d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <a href="{{ route('finance.receipts.index') }}" class="text-decoration-none small text-muted">
            <i class="bi bi-arrow-left me-1"></i> Back to search
        </a>
        <h1 class="page-title mb-0" style="color:#133A6F;">Receipt preview</h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" onclick="window.print()" class="rcpt-action rcpt-action--print">
            <i class="bi bi-printer"></i> Print receipt
        </button>
        <a href="{{ route('finance.receipts.xml', $linkParams) }}" class="rcpt-action rcpt-action--ghost">
            <i class="bi bi-filetype-xml"></i> XML
        </a>
        <a href="{{ route('finance.receipts.reprint', $linkParams) }}" class="rcpt-action rcpt-action--get">
            <i class="bi bi-download"></i> Reprint receipt
        </a>
    </div>
</div>

<div class="mx-auto" style="max-width: 820px;">
    <div class="rcpt-paper">
        <div class="rcpt-paper__strip"></div>
        <div class="rcpt-watermark"><span>Duplicate</span></div>

        <div class="position-relative p-4 p-sm-5">
            {{-- Letterhead --}}
            <div class="d-flex justify-content-between align-items-start border-bottom border-2 pb-3" style="border-color:#133A6F !important;">
                <div class="d-flex align-items-center gap-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-3 p-2" style="background:#133A6F;">
                        <img src="{{ asset('images/geminia-logo.png') }}" alt="Geminia Life" style="height:34px;width:auto;filter:brightness(0) invert(1);">
                    </span>
                    <div class="small text-muted lh-sm">
                        <div>P.O Box 61316-00200 Nairobi &middot; 4th Floor, Geminia Insurance Plaza</div>
                        <div>Call 0709 551 150 &middot; life@geminialife.co.ke</div>
                    </div>
                </div>
                <span class="badge rounded-pill text-uppercase" style="background:rgba(51,180,227,.12);color:#1A468A;border:1px solid rgba(51,180,227,.35);">Duplicate</span>
            </div>

            {{-- Title --}}
            <div class="text-center mt-4">
                <h2 class="h5 fw-bold text-uppercase mb-1" style="color:#133A6F;">{{ $header['title'] ?? 'Premium Payment Receipt' }}</h2>
                <div class="small text-muted">Receipt Number:
                    <span class="font-monospace fw-bold text-primary">{{ $header['receipt_no'] ?? '' }}</span>
                </div>
            </div>

            {{-- Policy details --}}
            <div class="mt-4">
                <span class="rcpt-sec-title">Policy Details</span>
                <div class="row g-3 mt-1">
                    @foreach($policyDetails as $label => $value)
                    <div class="col-sm-6">
                        <div class="rcpt-label">{{ $label }}</div>
                        <div class="rcpt-val">{{ $value }}</div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Payment information --}}
            <div class="mt-4">
                <span class="rcpt-sec-title">Payment Information</span>
                <div class="row g-3 mt-1">
                    @foreach($paymentInfo as $label => $value)
                    <div class="col-sm-6">
                        <div class="rcpt-label">{{ $label }}</div>
                        <div class="rcpt-val">{{ $value }}</div>
                    </div>
                    @endforeach
                </div>

                {{-- Amounts --}}
                <div class="ms-sm-auto mt-4" style="max-width: 340px;">
                    <table class="rcpt-amounts w-100 mb-0">
                        <tbody>
                            <tr>
                                <td class="rcpt-label">Currency</td>
                                <td class="text-end rcpt-val">{{ $cur ?: '—' }}</td>
                            </tr>
                            @foreach($amountRows as [$label, $value])
                            <tr style="border-top:1px solid #eef2f7;">
                                <td class="rcpt-label">{{ $label }}</td>
                                <td class="text-end rcpt-val">{{ $fmtAmt($value) }}</td>
                            </tr>
                            @endforeach
                            <tr style="background:#133A6F;">
                                <td class="text-uppercase text-white fw-bold" style="font-size:.7rem;padding:.6rem .8rem;">Total</td>
                                <td class="text-end text-white fw-bold" style="padding:.6rem .8rem;">{{ $fmtAmt($header['total_amount'] ?? $header['amount'] ?? null) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if(!empty($header['amount_in_words']))
                    <p class="mt-3 fst-italic text-muted small">
                        <span class="fw-semibold fst-normal text-secondary">Amount in words:</span> {{ $header['amount_in_words'] }}
                    </p>
                @endif
            </div>

            {{-- Signature --}}
            <div class="d-flex justify-content-between align-items-end mt-5">
                <div>
                    <div style="height:1px;width:220px;background:#94a3b8;"></div>
                    <div class="rcpt-label mt-1">Authorized Signature</div>
                </div>
                <div class="rcpt-stamp">
                    <span class="text-uppercase fw-bold lh-1" style="font-size:.6rem;color:rgba(26,70,138,.6);text-align:center;">Geminia<br>Life<br>Received</span>
                </div>
            </div>

            {{-- Meta --}}
            <div class="d-flex flex-wrap justify-content-between gap-2 border-top border-dashed mt-4 pt-3 small text-muted">
                <span>Date Printed: <span class="fw-semibold text-dark">{{ now()->format('d-m-Y') }}</span></span>
                <span>User Name: <span class="fw-semibold text-dark">{{ $header['user_name'] ?? $header['cashier'] ?? '—' }}</span></span>
            </div>

            {{-- Footer --}}
            <div class="border-top mt-3 pt-3 text-center text-muted" style="font-size:.7rem;">
                <div>Geminia Life Insurance | P.O Box 61316-00200 Nairobi | 4th Floor Geminia Insurance Plaza</div>
                <div>Call 0709 551 150 | Email life@geminialife.co.ke</div>
            </div>
        </div>
    </div>
</div>
@endsection
