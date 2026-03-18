<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — Geminia Life</title>
    <link rel="icon" type="image/png" href="{{ asset('images/geminia-logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preload" href="{{ asset('images/login-hero.jpg') }}" as="image">
    <style>
        :root {
            --color-dark-blue: #0033a0;
            --color-light-blue: #2CA8FF;
            --color-gray: #6c757d;
            /* Aliases for compatibility */
            --gem-dark-blue: #0033a0;
            --gem-light-blue: #2CA8FF;
            --gem-gray: #6c757d;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
            font-family: 'Poppins', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* Full-page background - no scroll */
        .login-page {
            height: 100vh;
            max-height: 100dvh;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .login-bg-img {
            position: absolute;
            inset: 0;
            z-index: 0;
        }
        .login-bg-img img {
            width: 100%;
            height: 100%;
            min-width: 100%;
            min-height: 100%;
            object-fit: cover;
            object-position: center center;
            image-rendering: auto;
            image-rendering: -webkit-optimize-contrast;
            transform: translateZ(0);
            backface-visibility: hidden;
            will-change: transform;
            display: block;
        }
        .login-page::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.4) 0%, rgba(248, 250, 252, 0.18) 35%, transparent 60%);
            pointer-events: none;
            z-index: 1;
        }
        .login-left {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            padding: 1.5rem 2rem 1.5rem 3rem;
            position: relative;
            z-index: 1;
            max-width: 520px;
            min-height: 0;
        }
        @media (max-width: 768px) {
            .login-left { padding: 1rem 1.25rem; max-width: 100%; }
        }
        .login-left-inner {
            width: 100%;
            max-width: 420px;
            flex-shrink: 0;
        }
        /* Two-tone tagline overlay: dark blue + light blue (per brand) */
        .login-tagline {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 360px;
            height: 220px;
            z-index: 2;
        }
        .login-tagline-shape {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 0 200px 340px;
            border-color: transparent transparent #0033a0 transparent;
        }
        .login-tagline-shape-accent {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 0 140px 240px;
            border-color: transparent transparent #2CA8FF transparent;
        }
        .login-tagline span {
            position: absolute;
            bottom: 48px;
            right: 24px;
            max-width: 220px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            line-height: 1.5;
            color: #fff;
            text-align: right;
            display: block;
        }

        .login-card {
            position: relative;
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(12px);
            border-radius: 24px;
            box-shadow:
                0 1px 2px rgba(15, 39, 68, 0.04),
                0 24px 56px -12px rgba(15, 39, 68, 0.14),
                0 0 0 1px rgba(255, 255, 255, 0.5);
            padding: 2.75rem 2.25rem;
            animation: cardFade 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes cardFade {
            from { opacity: 0; transform: translateY(16px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1.75rem;
            border-bottom: 1px solid rgba(0, 51, 160, 0.12);
        }
        .login-brand-img {
            width: 52px;
            height: 52px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-brand-img img { width: 100%; height: 100%; object-fit: contain; }
        .login-brand-text h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--color-dark-blue);
            margin: 0;
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .login-brand-text span {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--color-gray);
            letter-spacing: 0.04em;
        }

        .login-heading {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0 0 0.25rem;
            letter-spacing: -0.03em;
        }
        .login-sub {
            font-size: 0.9rem;
            color: var(--color-gray);
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--color-dark-blue);
            letter-spacing: 0.04em;
            margin-bottom: 0.5rem;
            display: block;
        }
        .login-input {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            color: #1a1a1a;
            background: rgba(0, 51, 160, 0.04);
            border: 1px solid rgba(0, 51, 160, 0.18);
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        .login-input:hover {
            background: rgba(0, 51, 160, 0.06);
            border-color: rgba(0, 51, 160, 0.3);
        }
        .login-input:focus {
            outline: none;
            border-color: var(--color-dark-blue);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(0, 51, 160, 0.12);
        }
        .login-input::placeholder { color: var(--color-gray); opacity: 0.65; }

        .btn-signin {
            width: 100%;
            padding: 1rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: #fff;
            background: linear-gradient(135deg, var(--color-dark-blue) 0%, #0044bb 100%);
            border: none;
            border-radius: 14px;
            margin-top: 1.5rem;
            transition: all 0.25s ease;
            box-shadow: 0 4px 20px rgba(0, 51, 160, 0.35);
        }
        .btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(0, 51, 160, 0.45);
        }
        .btn-signin:active { transform: translateY(0); }

        .login-card-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(0, 51, 160, 0.08);
        }
        .login-forgot {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--color-light-blue);
            text-decoration: none;
            transition: color 0.2s;
        }
        .login-forgot:hover { color: #1e90ff; }

        .login-secure {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            color: #5a6c7d;
            font-weight: 500;
            background: rgba(0, 51, 160, 0.06);
            border-radius: 999px;
        }
        .login-secure i { color: var(--color-light-blue); font-size: 1rem; }

        .alert-danger {
            border-radius: 12px;
            border: 1px solid rgba(185, 28, 28, 0.2);
            background: rgba(185, 28, 28, 0.08);
            color: #991b1b;
            font-weight: 500;
        }

        /* Vision, Mission, Core Values - below login form */
        .login-statements {
            margin-top: 1.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
            max-width: 420px;
            margin-left: auto;
            margin-right: auto;
        }
        @media (max-width: 600px) {
            .login-statements {
                grid-template-columns: 1fr;
                gap: 1.25rem;
            }
        }
        .login-statements .stmt {
            padding: 1rem 0;
            text-align: center;
            border-right: 1px solid rgba(108, 117, 125, 0.2);
        }
        .login-statements .stmt:last-child { border-right: none; }
        @media (max-width: 600px) {
            .login-statements .stmt {
                border-right: none;
                border-bottom: 1px solid rgba(108, 117, 125, 0.2);
                padding-bottom: 1rem;
            }
            .login-statements .stmt:last-child { border-bottom: none; }
        }
        .login-statements .stmt h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--color-dark-blue);
            margin: 0 0 0.5rem;
            letter-spacing: 0.02em;
        }
        .login-statements .stmt p,
        .login-statements .stmt ul {
            font-size: 0.82rem;
            color: #5a6c7d;
            font-weight: 500;
            line-height: 1.55;
            margin: 0;
        }
        .login-statements .stmt ul {
            list-style: none;
            padding: 0;
            text-align: left;
            display: inline-block;
        }
        .login-statements .stmt ul li::before {
            content: '•';
            color: var(--color-light-blue);
            font-weight: 700;
            margin-right: 0.5rem;
        }

        /* Social icons */
        .login-social {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.25rem;
        }
        .login-social a {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--color-dark-blue);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 1.2rem;
            transition: all 0.25s;
            box-shadow: 0 2px 8px rgba(0, 51, 160, 0.25);
        }
        .login-social a:hover {
            background: var(--color-light-blue);
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0, 51, 160, 0.35);
        }

        .login-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 0.6rem 1.5rem;
            background: rgba(255, 255, 255, 0.95);
            font-family: 'Poppins', sans-serif;
            font-size: 0.75rem;
            color: var(--color-gray);
            z-index: 3;
            border-top: 1px solid rgba(108, 117, 125, 0.2);
            text-align: center;
        }
        .login-footer a { color: var(--color-dark-blue); text-decoration: none; }
        .login-footer a:hover { color: var(--color-light-blue); }

        @media (prefers-reduced-motion: reduce) {
            .login-card { animation: none; }
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-bg-img">
            <img src="{{ asset('images/login-hero.jpg') }}?v=3" alt="" width="1920" height="1080" loading="eager" fetchpriority="high">
        </div>
        <div class="login-left">
            <div class="login-left-inner">
                <div class="login-card">
                    <div class="login-brand">
                        <div class="login-brand-img">
                            <img src="{{ asset('images/geminia-logo.png') }}" alt="Geminia Life" width="52" height="52">
                        </div>
                        <div class="login-brand-text">
                            <h1>GEMINIA</h1>
                            <span>LIFE INSURANCE</span>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger py-3 mb-3">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            @foreach ($errors->all() as $error) {{ $error }} @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" id="loginForm">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label" for="user_name">Username</label>
                            <input type="text" name="user_name" id="user_name" class="form-control login-input" value="{{ old('user_name') }}" placeholder="Enter your username" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input type="password" name="password" id="password" class="form-control login-input" placeholder="Enter your password" required>
                        </div>
                        <button type="submit" class="btn btn-signin" id="loginBtn">Sign in</button>
                    </form>

                    <div class="login-card-footer">
                        <a href="{{ route('password.request') }}" class="login-forgot">Forgot password?</a>
                        <div class="login-secure">
                            <i class="bi bi-shield-lock-fill"></i>
                            <span>Secure login</span>
                        </div>
                    </div>
                </div>

                <div class="login-statements">
                    <div class="stmt">
                        <h4>Vision Statement</h4>
                        <p>To become your trusted partner in your financial journey.</p>
                    </div>
                    <div class="stmt">
                        <h4>Mission Statement</h4>
                        <p>Securing your financial future.</p>
                    </div>
                    <div class="stmt">
                        <h4>Core Values</h4>
                        <ul>
                            <li>Trustworthy</li>
                            <li>Empathy</li>
                            <li>Authentic</li>
                            <li>Agile</li>
                        </ul>
                    </div>
                </div>

                <div class="login-social">
                    <a href="https://facebook.com/geminialife" target="_blank" rel="noopener" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="https://instagram.com/geminialife" target="_blank" rel="noopener" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                    <a href="https://twitter.com/geminialife" target="_blank" rel="noopener" aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
                </div>
            </div>
        </div>

        <div class="login-tagline">
            <div class="login-tagline-shape"></div>
            <div class="login-tagline-shape-accent"></div>
            <span>MAKE TODAY COUNT;<br>DELIGHT A CUSTOMER</span>
        </div>

        <footer class="login-footer">
            Powered by Agile CraftSolutions © 2015 - 2026 Agile CraftSolutions | <a href="#">Privacy Policy</a>
        </footer>
    </div>

    <script>
    document.getElementById('loginForm')?.addEventListener('submit', function() {
        var btn = document.getElementById('loginBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Signing in...'; }
    });
    </script>
</body>
</html>
