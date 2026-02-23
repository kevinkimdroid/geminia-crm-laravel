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
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            margin: 0;
            overflow-x: hidden;
            background: url('{{ asset("images/login-bg.png") }}') no-repeat center center fixed;
            background-size: cover;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: linear-gradient(105deg,
                rgba(255,255,255,0.55) 0%,
                rgba(255,255,255,0.35) 30%,
                rgba(255,255,255,0.12) 55%,
                transparent 75%);
            pointer-events: none;
            z-index: 0;
        }
        .login-page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2rem 3rem;
        }
        .login-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15);
            padding: 2.5rem 2.5rem;
            max-width: 400px;
            margin-top: 2rem;
        }
        .login-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }
        .login-logo-icon {
            width: 48px;
            height: 48px;
            background: var(--geminia-primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.5rem;
        }
        .login-logo-text h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--geminia-primary);
            margin: 0;
            letter-spacing: 0.02em;
        }
        .login-logo-text span {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--geminia-light);
            letter-spacing: 0.1em;
        }
        .login-input {
            border: none;
            border-bottom: 2px solid #e2e8f0;
            border-radius: 0;
            padding: 0.75rem 0;
            font-size: 1rem;
            background: transparent;
        }
        .login-input:focus {
            outline: none;
            border-bottom-color: var(--geminia-primary);
            box-shadow: none;
        }
        .form-label {
            font-weight: 600;
            color: var(--geminia-primary);
            margin-bottom: 0.5rem;
        }
        .btn-signin {
            background: var(--geminia-primary);
            border: none;
            border-radius: 9999px;
            padding: 0.875rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            color: #fff;
            width: 100%;
            margin-top: 1rem;
        }
        .btn-signin:hover {
            background: var(--geminia-primary-dark);
            color: #fff;
        }
        .forgot-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--geminia-light);
            font-size: 0.9rem;
            text-decoration: none;
        }
        .forgot-link:hover {
            color: var(--geminia-primary);
        }
        .login-footer {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            justify-content: space-between;
            gap: 2rem;
            margin-top: auto;
            padding-bottom: 1rem;
            width: 100%;
        }
        .footer-sections {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }
        .footer-section {
            padding: 0 1.5rem;
            border-right: 1px solid rgba(26, 85, 158, 0.5);
        }
        .footer-section:first-child {
            padding-left: 0;
        }
        .footer-section:last-of-type {
            border-right: none;
            padding-right: 0;
        }
        .footer-section h6 {
            font-size: 0.75rem;
            font-weight: 700;
            font-style: italic;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--geminia-primary);
            margin-bottom: 0.35rem;
        }
        .footer-section p {
            font-size: 0.85rem;
            color: var(--geminia-primary);
            margin: 0;
            max-width: 200px;
        }
        .footer-section p.core-values {
            line-height: 1.6;
            color: var(--geminia-primary);
        }
        .social-icons {
            display: flex;
            gap: 0.5rem;
        }
        .social-icon {
            width: 40px;
            height: 40px;
            background: var(--geminia-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-decoration: none;
            font-size: 1rem;
        }
        .slogan-corner {
            position: fixed;
            bottom: 0;
            right: 0;
            width: 480px;
            height: 220px;
            background: url('{{ asset("images/login-slogan-bg.png") }}') no-repeat bottom right;
            background-size: contain;
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
            padding: 1.25rem 2rem;
            z-index: 2;
            pointer-events: none;
        }
        .slogan-text {
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.08em;
            line-height: 1.5;
            text-align: right;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        @media (max-width: 768px) {
            .login-page { padding: 1.5rem; }
            .login-footer { flex-direction: column; align-items: flex-start; }
            .footer-sections { flex-direction: column; }
            .footer-section { border-right: none; border-bottom: 1px solid rgba(26, 85, 158, 0.3); padding-bottom: 1rem; padding-right: 0; }
            .slogan-corner { width: 280px; height: 140px; padding: 0.75rem 1.25rem; }
            .slogan-text { font-size: 0.7rem; }
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div>
            <div class="login-card">
                <div class="login-logo">
                    <div class="login-logo-icon"><i class="bi bi-shield-check"></i></div>
                    <div class="login-logo-text">
                        <h1>GEMINIA</h1>
                        <span>LIFE INSURANCE</span>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger py-2 mb-3">
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
                            Trustworthy<br>
                            Empathy<br>
                            Authentic<br>
                            Agile
                        </p>
                    </div>
                </div>
                <div class="social-icons">
                    <a href="#" class="social-icon" title="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="#" class="social-icon" title="Instagram"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="social-icon" title="Twitter"><i class="bi bi-twitter"></i></a>
                </div>
            </div>
            <div class="slogan-corner d-none d-lg-block">
                <span class="slogan-text">MAKE TODAY COUNT;<br>DELIGHT A CUSTOMER</span>
            </div>
        </div>
    </div>
</body>
</html>
