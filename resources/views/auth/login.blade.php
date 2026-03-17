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
            min-height: 100%;
            font-family: 'Poppins', system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* Full-page background - object-fit prevents stretching, maintains aspect ratio */
        .login-page {
            min-height: 100vh;
            position: relative;
            overflow: hidden;
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
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.25) 0%, rgba(248, 250, 252, 0.12) 30%, transparent 55%);
            pointer-events: none;
            z-index: 1;
        }
        .login-left {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: flex-start;
            padding: 2.5rem 2rem 120px 3rem;
            position: relative;
            z-index: 1;
            max-width: 520px;
        }
        @media (max-width: 768px) {
            .login-left { padding: 1.5rem 1.25rem 100px 1.25rem; max-width: 100%; }
        }
        .login-left-inner {
            width: 100%;
            max-width: 420px;
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
            max-width: 400px;
            margin: 0 auto;
            background: #fff;
            border-radius: 24px;
            box-shadow:
                0 1px 2px rgba(15, 39, 68, 0.04),
                0 24px 48px -12px rgba(15, 39, 68, 0.12),
                0 0 0 1px rgba(15, 39, 68, 0.04);
            padding: 2.5rem 2rem;
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
            margin-bottom: 1.75rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(0, 51, 160, 0.15);
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
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--color-dark-blue);
            letter-spacing: 0.04em;
            margin-bottom: 0.4rem;
        }
        .login-input {
            border: none;
            border-bottom: 2px solid rgba(0, 51, 160, 0.2);
            border-radius: 0;
            padding: 0.6rem 0 0.75rem;
            font-size: 0.975rem;
            width: 100%;
            transition: border-color 0.2s ease;
            background: transparent;
        }
        .login-input:hover { border-bottom-color: rgba(0, 51, 160, 0.4); }
        .login-input:focus {
            outline: none;
            border-bottom-color: var(--color-dark-blue);
        }
        .login-input::placeholder { color: var(--color-gray); opacity: 0.6; }

        .btn-signin {
            width: 100%;
            padding: 0.95rem 1.5rem;
            font-size: 0.975rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            color: #fff;
            background: var(--color-dark-blue);
            border: none;
            border-radius: 12px;
            margin-top: 0.5rem;
            transition: all 0.25s ease;
            box-shadow: 0 4px 16px rgba(0, 51, 160, 0.35);
        }
        .btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 51, 160, 0.4);
        }
        .btn-signin { position: relative; overflow: hidden; }
        .btn-signin:active { transform: translateY(0); }

        .login-forgot {
            display: inline-block;
            margin-top: 1rem;
            font-size: 0.875rem;
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
            margin-top: 1.75rem;
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
            color: var(--color-gray);
            font-weight: 500;
            background: rgba(0, 51, 160, 0.06);
            border-radius: 999px;
        }
        .login-secure i { color: var(--color-light-blue); font-size: 0.9rem; }

        .alert-danger {
            border-radius: 14px;
            border: none;
            background: rgba(185, 28, 28, 0.06);
            color: #991b1b;
        }

        /* Vision, Mission, Core Values - below login form */
        .login-statements {
            margin-top: 2.5rem;
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            justify-content: center;
            gap: 0;
        }
        .login-statements .stmt {
            flex: 1;
            min-width: 120px;
            padding: 0 1rem;
            text-align: center;
        }
        .login-statements .stmt:not(:last-child) {
            border-right: 1px solid rgba(108, 117, 125, 0.3);
        }
        @media (max-width: 600px) {
            .login-statements .stmt:not(:last-child) { border-right: none; border-bottom: 1px solid rgba(108, 117, 125, 0.3); padding-bottom: 1rem; margin-bottom: 1rem; }
            .login-statements { flex-direction: column; }
        }
        .login-statements .stmt h4 {
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--color-dark-blue);
            margin: 0 0 0.35rem;
        }
        .login-statements .stmt p {
            font-size: 0.8rem;
            color: var(--color-gray);
            line-height: 1.5;
            margin: 0;
        }
        .login-statements .stmt ul {
            list-style: none;
            padding: 0;
            margin: 0;
            font-size: 0.8rem;
            color: var(--color-gray);
            line-height: 1.7;
        }
        .login-statements .stmt ul li::before {
            content: '•';
            color: var(--color-light-blue);
            font-weight: 700;
            margin-right: 0.4rem;
        }

        /* Social icons */
        .login-social {
            display: flex;
            justify-content: center;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        .login-social a {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--color-dark-blue);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.25s;
        }
        .login-social a:hover {
            background: var(--color-light-blue);
            transform: translateY(-2px);
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
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="user_name" class="form-control login-input" value="{{ old('user_name') }}" placeholder="Enter your username" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control login-input" placeholder="Enter your password" required>
                        </div>
                        <button type="submit" class="btn btn-signin" id="loginBtn">Sign in</button>
                    </form>

                    <a href="{{ route('password.request') }}" class="login-forgot">Forgot password?</a>

                    <div class="login-secure">
                        <i class="bi bi-shield-lock-fill"></i>
                        <span>Secure login</span>
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
