<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password — Geminia Life</title>
    <link rel="icon" type="image/png" href="{{ asset('images/geminia-logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --gem-primary: #1A468A; --gem-primary-dark: #133A6F; --gem-accent: #33B4E3; --gem-border: #D0D1D2; }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100%; font-family: 'Plus Jakarta Sans', sans-serif; -webkit-font-smoothing: antialiased; }
        body { position: relative; background: linear-gradient(135deg, #e0f2fa 0%, #bae6fd 20%, #e8f4fc 40%, #d4ebf7 60%, #e0f2fa 80%, #f0f9ff 100%); background-size: 400% 400%; animation: gradientShift 15s ease infinite; }
        @keyframes gradientShift { 0%, 100% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } }
        .reset-orb { position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.6; pointer-events: none; z-index: 0; }
        .reset-orb-1 { width: 350px; height: 350px; background: linear-gradient(135deg, rgba(51, 180, 227, 0.35) 0%, rgba(26, 70, 138, 0.2) 100%); top: -80px; right: -40px; }
        .reset-orb-2 { width: 250px; height: 250px; background: linear-gradient(135deg, rgba(26, 70, 138, 0.2) 0%, rgba(51, 180, 227, 0.18) 100%); bottom: -40px; left: -60px; }
        .reset-dots { position: fixed; inset: 0; background-image: radial-gradient(rgba(26, 70, 138, 0.1) 1px, transparent 1px); background-size: 24px 24px; pointer-events: none; z-index: 0; }
        .reset-panel { position: relative; z-index: 1; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .reset-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(232, 244, 252, 0.6) 100%);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2.5rem 2.25rem;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 8px 40px rgba(26, 70, 138, 0.12), 0 0 0 1px rgba(255,255,255,0.8);
            border-left: 4px solid transparent;
            background-image: linear-gradient(white, rgba(232, 244, 252, 0.6)), linear-gradient(180deg, var(--gem-accent), var(--gem-primary));
            background-origin: border-box;
            background-clip: padding-box, border-box;
        }
        .reset-logo { display: flex; flex-direction: row; align-items: center; justify-content: flex-start; gap: 0.65rem; margin-bottom: 2rem; }
        .reset-logo-img { width: 56px; height: 56px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
        .reset-logo-img img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .reset-logo-text { text-align: left; }
        .reset-logo-text h1 { font-size: 1.55rem; font-weight: 800; color: var(--gem-primary); margin: 0; letter-spacing: 0.05em; line-height: 1.2; text-rendering: optimizeLegibility; -webkit-font-smoothing: antialiased; }
        .reset-logo-text span { font-size: 0.72rem; font-weight: 600; color: var(--gem-primary); opacity: 0.88; letter-spacing: 0.12em; display: block; margin-top: 0.18rem; -webkit-font-smoothing: antialiased; }
        .reset-title { font-size: 1.6rem; font-weight: 700; color: #0f172a; margin-bottom: 0.6rem; }
        .reset-username { font-size: 0.9rem; color: var(--gem-primary); background: rgba(26, 70, 138, 0.08); padding: 0.5rem 0.85rem; border-radius: 10px; border: 1px solid rgba(26, 70, 138, 0.15); }
        .reset-subtitle { font-size: 0.95rem; color: #5b7a9e; margin-bottom: 1.5rem; line-height: 1.6; }
        .reset-input { border: 1.5px solid var(--gem-border); border-radius: 12px; padding: 0.9rem 1.1rem; font-size: 1rem; width: 100%; transition: all 0.25s ease; background: #fff; }
        .reset-input:focus { outline: none; border-color: var(--gem-primary); box-shadow: 0 0 0 4px rgba(51, 180, 227, 0.12); }
        .btn-reset { background: linear-gradient(135deg, var(--gem-accent) 0%, var(--gem-primary) 100%); border: none; border-radius: 12px; padding: 1rem 1.5rem; font-weight: 600; font-size: 1rem; color: #fff; width: 100%; transition: all 0.3s ease; box-shadow: 0 4px 14px rgba(26, 70, 138, 0.35); }
        .btn-reset:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(26, 70, 138, 0.45); color: #fff; }
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 1.5rem; color: var(--gem-primary); font-size: 0.95rem; font-weight: 600; text-decoration: none; }
        .back-link:hover { color: var(--gem-primary-dark); }
        .form-label { font-weight: 600; color: #334155; font-size: 0.9rem; }
        .reset-username { font-size: 0.95rem; color: var(--gem-primary); background: rgba(51, 180, 227, 0.12); padding: 0.6rem 1rem; border-radius: 10px; border-left: 3px solid var(--gem-primary); }
        .alert { border-radius: 12px; }
    </style>
</head>
<body>
    <div class="reset-orb reset-orb-1"></div>
    <div class="reset-orb reset-orb-2"></div>
    <div class="reset-dots"></div>
    <div class="reset-panel">
        <div class="reset-card">
            <div class="reset-logo">
                <div class="reset-logo-img">
                    <img src="{{ asset('images/geminia-logo.png') }}" alt="Geminia Life" width="56" height="56">
                </div>
                <div class="reset-logo-text">
                    <h1>GEMINIA LIFE</h1>
                    <span>LIFE ASSURANCE · CRM</span>
                </div>
            </div>
            <h2 class="reset-title">Set new password</h2>
            @if($user_name ?? null)
                <p class="reset-username mb-2"><i class="bi bi-person-fill me-1"></i> Resetting password for <strong>{{ $user_name }}</strong></p>
            @endif
            <p class="reset-subtitle">Enter your new password below. It must be at least 8 characters.</p>
            @if($errors->any())
                <div class="alert alert-danger py-3 mb-3">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    @foreach($errors->all() as $error) {{ $error }} @endforeach
                </div>
            @endif
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">
                <div class="mb-3">
                    <label class="form-label">New password</label>
                    <input type="password" name="password" class="form-control reset-input" required autofocus minlength="8" placeholder="Enter new password">
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirm password</label>
                    <input type="password" name="password_confirmation" class="form-control reset-input" required minlength="8" placeholder="Confirm new password">
                </div>
                <button type="submit" class="btn btn-reset">Reset password</button>
            </form>
            <a href="{{ route('login') }}" class="back-link"><i class="bi bi-arrow-left"></i> Back to sign in</a>
        </div>
    </div>
</body>
</html>
