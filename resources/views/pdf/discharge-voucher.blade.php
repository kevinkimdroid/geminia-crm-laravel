@php
    $cfg = config('maturities.discharge_voucher', []);
    $company = config('app.name', 'Geminia Life');
    $issuer = $cfg['issuer_line'] ?? ($company.' — Client Service');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $cfg['document_title'] ?? 'Discharge Voucher' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1e293b; line-height: 1.45; margin: 36px 40px; }
        h1 { font-size: 18pt; color: #0E4385; margin: 0 0 6px 0; }
        .sub { font-size: 10pt; color: #64748b; margin-bottom: 22px; }
        .box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 16px; margin: 16px 0; }
        table.meta { width: 100%; border-collapse: collapse; }
        table.meta td { padding: 6px 0; vertical-align: top; }
        table.meta td.lbl { width: 32%; color: #64748b; font-size: 10pt; }
        .body-copy { margin: 18px 0; text-align: justify; }
        .footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 9pt; color: #64748b; }
        .sign { margin-top: 36px; }
    </style>
</head>
<body>
    <h1>{{ $cfg['document_title'] ?? 'Discharge Voucher' }}</h1>
    <p class="sub">{{ $cfg['subtitle'] ?? 'Policy maturity' }}</p>

    <div class="box">
        <table class="meta">
            <tr>
                <td class="lbl">Policy number</td>
                <td><strong>{{ $v['policy_number'] }}</strong></td>
            </tr>
            <tr>
                <td class="lbl">Life assured / client</td>
                <td>{{ $v['life_assured'] ?: '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Product</td>
                <td>{{ $v['product'] ?: '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Contractual maturity date</td>
                <td>{{ $v['maturity_display'] }}</td>
            </tr>
            @if(!empty($v['maturity_amount']))
            <tr>
                <td class="lbl">Maturity / benefit amount (if applicable)</td>
                <td>{{ $v['maturity_amount'] }}</td>
            </tr>
            @endif
            <tr>
                <td class="lbl">Voucher issue date</td>
                <td>{{ $v['issue_date_display'] }}</td>
            </tr>
        </table>
    </div>

    <div class="body-copy">
        <p>This document serves as a <strong>discharge voucher</strong> confirming that, subject to the terms and conditions of the above policy
        and completion of any outstanding requirements, the obligations of the insurer in respect of the policy maturity have been
        processed or are acknowledged as due in line with the maturity date shown.</p>
        <p>If you have questions about payout, tax certificates, or reinvestment options, please contact {{ $company }} Client Service
        quoting your policy number.</p>
        @if(!empty($cfg['extra_paragraph']))
            <p>{{ $cfg['extra_paragraph'] }}</p>
        @endif
    </div>

    <div class="sign">
        <p style="margin-bottom: 40px;">{{ $cfg['signatory_label'] ?? 'Authorised signatory' }}</p>
        <p style="font-size: 10pt; color: #64748b;">{{ $issuer }}</p>
    </div>

    <div class="footer">
        Generated on {{ $v['issue_date_display'] }} — reference: {{ $v['policy_number'] }} / {{ $v['maturity_iso'] }}.
        This is a system-generated document; no signature image is required unless your process mandates wet ink.
    </div>
</body>
</html>
