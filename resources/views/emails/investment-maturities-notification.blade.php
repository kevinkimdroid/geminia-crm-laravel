<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Investment Maturities Notification</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#0f172a;">
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f4f7fb;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="860" style="max-width:860px;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;">
                    <tr>
                        <td style="background:#0e4385;color:#ffffff;padding:20px 24px;">
                            <h2 style="margin:0;font-size:20px;line-height:1.3;">Investment Maturities Alert</h2>
                            <p style="margin:6px 0 0 0;font-size:13px;opacity:0.92;">
                                Policies maturing in the next {{ $days }} days
                                @if(!empty($resend))
                                    (resend requested)
                                @endif
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 24px 8px 24px;">
                            <p style="margin:0 0 10px 0;font-size:14px;color:#334155;">
                                Please find below the current investment policies due for maturity within the selected window.
                            </p>
                            <p style="margin:0;font-size:12px;color:#64748b;">
                                Generated at: {{ $generatedAt->format('Y-m-d H:i:s') }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:10px 24px 24px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;border:1px solid #e2e8f0;">
                                <thead>
                                    <tr style="background:#eff6ff;">
                                        <th align="left" style="padding:10px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#1e3a8a;">Policy Number</th>
                                        <th align="left" style="padding:10px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#1e3a8a;">Maturity Date</th>
                                        <th align="left" style="padding:10px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#1e3a8a;">Client Name</th>
                                        <th align="left" style="padding:10px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#1e3a8a;">Product</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($rows as $row)
                                        @php
                                            $maturity = (string) ($row->pol_maturity_date ?? '');
                                            try {
                                                $maturity = \Carbon\Carbon::parse($maturity)->format('d M Y');
                                            } catch (\Throwable $e) {
                                                // keep raw value
                                            }
                                        @endphp
                                        <tr>
                                            <td style="padding:9px 10px;border-bottom:1px solid #f1f5f9;font-size:13px;font-family:Consolas,Monaco,monospace;">{{ $row->pol_policy_no ?? '' }}</td>
                                            <td style="padding:9px 10px;border-bottom:1px solid #f1f5f9;font-size:13px;">{{ $maturity }}</td>
                                            <td style="padding:9px 10px;border-bottom:1px solid #f1f5f9;font-size:13px;">{{ $row->full_name ?? '' }}</td>
                                            <td style="padding:9px 10px;border-bottom:1px solid #f1f5f9;font-size:13px;">{{ $row->product ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f8fafc;padding:14px 24px;color:#64748b;font-size:12px;">
                            Geminia CRM automated notification.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

