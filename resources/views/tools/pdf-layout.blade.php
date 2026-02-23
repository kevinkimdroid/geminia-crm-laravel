<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e3a5f; margin: 0; padding: 20px; }
        .pdf-header { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
        .pdf-footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #64748b; }
        .pdf-content { }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px; border-bottom: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="pdf-header">{!! $headerHtml !!}</div>
    <div class="pdf-content">{!! $bodyHtml !!}</div>
    <div class="pdf-footer">{!! $footerHtml !!}</div>
</body>
</html>
