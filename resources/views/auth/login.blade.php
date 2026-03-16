<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In — Geminia Life</title>
    <link rel="icon" type="image/png" href="{{ asset('images/geminia-logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --gem-primary: #1A468A;
            --gem-primary-dark: #133A6F;
            --gem-accent: #33B4E3;
            --gem-light: #33B4E3;
            --gem-bg: #f8fafc;
            --gem-border: #D0D1D2;
        }
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100%; height: 100%; overflow-x: hidden; font-family: 'Plus Jakarta Sans', sans-serif; -webkit-font-smoothing: antialiased; }

        /* Animated mesh background - Geminia brand colors */
        body {
            position: relative;
            background: linear-gradient(135deg, #e0f2fa 0%, #bae6fd 20%, #e8f4fc 40%, #d4ebf7 60%, #e0f2fa 80%, #f0f9ff 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }
        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        .login-bg-orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.65;
            pointer-events: none;
            z-index: 0;
        }
        .login-bg-orb-1 { width: 400px; height: 400px; background: linear-gradient(135deg, rgba(51, 180, 227, 0.35) 0%, rgba(26, 70, 138, 0.22) 100%); top: -100px; right: -50px; animation: float1 20s ease-in-out infinite; }
        .login-bg-orb-2 { width: 300px; height: 300px; background: linear-gradient(135deg, rgba(26, 70, 138, 0.22) 0%, rgba(51, 180, 227, 0.2) 100%); bottom: -50px; left: -80px; animation: float2 18s ease-in-out infinite; }
        .login-bg-orb-3 { width: 200px; height: 200px; background: rgba(51, 180, 227, 0.18); top: 50%; left: 30%; animation: float3 22s ease-in-out infinite; }
        @keyframes float1 { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(-30px, 20px) scale(1.05); } }
        @keyframes float2 { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(20px, -25px) scale(1.08); } }
        @keyframes float3 { 0%, 100% { transform: translate(0, 0); } 50% { transform: translate(15px, 15px); } }

        /* Dot grid overlay */
        .login-dot-grid {
            position: fixed;
            inset: 0;
            background-image: radial-gradient(rgba(26, 70, 138, 0.12) 1px, transparent 1px);
            background-size: 24px 24px;
            pointer-events: none;
            z-index: 0;
        }

        .login-wrap {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr;
        }
        @media (min-width: 992px) {
            .login-wrap { grid-template-columns: 1.1fr 0.9fr; }
        }

        /* Left: Sign-in form - premium card */
        .login-form-panel {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2.5rem 2rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(232, 244, 252, 0.5) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.5);
            border-left: 4px solid transparent;
            background-image: linear-gradient(white, rgba(232, 244, 252, 0.5)), linear-gradient(180deg, var(--gem-accent), var(--gem-primary));
            background-origin: border-box;
            background-clip: padding-box, border-box;
        }
        @media (min-width: 992px) {
            .login-form-panel {
                padding: 3.5rem 4.5rem;
                box-shadow: 8px 0 48px rgba(26, 70, 138, 0.08), -1px 0 0 rgba(255, 255, 255, 0.8) inset;
            }
        }

        /* Staggered entrance */
        .login-anim { opacity: 0; animation: loginFadeUp 0.6s ease forwards; }
        .login-anim-1 { animation-delay: 0.1s; }
        .login-anim-2 { animation-delay: 0.2s; }
        .login-anim-3 { animation-delay: 0.3s; }
        .login-anim-4 { animation-delay: 0.4s; }
        .login-anim-5 { animation-delay: 0.5s; }
        .login-anim-6 { animation-delay: 0.6s; }
        .login-anim-7 { animation-delay: 0.7s; }
        .login-anim-8 { animation-delay: 0.8s; }
        @keyframes loginFadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-logo {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: flex-start;
            gap: 0.65rem;
            padding-bottom: 1.75rem;
            margin-bottom: 1.75rem;
            border-bottom: 1px solid rgba(26, 70, 138, 0.1);
        }
        .login-logo-img {
            width: 64px;
            height: 64px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-logo-img img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .login-logo-text { text-align: left; }
        .login-logo-text h1 { font-size: 1.65rem; font-weight: 800; color: var(--gem-primary); margin: 0; letter-spacing: 0.04em; line-height: 1.2; text-rendering: optimizeLegibility; -webkit-font-smoothing: antialiased; }
        .login-logo-text span { font-size: 0.75rem; font-weight: 600; color: var(--gem-primary); opacity: 0.9; letter-spacing: 0.1em; display: block; margin-top: 0.2rem; -webkit-font-smoothing: antialiased; }

        .login-title {
            font-size: 1.85rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.25rem;
            letter-spacing: -0.02em;
        }
        .login-title span {
            background: linear-gradient(135deg, var(--gem-primary) 0%, var(--gem-accent) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .login-subtitle {
            font-size: 0.925rem;
            color: #5b7a9e;
            margin-bottom: 2rem;
            padding-left: 1rem;
            margin-left: 0;
            border-left: 3px solid;
            border-image: linear-gradient(180deg, var(--gem-accent), var(--gem-primary)) 1;
        }

        .form-label { font-weight: 600; color: #334155; font-size: 0.9rem; margin-bottom: 0.5rem; }
        .login-input {
            border: 1.5px solid var(--gem-border);
            border-radius: 12px;
            padding: 0.9rem 1.1rem;
            font-size: 1rem;
            width: 100%;
            transition: all 0.25s ease;
            background: #fff;
        }
        .login-input:hover { border-color: rgba(51, 180, 227, 0.5); background: rgba(232, 244, 252, 0.4); }
        .login-input:focus {
            outline: none;
            border-color: var(--gem-primary);
            box-shadow: 0 0 0 4px rgba(51, 180, 227, 0.12);
        }
        .login-input::placeholder { color: #94a3b8; }

        /* Premium sign-in button - Geminia navy + sky blue accent */
        .btn-signin {
            background: linear-gradient(135deg, var(--gem-accent) 0%, var(--gem-primary) 35%, #25549e 70%, var(--gem-primary-dark) 100%);
            background-size: 200% 100%;
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.05em;
            color: #fff;
            width: 100%;
            margin-top: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px rgba(26, 70, 138, 0.4);
            position: relative;
            overflow: hidden;
        }
        .btn-signin::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        .btn-signin:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 70, 138, 0.5);
            background-position: 100% 0;
        }
        .btn-signin:hover::before { left: 100%; }
        .btn-signin:active { transform: translateY(0); }

        .forgot-link {
            display: block;
            text-align: left;
            margin-top: 1.25rem;
            padding: 0.5rem 0;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            color: var(--gem-primary);
            transition: all 0.25s ease;
        }
        .forgot-link:hover { color: var(--gem-primary-dark); text-decoration: underline; text-underline-offset: 4px; }

        .btn-signup-cta {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 1.1rem;
            border-radius: 999px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #fff !important;
            text-decoration: none !important;
            background: linear-gradient(135deg, var(--gem-accent) 0%, var(--gem-primary) 100%);
            box-shadow: 0 3px 14px rgba(26, 70, 138, 0.35);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .btn-signup-cta::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.25), transparent);
            transform: translateX(-100%);
            transition: transform 0.5s ease;
        }
        .btn-signup-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 70, 138, 0.45);
            background: linear-gradient(135deg, #4fc3f7 0%, var(--gem-primary) 100%);
        }
        .btn-signup-cta:hover::before { transform: translateX(100%); }

        /* Right: Promo panel */
        .login-promo-panel {
            display: none;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 2.5rem;
            background: linear-gradient(165deg, rgba(248, 250, 252, 0.9) 0%, rgba(241, 245, 249, 0.95) 100%);
            backdrop-filter: blur(12px);
            border-left: 1px solid rgba(255, 255, 255, 0.6);
        }
        @media (min-width: 992px) {
            .login-promo-panel { display: flex; }
        }

        /* Promo slider */
        .login-promo-slider {
            position: relative;
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
            overflow: hidden;
        }
        .login-promo-slides {
            position: relative;
            min-height: 380px;
        }
        .login-promo-slide {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            opacity: 0;
            visibility: hidden;
            transform: translateX(24px);
            transition: opacity 0.5s ease, transform 0.5s ease, visibility 0.5s;
        }
        .login-promo-slide.active {
            position: relative;
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
        }
        .login-promo-slide.prev-out {
            transform: translateX(-24px);
            opacity: 0;
        }

        .login-promo-illus {
            width: 100%;
            max-width: 320px;
            margin: 0 auto 1.5rem;
            position: relative;
        }
        .login-laptop-mock {
            animation: loginFloat 6s ease-in-out infinite;
        }
        @keyframes loginFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        .login-laptop-frame {
            background: linear-gradient(180deg, #e2e8f0 0%, #cbd5e1 100%);
            border: 3px solid #94a3b8;
            border-radius: 14px 14px 6px 6px;
            padding: 10px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0, 0, 0, 0.04);
        }
        .login-laptop-screen {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 8px;
            padding: 1.75rem;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }
        .login-laptop-screen .email-placeholder {
            font-size: 0.8rem;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .login-laptop-screen .fingerprint {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, rgba(51, 180, 227, 0.15) 0%, rgba(26, 70, 138, 0.08) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.85rem;
            color: var(--gem-primary);
            animation: fingerprintPulse 2.5s ease-in-out infinite;
        }
        @keyframes fingerprintPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(51, 180, 227, 0.25); }
            50% { box-shadow: 0 0 0 12px rgba(51, 180, 227, 0); }
        }
        .login-laptop-base {
            height: 14px;
            background: linear-gradient(180deg, #94a3b8 0%, #64748b 100%);
            border-radius: 0 0 8px 8px;
            margin-top: 6px;
        }

        .login-promo h2 {
            font-size: 1.7rem;
            font-weight: 700;
            margin: 0 0 1rem;
            line-height: 1.3;
            color: #0f172a;
            letter-spacing: -0.02em;
        }
        .login-promo p {
            font-size: 0.975rem;
            line-height: 1.65;
            color: #64748b;
            margin: 0 0 1.25rem;
            max-width: 360px;
        }
        .login-promo a {
            color: var(--gem-primary);
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        .login-promo a:hover {
            color: var(--gem-primary-dark);
            gap: 0.75rem;
        }

        .login-secure {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #64748b;
            margin-top: 1.5rem;
            padding: 0.5rem 1.1rem;
            background: linear-gradient(135deg, rgba(51, 180, 227, 0.1) 0%, rgba(26, 70, 138, 0.05) 100%);
            border-radius: 999px;
            border: 1px solid rgba(51, 180, 227, 0.25);
            box-shadow: 0 2px 8px rgba(26, 70, 138, 0.04);
        }
        .login-secure i { color: var(--gem-accent); font-size: 1rem; }
        .login-secure span { color: #475569; }
        .btn-signup-cta { position: relative; overflow: hidden; }
        .btn-signup-cta::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(105deg, transparent 40%, rgba(255,255,255,0.4) 50%, transparent 60%);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }
        .btn-signup-cta:hover::after { transform: translateX(100%); }

        /* Promo slider */
        .login-promo-slider {
            position: relative;
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-promo-slides {
            position: relative;
            flex: 1;
            min-height: 320px;
        }
        .login-promo-slide {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transform: translateX(24px);
            transition: opacity 0.6s ease, transform 0.6s ease, visibility 0.6s;
        }
        .login-promo-slide.active {
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
            position: relative;
        }
        .login-promo-slide .login-promo-illus {
            margin-bottom: 1.5rem;
        }
        .login-promo-slider-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        .login-promo-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(148, 163, 184, 0.4);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0;
        }
        .login-promo-dot:hover { background: rgba(51, 180, 227, 0.5); transform: scale(1.15); }
        .login-promo-dot.active {
            background: linear-gradient(135deg, var(--gem-accent), var(--gem-primary));
            width: 28px;
            border-radius: 999px;
            box-shadow: 0 2px 8px rgba(26, 70, 138, 0.3);
        }
        .login-promo-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(51, 180, 227, 0.25);
            color: var(--gem-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 12px rgba(26, 70, 138, 0.1);
            z-index: 2;
        }
        .login-promo-arrow:hover { background: #fff; border-color: var(--gem-accent); color: var(--gem-accent); box-shadow: 0 6px 20px rgba(26, 70, 138, 0.2); transform: translateY(-50%) scale(1.08); }
        .login-promo-arrow.prev { left: 0.5rem; }
        .login-promo-arrow.next { right: 0.5rem; }
        .login-promo-slide-icon {
            width: 100px;
            height: 100px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(51, 180, 227, 0.15) 0%, rgba(26, 70, 138, 0.08) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.75rem;
            color: var(--gem-primary);
            margin-bottom: 1.5rem;
            animation: slideIconFloat 4s ease-in-out infinite;
        }
        .login-promo-slide:nth-child(2) .login-promo-slide-icon { animation-delay: 0.5s; }
        .login-promo-slide:nth-child(3) .login-promo-slide-icon { animation-delay: 1s; }
        @keyframes slideIconFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
        .login-promo-appstores {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        .login-promo-appstores a {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: rgba(148, 163, 184, 0.15);
            border: 1px solid rgba(148, 163, 184, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            text-decoration: none;
            transition: all 0.25s ease;
        }
        .login-promo-appstores a:hover { background: rgba(51, 180, 227, 0.12); border-color: rgba(51, 180, 227, 0.3); color: var(--gem-primary); }
        .login-promo-appstores i { font-size: 1.25rem; }

        .btn-smart {
            font-size: 0.8rem;
            font-weight: 600;
            border-radius: 999px;
            padding: 0.4rem 1rem;
            border: 1.5px solid var(--gem-primary);
            color: var(--gem-primary);
            background: rgba(51, 180, 227, 0.08);
            transition: all 0.25s;
            text-decoration: none;
        }
        .btn-smart:hover { background: rgba(26, 70, 138, 0.12); color: var(--gem-primary-dark); transform: scale(1.02); border-color: var(--gem-primary); }

        @media (prefers-reduced-motion: reduce) {
            body, .login-bg-orb-1, .login-bg-orb-2, .login-bg-orb-3, .login-laptop-mock, .login-laptop-screen .fingerprint { animation: none !important; }
            .login-anim { animation: none !important; opacity: 1; }
            .login-promo-panel .login-promo-illus, .login-promo-panel .login-promo { opacity: 1; animation: none; }
        }

        .alert-danger { border-radius: 12px; border: none; }

        /* Promo slider */
        .login-promo-slider { position: relative; overflow: hidden; flex: 1; display: flex; flex-direction: column; justify-content: center; }
        .login-promo-slides { position: relative; min-height: 420px; }
        .login-promo-slide {
            position: absolute;
            inset: 0;
            opacity: 0;
            visibility: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 1rem;
            transition: opacity 0.6s ease, visibility 0.6s ease;
        }
        .login-promo-slide.active {
            position: relative;
            opacity: 1;
            visibility: visible;
        }
        .login-promo-slide .login-promo-illus { margin-bottom: 1.5rem; }
        .login-promo-slide .login-promo { max-width: 340px; }
        .login-promo-slide .login-promo h2 { font-size: 1.6rem; margin-bottom: 0.75rem; }
        .login-promo-slide .login-promo p { font-size: 0.9rem; margin-bottom: 1rem; }
        .login-promo-slider-dots {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        .login-promo-slider-dots button {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            border: none;
            background: rgba(148, 163, 184, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .login-promo-slider-dots button:hover { background: rgba(51, 180, 227, 0.5); }
        .login-promo-slider-dots button.active {
            width: 24px;
            border-radius: 4px;
            background: linear-gradient(90deg, var(--gem-accent), var(--gem-primary));
        }
        .login-promo-slider-arrows {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 100%;
            display: flex;
            justify-content: space-between;
            padding: 0 0.5rem;
            pointer-events: none;
        }
        .login-promo-slider-arrows button {
            pointer-events: auto;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid rgba(51, 180, 227, 0.3);
            background: rgba(255,255,255,0.9);
            color: var(--gem-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 2px 12px rgba(26, 70, 138, 0.1);
        }
        .login-promo-slider-arrows button:hover {
            background: rgba(51, 180, 227, 0.1);
            border-color: var(--gem-accent);
            transform: scale(1.08);
        }
        .login-promo-app-icons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        .login-promo-app-icons a {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: rgba(255,255,255,0.8);
            border: 1px solid rgba(148, 163, 184, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 1.25rem;
            text-decoration: none;
            transition: all 0.25s ease;
        }
        .login-promo-app-icons a:hover {
            background: #fff;
            color: var(--gem-primary);
            border-color: rgba(51, 180, 227, 0.3);
            transform: translateY(-2px);
        }

        /* Secure badge soft pulse */
        .login-secure { box-shadow: 0 0 0 0 rgba(51, 180, 227, 0.2); animation: secureGlow 3s ease-in-out infinite; }
        @keyframes secureGlow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(51, 180, 227, 0.15); }
            50% { box-shadow: 0 0 16px 2px rgba(51, 180, 227, 0.12); }
        }

        /* Promo slider */
        .login-promo-slider {
            position: relative;
            overflow: hidden;
            width: 100%;
            height: 100%;
            min-height: 420px;
        }
        .login-promo-slides {
            position: relative;
            height: 100%;
        }
        .login-promo-slide {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 0.5rem;
            opacity: 0;
            visibility: hidden;
            transform: translateX(24px);
            transition: opacity 0.5s ease, transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1), visibility 0.5s;
        }
        .login-promo-slide.active {
            position: relative;
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
        }
        .login-promo-slide-illus {
            width: 100%;
            max-width: 320px;
            margin: 0 auto 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 180px;
        }
        .login-promo-slide-illus .slide-icon {
            width: 100px;
            height: 100px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(51, 180, 227, 0.2) 0%, rgba(26, 70, 138, 0.12) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.75rem;
            color: var(--gem-primary);
            animation: slideIconFloat 4s ease-in-out infinite;
        }
        @keyframes slideIconFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }
        .login-promo-slider-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.75rem;
        }
        .login-promo-slider-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(148, 163, 184, 0.4);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .login-promo-slider-dot:hover { background: rgba(51, 180, 227, 0.5); transform: scale(1.2); }
        .login-promo-slider-dot.active {
            background: linear-gradient(135deg, var(--gem-accent), var(--gem-primary));
            width: 28px;
            border-radius: 5px;
        }
        .login-promo-slider-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(51, 180, 227, 0.25);
            color: var(--gem-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            cursor: pointer;
            transition: all 0.25s ease;
            z-index: 2;
            box-shadow: 0 2px 12px rgba(26, 70, 138, 0.1);
        }
        .login-promo-slider-arrow:hover {
            background: var(--gem-primary);
            color: #fff;
            box-shadow: 0 4px 16px rgba(26, 70, 138, 0.25);
        }
        .login-promo-slider-arrow.prev { left: 0.5rem; }
        .login-promo-slider-arrow.next { right: 0.5rem; }
        .login-promo-apps {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
        }
        .login-promo-apps a {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid rgba(148, 163, 184, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 1.4rem;
            transition: all 0.25s ease;
        }
        .login-promo-apps a:hover { background: rgba(51, 180, 227, 0.1); color: var(--gem-primary); border-color: var(--gem-accent); transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="login-bg-orb login-bg-orb-1"></div>
    <div class="login-bg-orb login-bg-orb-2"></div>
    <div class="login-bg-orb login-bg-orb-3"></div>
    <div class="login-dot-grid"></div>

    <div class="login-wrap">
        <div class="login-form-panel">
            <div class="login-logo login-anim login-anim-1">
                <div class="login-logo-img">
                    <img src="{{ asset('images/geminia-logo.png') }}" alt="Geminia Life" width="64" height="64">
                </div>
                <div class="login-logo-text">
                    <h1>GEMINIA LIFE</h1>
                    <span>LIFE ASSURANCE · CRM</span>
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2 mb-2 login-anim login-anim-2">
                <h2 class="login-title mb-0">Sign in to access <span>CRM</span></h2>
                <a href="#" class="btn-smart"><i class="bi bi-stars me-1"></i>Try smart sign-in</a>
            </div>
            <p class="login-subtitle login-anim login-anim-3">Enter your credentials to continue</p>

            @if ($errors->any())
                <div class="alert alert-danger py-3 mb-3">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    @foreach ($errors->all() as $error) {{ $error }} @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" id="loginForm">
                @csrf
                <div class="mb-3 login-anim login-anim-4">
                    <label class="form-label">Username</label>
                    <input type="text" name="user_name" class="form-control login-input" value="{{ old('user_name') }}" placeholder="Enter your username" required autofocus>
                </div>
                <div class="mb-3 login-anim login-anim-5">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control login-input" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn btn-signin login-anim login-anim-6" id="loginBtn">Sign In</button>
            </form>

            <a href="{{ route('password.request') }}" class="forgot-link login-anim login-anim-7">Forgot your password?</a>

            <div class="login-secure login-anim login-anim-8">
                <i class="bi bi-shield-lock-fill"></i>
                <span>Secure login</span>
            </div>
        </div>

        <div class="login-promo-panel">
            <div class="login-promo-slider" id="loginPromoSlider">
                <button type="button" class="login-promo-slider-arrow prev" aria-label="Previous slide"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="login-promo-slider-arrow next" aria-label="Next slide"><i class="bi bi-chevron-right"></i></button>
                <div class="login-promo-slides">
                    <div class="login-promo-slide active" data-slide="0">
                        <div class="login-promo-illus">
                            <div class="login-laptop-mock">
                                <div class="login-laptop-frame">
                                    <div class="login-laptop-screen">
                                        <div class="email-placeholder"><i class="bi bi-lock-fill"></i> user@geminia.com</div>
                                        <div class="fingerprint"><i class="bi bi-fingerprint"></i></div>
                                    </div>
                                </div>
                                <div class="login-laptop-base"></div>
                            </div>
                        </div>
                        <div class="login-promo">
                            <h2>Passwordless sign-in</h2>
                            <p>Move away from risky passwords and experience one-tap access to Geminia Life. Download and install OneAuth for a seamless, secure login.</p>
                            <a href="#">Learn more <i class="bi bi-arrow-right"></i></a>
                        </div>
                        <div class="login-promo-apps">
                            <a href="#" title="App Store"><i class="bi bi-apple"></i></a>
                            <a href="#" title="Google Play"><i class="bi bi-google-play"></i></a>
                        </div>
                    </div>
                    <div class="login-promo-slide" data-slide="1">
                        <div class="login-promo-slide-illus">
                            <div class="slide-icon"><i class="bi bi-people-fill"></i></div>
                        </div>
                        <div class="login-promo">
                            <h2>Manage clients & policies</h2>
                            <p>Keep all your clients, policies, and conversations in one place. Track interactions and never miss a follow-up.</p>
                        </div>
                    </div>
                    <div class="login-promo-slide" data-slide="2">
                        <div class="login-promo-slide-illus">
                            <div class="slide-icon"><i class="bi bi-shield-check"></i></div>
                        </div>
                        <div class="login-promo">
                            <h2>Secure by design</h2>
                            <p>Enterprise-grade security protects your data. Geminia Life is built for insurance professionals who demand reliability.</p>
                        </div>
                    </div>
                </div>
                <div class="login-promo-slider-nav">
                    <button type="button" class="login-promo-slider-dot active" data-slide="0" aria-label="Slide 1"></button>
                    <button type="button" class="login-promo-slider-dot" data-slide="1" aria-label="Slide 2"></button>
                    <button type="button" class="login-promo-slider-dot" data-slide="2" aria-label="Slide 3"></button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('loginForm')?.addEventListener('submit', function() {
        var btn = document.getElementById('loginBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Signing in...'; }
    });

    (function() {
        var slider = document.getElementById('loginPromoSlider');
        if (!slider) return;
        var slides = slider.querySelectorAll('.login-promo-slide');
        var dots = slider.querySelectorAll('.login-promo-slider-dot');
        var prevBtn = slider.querySelector('.login-promo-slider-arrow.prev');
        var nextBtn = slider.querySelector('.login-promo-slider-arrow.next');
        var total = slides.length;
        var current = 0;
        var autoInterval;

        function goTo(n) {
            current = (n + total) % total;
            slides.forEach(function(s, i) { s.classList.toggle('active', i === current); });
            dots.forEach(function(d, i) { d.classList.toggle('active', i === current); });
        }

        function next() { goTo(current + 1); }
        function prev() { goTo(current - 1); }

        dots.forEach(function(dot, i) {
            dot.addEventListener('click', function() { goTo(i); resetAuto(); });
        });
        if (prevBtn) prevBtn.addEventListener('click', function() { prev(); resetAuto(); });
        if (nextBtn) nextBtn.addEventListener('click', function() { next(); resetAuto(); });

        function startAuto() {
            autoInterval = setInterval(next, 5000);
        }
        function resetAuto() {
            clearInterval(autoInterval);
            startAuto();
        }
        slider.addEventListener('mouseenter', function() { clearInterval(autoInterval); });
        slider.addEventListener('mouseleave', startAuto);
        startAuto();
    })();
    </script>
</body>
</html>
