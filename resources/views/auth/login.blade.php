<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — Geminia Life Insurance</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --geminia-primary: #1A559E;
            --geminia-primary-dark: #144177;
            --geminia-light: #4a7fc4;
            --geminia-bg: #0f2d4d;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { font-size: 15px; }
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            height: 100%;
            overflow-x: hidden;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        body {
            background: var(--geminia-bg);
            background-image: 
                linear-gradient(135deg, rgba(26, 85, 158, 0.15) 0%, transparent 50%),
                linear-gradient(225deg, rgba(20, 65, 119, 0.2) 0%, transparent 50%),
                url('{{ asset("images/login-bg.png") }}');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            background-attachment: fixed;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: linear-gradient(105deg,
                rgba(255,255,255,0.6) 0%,
                rgba(255,255,255,0.35) 35%,
                rgba(255,255,255,0.08) 60%,
                transparent 80%);
            pointer-events: none;
            z-index: 0;
        }
        .login-page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: clamp(1rem, 3vw, 2rem) clamp(1.5rem, 4vw, 2.5rem);
        }
        .login-card {
            background: rgba(255,255,255,0.98);
            border: 1px solid rgba(255,255,255,0.9);
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.06), 0 25px 50px -12px rgba(0,0,0,0.15);
            padding: clamp(1.5rem, 3vw, 2rem);
            max-width: 380px;
            width: 100%;
            margin-top: clamp(1rem, 2vw, 1.5rem);
            backdrop-filter: blur(12px);
        }
        .login-logo {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            margin-bottom: 1.75rem;
        }
        .login-logo-img {
            width: 48px;
            height: 48px;
            flex-shrink: 0;
        }
        .login-logo-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .login-logo-text h1 {
            font-size: clamp(1.2rem, 2vw, 1.4rem);
            font-weight: 700;
            color: var(--geminia-primary);
            margin: 0;
            letter-spacing: 0.05em;
        }
        .login-logo-text span {
            font-size: 0.72rem;
            font-weight: 600;
            color: var(--geminia-light);
            letter-spacing: 0.12em;
        }
        .form-label {
            font-weight: 600;
            color: var(--geminia-primary);
            margin-bottom: 0.5rem;
        }
        .login-input {
            border: none;
            border-bottom: 2px solid #e2e8f0;
            border-radius: 0;
            padding: 0.65rem 0;
            font-size: 1rem;
            background: transparent;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .login-input:focus {
            outline: none;
            border-bottom-color: var(--geminia-primary);
            box-shadow: none;
        }
        .login-input::placeholder {
            color: #94a3b8;
        }
        .btn-signin {
            background: linear-gradient(135deg, var(--geminia-primary) 0%, var(--geminia-primary-dark) 100%);
            border: none;
            border-radius: 9999px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            color: #fff;
            width: 100%;
            margin-top: 1rem;
            transition: transform 0.15s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 14px rgba(26, 85, 158, 0.35);
        }
        .btn-signin:hover {
            color: #fff;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(26, 85, 158, 0.4);
        }
        .btn-signin:active {
            transform: translateY(0);
        }
        .forgot-link {
            display: block;
            text-align: center;
            margin-top: 1.125rem;
            color: var(--geminia-light);
            font-size: 0.875rem;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .forgot-link:hover {
            color: var(--geminia-primary);
        }
        .login-footer {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 1.75rem;
            margin-top: auto;
            padding: 1.25rem 1.5rem;
            padding-bottom: clamp(0.75rem, 2vw, 1rem);
            width: 100%;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            border-radius: 14px 14px 0 0;
            box-shadow: 0 -4px 20px rgba(26, 85, 158, 0.08);
        }
        .footer-sections {
            display: flex;
            gap: 1.75rem;
            flex-wrap: wrap;
        }
        .footer-section {
            padding: 0 1.5rem;
            border-right: 1px solid rgba(26, 85, 158, 0.5);
        }
        .footer-section:first-child { padding-left: 0; }
        .footer-section:last-of-type {
            border-right: none;
            padding-right: 0;
        }
        .footer-section h6 {
            font-size: 0.7rem;
            font-weight: 700;
            font-style: italic;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--geminia-primary);
            margin-bottom: 0.3rem;
        }
        .footer-section p {
            font-size: 0.8rem;
            color: var(--geminia-primary);
            margin: 0;
            max-width: 200px;
            line-height: 1.5;
        }
        .footer-section p.core-values {
            line-height: 1.6;
        }
        .social-icons { display: flex; gap: 0.5rem; }
        .social-icon {
            width: 36px;
            height: 36px;
            background: var(--geminia-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-decoration: none;
            font-size: 0.9rem;
            transition: background 0.2s ease, transform 0.15s ease;
        }
        .social-icon:hover {
            background: var(--geminia-primary-dark);
            transform: translateY(-2px);
        }
        .slogan-corner {
            position: fixed;
            bottom: 0;
            right: 0;
            width: min(400px, 100%);
            height: 160px;
            background-image: linear-gradient(135deg, transparent 40%, rgba(26, 85, 158, 0.5) 100%), url('{{ asset("images/login-slogan-bg.png") }}');
            background-repeat: no-repeat;
            background-position: bottom right;
            background-size: contain;
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
            padding: 1rem 1.5rem;
            z-index: 2;
            pointer-events: none;
        }
        .slogan-text {
            color: #fff;
            font-weight: 700;
            font-size: clamp(0.75rem, 1vw, 0.9rem);
            letter-spacing: 0.08em;
            line-height: 1.5;
            text-align: right;
            text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        }
        .alert-danger {
            border-radius: 12px;
        }
        @media (max-width: 768px) {
            .login-page { padding: 1rem; }
            .login-card { max-width: 100%; }
            .login-footer { flex-direction: column; align-items: flex-start; }
            .footer-sections { flex-direction: column; }
            .footer-section {
                border-right: none;
                border-bottom: 1px solid rgba(255,255,255,0.3);
                padding-bottom: 1rem;
                padding-right: 0;
            }
            .slogan-corner { width: 220px; height: 100px; padding: 0.5rem 1rem; }
            .slogan-text { font-size: 0.7rem; }
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div>
            <div class="login-card">
                <div class="login-logo">
                    <div class="login-logo-img">
                        <img src="{{ asset('images/geminia-logo.svg') }}" alt="Geminia Life Insurance" width="48" height="48">
                    </div>
                    <div class="login-logo-text">
                        <h1>GEMINIA</h1>
                        <span>LIFE INSURANCE</span>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger py-3 mb-3 rounded-3">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        @foreach ($errors->all() as $error)
                            {{ $error }}
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" id="loginForm">
                    @csrf
                    <div class="mb-4">
                        <label class="form-label">Username</label>
                        <input type="text" name="user_name" class="form-control login-input" value="{{ old('user_name') }}" placeholder="Enter username" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control login-input" placeholder="Enter password" required>
                    </div>
                    <button type="submit" class="btn btn-signin" id="loginBtn">Sign In</button>
                </form>
                <script>
                document.getElementById('loginForm')?.addEventListener('submit', function() {
                    var btn = document.getElementById('loginBtn');
                    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Signing in...'; }
                });
                </script>
                <a href="#" class="forgot-link">Forgot password?</a>
            </div>
        </div>

        <div class="login-footer">
            <div class="d-flex flex-wrap align-items-end gap-4">
                <div class="footer-sections">
                    <div class="footer-section">
                        <h6>Vision Statement</h6>
                        <p>To become your trusted partner in your financial journey.</p>
                    </div>
                    <div class="footer-section">
                        <h6>Mission Statement</h6>
                        <p>Securing your financial future.</p>
                    </div>
                    <div class="footer-section">
                        <h6>Core Values</h6>
                        <p class="core-values">
                            Trustworthy · Empathy · Authentic · Agile
                        </p>
                    </div>
                </div>
                <div class="social-icons">
                    <a href="#" class="social-icon" title="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="social-icon" title="Instagram"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="social-icon" title="Twitter"><i class="bi bi-twitter"></i></a>
                </div>
            </div>
            <div class="slogan-corner d-none d-lg-flex">
                <span class="slogan-text">MAKE TODAY COUNT;<br>DELIGHT A CUSTOMER</span>
            </div>
        </div>
    </div>
</body>
</html>
