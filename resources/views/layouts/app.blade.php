<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <title>@yield('title', 'Dashboard') — Geminia Life</title>
    <link rel="icon" type="image/png" href="{{ asset('images/geminia-logo.png') }}">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
    <style>
        :root {
            --geminia-primary: #1A468A;
            --geminia-primary-dark: #133A6F;
            --geminia-accent: #33B4E3;
            --geminia-primary-light: rgba(51, 180, 227, 0.15);
            --geminia-primary-muted: rgba(51, 180, 227, 0.08);
            --geminia-sidebar: #1A468A;
            --geminia-sidebar-hover: rgba(255,255,255,0.1);
            --geminia-sidebar-active: rgba(255,255,255,0.2);
            --geminia-text: #1e293b;
            --geminia-text-muted: #64748b;
            --geminia-border: #e2e8f0;
            --geminia-bg: #f8fafc;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Plus Jakarta Sans', system-ui, sans-serif;
            background: linear-gradient(180deg, #f0f9ff 0%, var(--geminia-bg) 100%);
            min-height: 100vh;
            color: var(--geminia-text);
            -webkit-font-smoothing: antialiased;
        }
        .app-layout { display: flex; min-height: 100vh; }

        /* Sidebar */
        .app-sidebar {
            width: 260px;
            background: var(--geminia-sidebar);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }
        .app-sidebar-brand {
            padding: 1.25rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .app-sidebar-logo {
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 4px;
        }
        .app-sidebar-logo img { width: 100%; height: 100%; object-fit: contain; }
        .app-sidebar-title { font-weight: 700; font-size: 1.1rem; color: #fff; }
        .app-sidebar-sub { font-size: 0.7rem; color: rgba(255,255,255,0.7); }
        .app-sidebar-nav { flex: 1; overflow-y: auto; padding: 1rem 0.75rem; }
        .app-nav-group { margin-bottom: 1.5rem; }
        .app-nav-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.5);
            padding: 0 0.75rem 0.5rem;
        }
        .app-nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 2px;
            transition: background 0.15s, color 0.15s;
        }
        .app-nav-link:hover { background: var(--geminia-sidebar-hover); color: #fff; }
        .app-nav-link.active { background: var(--geminia-sidebar-active); color: #fff; }
        .app-nav-link i { font-size: 1.1rem; width: 22px; text-align: center; opacity: 0.9; }
        .app-nav-sublink { padding-left: 2.5rem; font-size: 0.85rem; }
        .app-sidebar-cta {
            margin: 1rem;
            padding: 0.75rem 1rem;
            background: #fff;
            color: var(--geminia-primary);
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .app-sidebar-cta:hover { background: #fff; color: var(--geminia-primary-dark); transform: translateY(-1px); }
        .app-nav-toggle { cursor: pointer; width: 100%; border: none; background: transparent; text-align: left; }
        .app-nav-toggle .bi-chevron-down { font-size: 0.7rem; transition: transform 0.2s; }
        .app-nav-toggle[aria-expanded="true"] .bi-chevron-down { transform: rotate(180deg); }

        /* Topbar */
        .app-topbar {
            height: 60px;
            background: #fff;
            border-bottom: 1px solid var(--geminia-border);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            gap: 1rem;
        }
        .app-topbar-search {
            flex: 1;
            max-width: 400px;
            position: relative;
        }
        .app-topbar-search input {
            width: 100%;
            height: 38px;
            padding: 0 1rem 0 2.5rem;
            border: 1px solid var(--geminia-border);
            border-radius: 8px;
            font-size: 0.9rem;
            background: var(--geminia-bg);
            transition: border-color 0.2s;
        }
        .app-topbar-search input:focus { outline: none; border-color: var(--geminia-primary); }
        .app-topbar-search input::placeholder { color: var(--geminia-text-muted); }
        .app-topbar-search i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--geminia-text-muted);
        }
        .app-topbar-actions { display: flex; align-items: center; gap: 0.5rem; }
        .app-topbar-btn {
            width: 38px;
            height: 38px;
            border: none;
            background: transparent;
            border-radius: 8px;
            color: var(--geminia-text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .app-topbar-btn:hover { background: var(--geminia-primary-muted); color: var(--geminia-primary); }
        .app-topbar-add {
            padding: 0.4rem 1.25rem;
            background: var(--geminia-primary);
            color: #fff !important;
            border-radius: 9999px;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: background 0.2s;
        }
        .app-topbar-add:hover { background: var(--geminia-primary-dark); color: #fff !important; }
        .app-topbar-add.dropdown-toggle::after { margin-left: 0.4rem; }
        .quick-create-menu .dropdown-header { font-size: 0.7rem; letter-spacing: 0.08em; }
        .quick-create-menu .dropdown-item { font-size: 0.9rem; }
        .quick-create-menu .dropdown-item:hover { background: var(--geminia-primary-muted); color: var(--geminia-primary); }
        .app-topbar-user {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.3rem 0.6rem 0.3rem 0.4rem;
            border-radius: 8px;
            cursor: pointer;
        }
        .app-topbar-user:hover { background: var(--geminia-primary-muted); }
        .app-topbar-avatar {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--geminia-primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .app-topbar-name { font-weight: 600; font-size: 0.875rem; color: var(--geminia-text); margin: 0; }
        .app-topbar-role { font-size: 0.75rem; color: var(--geminia-text-muted); margin: 0; }

        /* Main */
        .app-main { flex: 1; min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; }
        .app-content { flex: 1; padding: 1.5rem 1.75rem; }
        .app-page-title { font-size: 1.5rem; font-weight: 700; color: var(--geminia-text); margin: 0 0 0.25rem; }
        .app-page-sub { font-size: 0.9rem; color: var(--geminia-text-muted); margin: 0; }

        .app-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid var(--geminia-border);
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .app-btn-primary { background: var(--geminia-primary); color: #fff; border: none; border-radius: 8px; font-weight: 600; padding: 0.5rem 1rem; }
        .app-btn-primary:hover { background: var(--geminia-primary-dark); color: #fff; }
        .app-form-control:focus { border-color: var(--geminia-primary); box-shadow: 0 0 0 3px var(--geminia-primary-muted); }

        .search-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 4px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            border: 1px solid var(--geminia-border);
            max-height: 320px;
            overflow-y: auto;
            z-index: 1050;
        }
        .search-dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.6rem 1rem;
            text-decoration: none;
            color: var(--geminia-text);
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.15s;
        }
        .search-dropdown-item:last-child { border-bottom: none; }
        .search-dropdown-item:hover { background: var(--geminia-primary-muted); }
        .search-dropdown-empty { padding: 1rem; text-align: center; color: var(--geminia-text-muted); font-size: 0.9rem; }

        .app-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 999; }
        .app-overlay.show { display: block; }
        @media (max-width: 991px) {
            .app-sidebar { position: fixed; left: -260px; top: 0; bottom: 0; transition: left 0.3s; box-shadow: 4px 0 20px rgba(0,0,0,0.15); }
            .app-sidebar.open { left: 0; }
        }
        /* Page load indicator - instant feedback on transitions */
        .page-load-bar {
            position: fixed; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, var(--geminia-primary) 0%, #3b82f6 100%);
            transform: scaleX(0); transform-origin: left;
            z-index: 9999; pointer-events: none;
            transition: transform 0.15s ease-out;
        }
        .page-load-bar.loading { transform: scaleX(0.3); animation: pageLoadProgress 1.5s ease-in-out infinite; }
        .page-load-bar.done { transform: scaleX(1); transition: transform 0.2s ease-out; }
        @keyframes pageLoadProgress { 0%,100%{ transform: scaleX(0.3); } 50%{ transform: scaleX(0.6); } }
    </style>
</head>
<body>
    <div id="pageLoadBar" class="page-load-bar" aria-hidden="true"></div>
    <div class="app-overlay" id="appOverlay" onclick="document.getElementById('appSidebar').classList.remove('open'); this.classList.remove('show')"></div>
    <div class="app-layout">
        <aside class="app-sidebar" id="appSidebar">
            <div class="app-sidebar-brand">
                <div class="app-sidebar-logo"><img src="{{ asset('images/geminia-logo.png') }}" alt="Geminia Life"></div>
                <div>
                    <div class="app-sidebar-title">Geminia Life</div>
                    <div class="app-sidebar-sub">Insurance</div>
                </div>
            </div>
            @php $allowed = $allowedModules ?? []; $can = fn($k) => empty($allowed) || in_array($k, $allowed); @endphp
            <nav class="app-sidebar-nav">
                <div class="app-nav-group">
                    <div class="app-nav-label">Main</div>
                    @if($can('dashboard'))
                    <a href="{{ route('dashboard') }}" class="app-nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="bi bi-house-door-fill"></i><span>Dashboard</span>
                    </a>
                    @endif
                    @if($can('deals'))
                    <a href="{{ route('deals.index') }}" class="app-nav-link {{ request()->routeIs('deals.*') ? 'active' : '' }}">
                        <i class="bi bi-briefcase-fill"></i><span>Deals</span>
                    </a>
                    @endif
                    @if($can('contacts'))
                    <a href="{{ route('contacts.index') }}" class="app-nav-link {{ request()->routeIs('contacts.*') ? 'active' : '' }}">
                        <i class="bi bi-person-lines-fill"></i><span>Contacts</span>
                    </a>
                    @endif
                </div>
                @if($can('leads') || $can('marketing'))
                <div class="app-nav-group">
                    <div class="app-nav-label">Sales</div>
                    @if($can('leads'))
                    <a href="{{ route('leads.index') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('leads.*') ? 'active' : '' }}">
                        <i class="bi bi-people-fill"></i><span>Leads</span>
                    </a>
                    @endif
                    @if($can('marketing.social-media'))
                    <a href="{{ route('marketing.social-media') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('marketing.social-media') ? 'active' : '' }}">
                        <i class="bi bi-facebook"></i><span>Social Media</span>
                    </a>
                    @endif
                    @if($can('marketing.campaigns'))
                    <a href="{{ route('marketing.campaigns.index') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('marketing.campaigns.*') ? 'active' : '' }}">
                        <i class="bi bi-megaphone"></i><span>Campaigns</span>
                    </a>
                    @endif
                </div>
                @endif
                @if($can('tickets') || $can('support'))
                <div class="app-nav-group">
                    <div class="app-nav-label">Support</div>
                    <a href="{{ route('support') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('support') ? 'active' : '' }}">
                        <i class="bi bi-headset"></i><span>Support</span>
                    </a>
                    @if($can('support.serve-client') || $can('tickets'))
                    <a href="{{ route('support.serve-client') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('support.serve-client') ? 'active' : '' }}">
                        <i class="bi bi-person-plus-fill"></i><span>Serve Client</span>
                    </a>
                    @endif
                    @if($can('tickets'))
                    <a href="{{ route('tickets.index') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('tickets.*') ? 'active' : '' }}">
                        <i class="bi bi-ticket-perforated-fill"></i><span>Tickets</span>
                    </a>
                    @endif
                    @if($can('support.faq'))
                    <a href="{{ route('support.faq') }}" class="app-nav-link app-nav-sublink"><i class="bi bi-question-circle"></i><span>FAQ</span></a>
                    @endif
                    @if($can('support.customers'))
                    <a href="{{ route('support.customers') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('support.customers') || request()->routeIs('support.clients.*') ? 'active' : '' }}"><i class="bi bi-people"></i><span>Clients</span></a>
                    @endif
                    @if($can('tickets'))
                    <a href="{{ route('support.maturities') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('support.maturities') ? 'active' : '' }}"><i class="bi bi-calendar-event"></i><span>Maturities</span></a>
                    @endif
                    @if($can('compliance.complaints'))
                    <a href="{{ route('compliance.complaints.index') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('compliance.complaints.*') ? 'active' : '' }}"><i class="bi bi-clipboard2-data"></i><span>Complaint Register</span></a>
                    @endif
                </div>
                @endif
                <div class="app-nav-group">
                    <div class="app-nav-label">Tools</div>
                    @if($can('calendar'))
                    <a href="{{ route('activities.index') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('activities.*') ? 'active' : '' }}">
                        <i class="bi bi-calendar3"></i><span>Calendar</span>
                    </a>
                    @endif
                    @if($can('reports'))
                    <a href="{{ route('reports') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('reports') ? 'active' : '' }}">
                        <i class="bi bi-bar-chart"></i><span>Reports</span>
                    </a>
                    @endif
                    @if($can('tools.pbx-manager'))
                    <a href="{{ route('tools.pbx-manager') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('tools.pbx-manager*') ? 'active' : '' }}">
                        <i class="bi bi-telephone-fill"></i><span>PBX & Calls</span>
                    </a>
                    @endif
                    @if($can('tools.email-templates'))
                    <a href="{{ route('tools.email-templates') }}" class="app-nav-link app-nav-sublink"><i class="bi bi-envelope"></i><span>Email Templates</span></a>
                    @endif
                    @if($can('tools.mail-manager'))
                    <a href="{{ route('tools.mail-manager') }}" class="app-nav-link app-nav-sublink"><i class="bi bi-envelope-at"></i><span>Mail Manager</span></a>
                    @endif
                    @if($can('tools.pdf-maker'))
                    <a href="{{ route('tools.pdf-protect') }}" class="app-nav-link app-nav-sublink {{ request()->routeIs('tools.pdf-protect') ? 'active' : '' }}">
                        <i class="bi bi-shield-lock"></i><span>Protect PDF</span>
                    </a>
                    @endif
                </div>
                @if($can('settings'))
                <div class="app-nav-group">
                    <div class="app-nav-label">Settings</div>
                    <a href="{{ route('settings.crm') }}" class="app-nav-link app-nav-sublink"><i class="bi bi-gear"></i><span>CRM Settings</span></a>
                    @if($can('settings.manage-users'))
                    <a href="{{ route('settings.crm') }}?section=users" class="app-nav-link app-nav-sublink"><i class="bi bi-person-gear"></i><span>Users</span></a>
                    @endif
                </div>
                @endif
            </nav>
            @if($can('leads'))
            <a href="{{ route('leads.create') }}" class="app-sidebar-cta">
                <i class="bi bi-plus-lg"></i> Add Lead
            </a>
            @endif
        </aside>
        <div class="app-main">
            <header class="app-topbar">
                <button class="app-topbar-btn d-lg-none" onclick="document.getElementById('appSidebar').classList.toggle('open'); document.getElementById('appOverlay').classList.toggle('show')">
                    <i class="bi bi-list" style="font-size:1.25rem"></i>
                </button>
                <div class="app-topbar-search position-relative">
                    <i class="bi bi-search"></i>
                    <input type="text" id="globalSearch" class="form-control" placeholder="Search contacts, leads, deals..." autocomplete="off">
                    <div id="searchResults" class="search-dropdown" style="display:none"></div>
                </div>
                <div class="app-topbar-actions ms-auto">
                    <div class="dropdown">
                        <button class="app-topbar-add dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-plus-lg"></i> Quick Create
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end quick-create-menu shadow border-0 rounded-2 py-2" style="min-width: 200px">
                            <li class="dropdown-header px-3 py-2 text-muted small fw-bold text-uppercase">Quick Create</li>
                            @if($can('leads'))
                            <li><a class="dropdown-item py-2" href="{{ route('leads.create') }}"><i class="bi bi-briefcase me-2"></i>Lead</a></li>
                            @endif
                            @if($can('support.serve-client') || $can('tickets'))
                            <li><a class="dropdown-item py-2" href="{{ route('support.serve-client') }}"><i class="bi bi-person-plus me-2"></i>Customer</a></li>
                            @endif
                            @if($can('deals'))
                            <li><a class="dropdown-item py-2" href="{{ route('deals.create') }}"><i class="bi bi-currency-dollar me-2"></i>Opportunity</a></li>
                            @endif
                            @if($can('tickets'))
                            <li><a class="dropdown-item py-2" href="{{ route('tickets.create') }}"><i class="bi bi-ticket-perforated me-2"></i>Ticket</a></li>
                            @endif
                            @if($can('calendar'))
                            <li><a class="dropdown-item py-2" href="{{ route('activities.create', ['type' => 'Event']) }}"><i class="bi bi-calendar-event me-2"></i>Event</a></li>
                            <li><a class="dropdown-item py-2" href="{{ route('activities.create', ['type' => 'Task']) }}"><i class="bi bi-check2-square me-2"></i>Task</a></li>
                            @endif
                        </ul>
                    </div>
                    <a href="{{ route('reports') }}" class="app-topbar-btn" title="Reports"><i class="bi bi-bar-chart"></i></a>
                    <a href="{{ route('tickets.index') }}" class="app-topbar-btn" title="Tickets"><i class="bi bi-ticket-perforated"></i></a>
                    <div class="dropdown">
                        <div class="app-topbar-user" data-bs-toggle="dropdown">
                            <div class="text-end">
                                <p class="app-topbar-name mb-0">{{ $currentUserName ?? 'User' }}</p>
                                <p class="app-topbar-role mb-0">{{ $currentUserRole ?? '—' }}</p>
                            </div>
                            <div class="app-topbar-avatar">{{ $currentUserInitials ?? 'U' }}</div>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-2 py-2" style="min-width:180px">
                            <li><a class="dropdown-item py-2" href="{{ route('settings') }}"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item py-2" href="{{ route('settings') }}"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <li><hr class="dropdown-divider my-2"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}" class="logout-form">
                                    @csrf
                                    <button type="submit" class="dropdown-item py-2 text-danger border-0 bg-transparent w-100 text-start"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>
            <main class="app-content">
                @yield('content')
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" defer></script>
    <script>
    (function(){
        var bar=document.getElementById('pageLoadBar'), base=document.querySelector('base')?.href||window.location.origin;
        function isInternal(url){ try{ var u=typeof url==='string'?new URL(url,base):url; return u.origin===window.location.origin; }catch(e){ return false; }}
        function showBar(){ if(bar){ bar.classList.add('loading'); bar.classList.remove('done'); bar.setAttribute('aria-hidden','false'); }}
        function hideBar(){ if(bar){ bar.classList.remove('loading'); bar.classList.add('done'); setTimeout(function(){ bar.classList.remove('done'); bar.style.transform='scaleX(0)'; bar.setAttribute('aria-hidden','true'); },220); }}
        document.addEventListener('click',function(e){ var a=e.target.closest('a'); if(a&&a.href&&isInternal(a.href)&&!a.target&&!e.ctrlKey&&!e.metaKey&&!e.shiftKey){ showBar(); }},true);
        document.addEventListener('submit',function(e){
            var f=e.target;
            if(!f||!isInternal(f.action||window.location.href))return;
            if(f.method.toLowerCase()==='get')showBar();
            else if(f.classList&&f.classList.contains('logout-form')){ showBar(); var b=f.querySelector('button[type=submit]'); if(b){ b.disabled=true; b.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Logging out...'; }}
        },true);
        if(document.readyState==='complete')hideBar(); else window.addEventListener('load',hideBar);
    })();
    </script>
    <script>
    (function(){
        var inp=document.getElementById('globalSearch'),res=document.getElementById('searchResults');
        if(!inp||!res)return;
        var t;
        inp.oninput=function(){
            clearTimeout(t);
            var q=(inp.value||'').trim();
            if(q.length<2){res.style.display='none';return;}
            t=setTimeout(function(){
                fetch('{{ route("search") }}?q='+encodeURIComponent(q)+'&limit=8').then(r=>r.json()).then(function(d){
                    var r=d.results||[];
                    res.innerHTML=r.length?r.map(function(x){
                        return '<a href="'+(x.url||'#')+'" class="search-dropdown-item"><i class="bi '+(x.icon||'bi-circle')+' text-muted"></i><span>'+(x.title||'').replace(/</g,'&lt;')+'</span></a>';
                    }).join(''):'<div class="search-dropdown-empty">No results</div>';
                    res.style.display='block';
                }).catch(function(){res.style.display='none';});
            },200);
        };
        inp.onfocus=function(){if((inp.value||'').trim().length>=2&&res.innerHTML)res.style.display='block';};
        inp.onblur=function(){setTimeout(function(){res.style.display='none';},150);};
        document.onclick=function(e){if(!inp.contains(e.target)&&!res.contains(e.target))res.style.display='none';};
    })();
    </script>
    <script>
    (function(){
        var prefetched={};
        document.querySelectorAll('a[href*="serve-client"],a[href*="tickets"],a[href*="support/customers"],a[href*="contacts"]').forEach(function(a){
            if(!a.href||a.target||prefetched[a.href])return;
            a.addEventListener('mouseenter',function(){
                if(prefetched[a.href])return;
                prefetched[a.href]=true;
                var link=document.createElement('link');link.rel='prefetch';link.href=a.href;document.head.appendChild(link);
            },{once:true,passive:true});
        });
    })();
    </script>
    @include('partials.pbx-tel-handler')
    @stack('scripts')
</body>
</html>
