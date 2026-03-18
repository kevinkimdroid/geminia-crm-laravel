<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Expired — Geminia Life</title>
    <link rel="icon" type="image/png" href="{{ asset('images/geminia-logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg, #f0f9ff 0%, #f8fafc 100%);
            font-family: system-ui, -apple-system, sans-serif;
            color: #1e293b;
        }
        .err-card {
            max-width: 440px;
            padding: 2.5rem;
            text-align: center;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(26, 70, 138, 0.1);
            border: 1px solid rgba(26, 70, 138, 0.08);
        }
        .err-icon {
            width: 72px; height: 72px;
            background: rgba(26, 70, 138, 0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: #1A468A;
        }
        .err-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem; }
        .err-desc { color: #64748b; font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem; }
        .err-actions { display: flex; flex-direction: column; gap: 0.75rem; }
        .err-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            padding: 0.75rem 1.5rem; font-weight: 600; font-size: 0.95rem;
            border-radius: 12px; text-decoration: none; transition: all 0.2s;
        }
        .err-btn-primary {
            background: linear-gradient(135deg, #1A468A 0%, #2563eb 100%);
            color: #fff; border: none;
        }
        .err-btn-primary:hover { color: #fff; transform: translateY(-1px); box-shadow: 0 4px 16px rgba(26, 70, 138, 0.3); }
        .err-btn-secondary {
            background: #f8fafc; color: #475569; border: 1px solid #e2e8f0;
        }
        .err-btn-secondary:hover { background: #f1f5f9; color: #1A468A; }
    </style>
</head>
<body>
    <div class="err-card">
        <div class="err-icon"><i class="bi bi-arrow-repeat"></i></div>
        <h1 class="err-title">Session Expired</h1>
        <p class="err-desc">
            Your session may have timed out, or this page was open too long. Refresh to get a fresh session and try your action again.
        </p>
        <div class="err-actions">
            <a href="{{ request()->header('referer') ?? url('/') }}" class="err-btn err-btn-primary">
                <i class="bi bi-arrow-clockwise"></i> Refresh & try again
            </a>
            <a href="{{ url('/') }}" class="err-btn err-btn-secondary">
                <i class="bi bi-house"></i> Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
