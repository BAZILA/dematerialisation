<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Mon espace') — Université de Kara</title>
    <link rel="icon" href="{{ asset('assets/uk/logo.ico') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@0,300;0,400;0,500;0,600;0,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --bg: #EEF3FA;
            --surface: #FFFFFF;
            --surface-2: #F5F8FC;
            --surface-3: #EAF0F8;
            --line: #E5EBF3;
            --line-mid: #D3DEEA;
            --brand: #1A56A8;
            --brand-600: #16478C;
            --em: #1A56A8;
            --em-dim: rgba(26, 86, 168, .10);
            --em-glow: rgba(26, 86, 168, .28);
            --green: #2F9E33;
            --green-dim: rgba(47, 158, 51, .13);
            --amber: #B45309;
            --amber-dim: rgba(180, 83, 9, .12);
            --red: #DC2626;
            --red-dim: rgba(220, 38, 38, .10);
            --blue: #2563EB;
            --blue-dim: rgba(37, 99, 235, .10);
            --t1: #14243B;
            --t2: #4A5A72;
            --t3: #8A98AC;
            --font-head: 'Sora', sans-serif;
            --font-body: 'Inter', sans-serif;
            --r: 12px;
            --r-lg: 18px;
            --shadow-sm: 0 1px 2px rgba(20, 40, 80, .05), 0 2px 6px rgba(20, 40, 80, .05);
            --shadow: 0 8px 30px rgba(20, 40, 80, .08);
            --shadow-lg: 0 20px 50px rgba(20, 40, 80, .16);
            --ease: cubic-bezier(.4, 0, .2, 1);
            --dur: .2s;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: var(--font-body); font-size: 14.5px;
            background:
                radial-gradient(1100px 500px at 15% -5%, #E3ECFA 0%, transparent 55%),
                radial-gradient(900px 460px at 100% 0%, #E4F5E6 0%, transparent 50%),
                var(--bg);
            color: var(--t1); min-height: 100vh; -webkit-font-smoothing: antialiased;
        }

        /* ── Barre du haut (claire) ── */
        .st-nav {
            background: rgba(255, 255, 255, .9);
            backdrop-filter: saturate(180%) blur(10px);
            border-bottom: 1px solid var(--line);
            position: sticky; top: 0; z-index: 100;
        }
        .st-nav-inner {
            max-width: 1120px; margin: 0 auto; padding: 0 22px;
            height: 66px; display: flex; align-items: center; gap: 16px;
        }
        .st-brand { display: flex; align-items: center; text-decoration: none; }
        .st-brand img { height: 42px; width: auto; }
        .st-spacer { flex: 1; }
        .st-user { display: flex; align-items: center; gap: 11px; }
        .st-avatar {
            width: 40px; height: 40px; border-radius: 50%;
            background: linear-gradient(135deg, var(--brand) 0%, #2F9E33 100%);
            color: #fff; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-family: var(--font-head); font-size: 14px; flex-shrink: 0;
        }
        .st-user-meta { line-height: 1.2; }
        .st-user-name { font-size: 13.5px; font-weight: 600; color: var(--t1); }
        .st-user-role { font-size: 11.5px; color: var(--t3); }
        .st-logout {
            border: 1px solid var(--line-mid); background: var(--surface); color: var(--t2);
            width: 40px; height: 40px; border-radius: 11px; cursor: pointer;
            display: flex; align-items: center; justify-content: center; transition: all var(--dur);
        }
        .st-logout:hover { background: var(--red-dim); color: var(--red); border-color: transparent; }

        .st-main { max-width: 1120px; margin: 0 auto; padding: 30px 22px 60px; }
        .st-hello { margin-bottom: 22px; }
        .st-hello h1 { font-family: var(--font-head); font-size: 25px; font-weight: 800; letter-spacing: -.02em; color: var(--t1); }
        .st-hello p { color: var(--t2); font-size: 14px; margin-top: 3px; }

        /* ── Composants (mêmes noms de classes que le back-office) ── */
        .card { background: var(--surface) !important; border: 1px solid var(--line) !important; border-radius: var(--r-lg) !important; box-shadow: var(--shadow-sm) !important; }
        .card-header { background: transparent !important; border-bottom: 1px solid var(--line) !important; padding: 16px 22px !important; font-weight: 600; color: var(--t1); }
        .card-header.bg-primary, .card-header.bg-success, .card-header.bg-warning, .card-header.bg-info, .card-header.bg-danger { color: #fff !important; }
        .card-header.bg-primary { background: var(--brand) !important; }
        .card-header.bg-success { background: var(--green) !important; }
        .card-header.bg-warning { background: var(--amber) !important; }
        .card-header.bg-info { background: var(--blue) !important; }
        .card-header.bg-danger { background: var(--red) !important; }
        .card-body { padding: 22px !important; }

        .btn { font-family: var(--font-body); font-weight: 600; font-size: 14px; border-radius: 12px; transition: all var(--dur) var(--ease); }
        .btn-lg { border-radius: 14px; }
        .btn-primary { background: var(--brand) !important; border-color: var(--brand) !important; color: #fff !important; }
        .btn-primary:hover { background: var(--brand-600) !important; border-color: var(--brand-600) !important; box-shadow: 0 8px 22px var(--em-glow) !important; transform: translateY(-1px); }
        .btn-success { background: var(--green) !important; border-color: var(--green) !important; color: #fff !important; }
        .btn-success:hover { background: #25801f !important; transform: translateY(-1px); }
        .btn-warning { background: var(--amber) !important; border-color: var(--amber) !important; color: #fff !important; }
        .btn-danger { background: var(--red) !important; border-color: var(--red) !important; color: #fff !important; }
        .btn-info { background: var(--blue) !important; border-color: var(--blue) !important; color: #fff !important; }
        .btn-secondary { background: var(--surface-3) !important; border-color: var(--line-mid) !important; color: var(--t2) !important; }
        .btn-outline-primary { background: transparent !important; border-color: var(--line-mid) !important; color: var(--brand) !important; }
        .btn-outline-primary:hover { background: var(--em-dim) !important; border-color: var(--brand) !important; color: var(--brand) !important; }
        .btn-outline-secondary { background: transparent !important; border-color: var(--line-mid) !important; color: var(--t2) !important; }
        .btn-outline-secondary:hover { background: var(--surface-2) !important; color: var(--t1) !important; }
        .btn-outline-danger { background: transparent !important; border-color: rgba(220, 38, 38, .35) !important; color: var(--red) !important; }
        .btn-outline-danger:hover { background: var(--red) !important; color: #fff !important; }
        .btn:disabled { opacity: .5 !important; pointer-events: none; }

        .badge { font-weight: 600; font-size: 11px; border-radius: 8px; padding: 5px 10px !important; }
        .bg-primary { background: var(--em-dim) !important; color: var(--brand) !important; }
        .bg-success { background: var(--green-dim) !important; color: #1f7a24 !important; }
        .bg-danger { background: var(--red-dim) !important; color: var(--red) !important; }
        .bg-warning { background: var(--amber-dim) !important; color: var(--amber) !important; }
        .bg-secondary { background: var(--surface-3) !important; color: var(--t2) !important; }
        .bg-info { background: var(--blue-dim) !important; color: var(--blue) !important; }
        .text-success { color: #1f7a24 !important; } .text-danger { color: var(--red) !important; }
        .text-warning { color: var(--amber) !important; } .text-info { color: var(--blue) !important; }
        .text-muted { color: var(--t2) !important; } .text-primary { color: var(--brand) !important; } .text-white { color: #fff !important; }
        .icon-box { display: flex; align-items: center; justify-content: center; border-radius: 14px; flex-shrink: 0; }
        .icon-box.bg-primary { background: var(--brand) !important; color: #fff !important; }
        .icon-box.text-white { color: #fff !important; }

        .form-control, .form-select { font-size: 14px; background: var(--surface) !important; border: 1px solid var(--line-mid) !important; border-radius: 12px !important; color: var(--t1) !important; padding: 11px 14px !important; }
        .form-control::placeholder { color: var(--t3) !important; }
        .form-control:focus, .form-select:focus { border-color: var(--brand) !important; box-shadow: 0 0 0 3px var(--em-dim) !important; outline: none; }
        .form-control.is-invalid { border-color: var(--red) !important; }
        .form-label { font-size: 13px; font-weight: 600; color: var(--t2); margin-bottom: 6px; }
        .invalid-feedback { font-size: 12px; color: var(--red) !important; }
        .form-check-input:checked { background-color: var(--brand) !important; border-color: var(--brand) !important; }
        .form-check-input:focus { box-shadow: 0 0 0 3px var(--em-dim) !important; }

        .nav-tabs { border-bottom: 1px solid var(--line) !important; gap: 4px; }
        .nav-tabs .nav-link { font-weight: 600; color: var(--t2) !important; border: 1px solid transparent !important; border-radius: 12px 12px 0 0; padding: 11px 18px; }
        .nav-tabs .nav-link:hover { color: var(--t1) !important; background: var(--surface-2); }
        .nav-tabs .nav-link.active { color: var(--brand) !important; background: var(--surface) !important; border-color: var(--line) var(--line) var(--surface) !important; box-shadow: 0 -2px 0 var(--brand) inset; }

        .list-group-item { background: transparent !important; border-color: var(--line) !important; color: var(--t1) !important; padding: 15px 20px !important; font-size: 14px; }
        .list-group-flush .list-group-item:first-child { border-top: 0 !important; }
        .alert { border: 1px solid !important; border-radius: var(--r) !important; font-size: 14px; padding: 13px 16px !important; }
        .alert-success { background: var(--green-dim) !important; border-color: rgba(47, 158, 51, .25) !important; color: #1f7a24 !important; }
        .alert-danger { background: var(--red-dim) !important; border-color: rgba(220, 38, 38, .25) !important; color: #b91c1c !important; }
        .alert-warning { background: var(--amber-dim) !important; border-color: rgba(180, 83, 9, .25) !important; color: #92400e !important; }
        .alert-info { background: var(--blue-dim) !important; border-color: rgba(37, 99, 235, .22) !important; color: #1d4ed8 !important; }

        .modal-content { background: var(--surface) !important; border: 1px solid var(--line-mid) !important; border-radius: var(--r-lg) !important; box-shadow: var(--shadow-lg) !important; }
        .modal-header { border-bottom: 1px solid var(--line) !important; padding: 17px 22px !important; }
        .modal-title { font-weight: 700; color: var(--t1); font-family: var(--font-head); }
        .modal-body { padding: 22px !important; } .modal-footer { border-top: 1px solid var(--line) !important; padding: 14px 22px !important; }
        .modal-backdrop { background: rgba(20, 40, 80, .45) !important; backdrop-filter: blur(3px); }
        code { font-size: 12.5px; background: var(--em-dim); padding: 2px 8px; border-radius: 6px; color: var(--brand); font-weight: 600; }
        hr { border-color: var(--line) !important; opacity: 1 !important; }

        /* ── Toasts ── */
        .toast-shelf { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; width: 340px; }
        .toast-pill { display: flex; align-items: center; gap: 11px; padding: 14px 16px; background: var(--surface); border: 1px solid var(--line-mid); border-radius: 14px; box-shadow: var(--shadow-lg); animation: rise .3s var(--ease) both; position: relative; overflow: hidden; }
        .toast-pill::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px; }
        .toast-success::after { background: var(--green); } .toast-danger::after { background: var(--red); }
        .toast-warning::after { background: var(--amber); } .toast-info::after { background: var(--blue); }
        .toast-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
        .toast-success .toast-dot { background: var(--green); } .toast-danger .toast-dot { background: var(--red); }
        .toast-warning .toast-dot { background: var(--amber); } .toast-info .toast-dot { background: var(--blue); }
        .toast-text { flex: 1; min-width: 0; } .toast-title { font-size: 13.5px; font-weight: 600; color: var(--t1); }
        .toast-msg { font-size: 12.5px; color: var(--t2); margin-top: 2px; }
        .toast-x { background: none; border: none; color: var(--t3); cursor: pointer; padding: 3px; }
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

        /* ── Onglets : défilement horizontal à TOUTES les tailles (l'actif est
               recentré par JS ; jamais d'empilement sur 2 lignes) ── */
        .nav-tabs { flex-wrap: nowrap !important; overflow-x: auto; overflow-y: hidden;
            scrollbar-width: none; -ms-overflow-style: none; -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth; }
        .nav-tabs::-webkit-scrollbar { display: none; }
        .nav-tabs .nav-link { white-space: nowrap; flex-shrink: 0; }

        @media (max-width: 767px) {
            .nav-tabs .nav-link { padding: 10px 14px; font-size: 13.5px; }
            .card-body { padding: 16px !important; }
            .card-header { padding: 13px 16px !important; }
            .modal-body { padding: 16px !important; }
            .table { font-size: 13px; }
        }

        @media (max-width: 640px) {
            .st-nav-inner, .st-main { padding-left: 15px; padding-right: 15px; }
            .st-main { padding-top: 20px; }
            .st-user-meta { display: none; }
            .st-hello h1 { font-size: 21px; }
        }
    </style>
    @yield('css')
</head>

<body>
    <nav class="st-nav">
        <div class="st-nav-inner">
            <a href="{{ route('student.dashboard') }}" class="st-brand">
                <img src="{{ asset('assets/uk/logo-sigle-bg.png') }}" alt="Université de Kara">
            </a>
            <div class="st-spacer"></div>
            <div class="st-user">
                <div class="st-avatar">{{ strtoupper(substr(auth()->user()->student->prenom ?? auth()->user()->numero_identifiant, 0, 1) . substr(auth()->user()->student->nom ?? '', 0, 1)) }}</div>
                <div class="st-user-meta">
                    <div class="st-user-name">{{ auth()->user()->student->full_name ?? auth()->user()->numero_identifiant }}</div>
                    <div class="st-user-role"><code>{{ auth()->user()->numero_identifiant }}</code></div>
                </div>
                <form method="POST" action="{{ route('logout') }}" data-no-loading>
                    @csrf
                    <button type="submit" class="st-logout" title="Se déconnecter"><i class="fas fa-arrow-right-from-bracket"></i></button>
                </form>
            </div>
        </div>
    </nav>

    <main class="st-main">
        <div class="toast-shelf" id="toastShelf">
            @foreach ($errors->all() as $error)
                <div class="toast-pill toast-danger" data-ttl="6000"><div class="toast-dot"></div><div class="toast-text"><div class="toast-title">Erreur</div><div class="toast-msg">{{ $error }}</div></div><button class="toast-x" onclick="this.closest('.toast-pill').remove()"><i class="fas fa-xmark"></i></button></div>
            @endforeach
            @if (session('success'))
                <div class="toast-pill toast-success" data-ttl="5000"><div class="toast-dot"></div><div class="toast-text"><div class="toast-title">Succès</div><div class="toast-msg">{{ session('success') }}</div></div><button class="toast-x" onclick="this.closest('.toast-pill').remove()"><i class="fas fa-xmark"></i></button></div>
            @endif
            @if (session('info'))
                <div class="toast-pill toast-info" data-ttl="5000"><div class="toast-dot"></div><div class="toast-text"><div class="toast-title">Information</div><div class="toast-msg">{{ session('info') }}</div></div><button class="toast-x" onclick="this.closest('.toast-pill').remove()"><i class="fas fa-xmark"></i></button></div>
            @endif
        </div>

        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            document.querySelectorAll('.toast-pill[data-ttl]').forEach(el => {
                const ttl = parseInt(el.dataset.ttl) || 5000;
                setTimeout(() => { el.style.transition = 'opacity .35s, transform .35s'; el.style.opacity = '0'; el.style.transform = 'translateY(10px)'; setTimeout(() => el.remove(), 360); }, ttl);
            });
            window.btnLoading = function (btn, text) {
                if (!btn || btn.dataset.loading) return;
                btn.dataset.loading = '1'; btn.style.minWidth = btn.offsetWidth + 'px'; btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="vertical-align:-2px;"></span> ' + (text || 'Veuillez patienter…');
            };
            document.addEventListener('submit', function (e) {
                if (e.defaultPrevented) return;
                const form = e.target;
                if (!form || form.hasAttribute('data-no-loading')) return;
                let btn = form.querySelector('button[type="submit"], button:not([type])');
                const active = document.activeElement;
                if (active && active.type === 'submit' && form.contains(active)) btn = active;
                if (btn) window.btnLoading(btn, btn.dataset.loadingText);
            });
        })();
    </script>
    @yield('js')
</body>

</html>
