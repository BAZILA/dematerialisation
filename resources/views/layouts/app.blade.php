<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Accueil') — UK Démat</title>
    <link rel="icon" href="{{ asset('assets/uk/logo.ico') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@0,300;0,400;0,500;0,600;0,700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* ╔═══════════════════════════════════════════════╗
           ║  TOKENS — Sidebar sombre + contenu clair       ║
           ╚═══════════════════════════════════════════════╝ */
        :root {
            --bg: #F1F5F9;
            --surface: #FFFFFF;
            --surface-2: #F8FAFC;
            --surface-3: #EEF2F7;
            --line: #E6EAF1;
            --line-mid: #D3DAE6;

            /* Accent (indigo) — alias historiques --brand / --em */
            --brand: #1A56A8;
            --brand-600: #14468A;
            --em: #1A56A8;
            --em-dim: rgba(26, 86, 168, .10);
            --em-glow: rgba(26, 86, 168, .30);

            --green: #2F9E33;
            --green-dim: rgba(47, 158, 51, .12);
            --amber: #B45309;
            --amber-dim: rgba(180, 83, 9, .12);
            --red: #DC2626;
            --red-dim: rgba(220, 38, 38, .10);
            --blue: #2563EB;
            --blue-dim: rgba(37, 99, 235, .10);

            --t1: #0F172A;
            --t2: #475569;
            --t3: #94A3B8;

            /* Sidebar sombre */
            --sb-bg: #0F172A;
            --sb-bg2: #0B1120;
            --sb-line: rgba(255, 255, 255, .07);
            --sb-text: #94A3B8;
            --sb-text-hover: #E2E8F0;
            --sb-muted: #64748B;

            --font-head: 'Sora', sans-serif;
            --font-body: 'Inter', sans-serif;

            --r: 10px;
            --r-lg: 14px;
            --shadow-sm: 0 1px 2px rgba(15, 23, 42, .05), 0 1px 3px rgba(15, 23, 42, .06);
            --shadow-lg: 0 20px 50px rgba(15, 23, 42, .18);

            --ease: cubic-bezier(.4, 0, .2, 1);
            --dur: .2s;

            --sb-w: 252px;
            --sb-w-collapsed: 74px;
            --topbar-h: 62px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            font-size: 14px;
            background: var(--bg);
            color: var(--t1);
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ╔═══════════════════════════════════════════════╗
           ║  SIDEBAR (fixe, repliable — sans survol)       ║
           ╚═══════════════════════════════════════════════╝ */
        .sb {
            position: fixed;
            top: 0; left: 0;
            width: var(--sb-w);
            height: 100vh;
            background: linear-gradient(180deg, var(--sb-bg) 0%, var(--sb-bg2) 100%);
            border-right: 1px solid var(--sb-line);
            display: flex;
            flex-direction: column;
            z-index: 300;
            transition: width var(--dur) var(--ease), transform var(--dur) var(--ease);
        }

        .sb-head {
            height: var(--topbar-h);
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 12px;
            border-bottom: 1px solid var(--sb-line);
            flex-shrink: 0;
            overflow: hidden;
        }

        .sb-logo {
            width: 40px; height: 40px;
            background: #fff;
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; overflow: hidden; padding: 3px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .35);
        }
        .sb-logo img { width: 100%; height: 100%; object-fit: contain; }
        .sb-brand-link { display: flex; align-items: center; text-decoration: none; overflow: hidden; }
        .sb-logo-full { display: flex; align-items: center; background: #fff; border-radius: 10px; padding: 5px 9px; height: 44px; box-shadow: 0 4px 12px rgba(0, 0, 0, .35); }
        .sb-logo-full img { height: 100%; width: auto; max-width: 178px; object-fit: contain; }
        .sb-logo-mark { display: none; }
        body.sb-collapsed .sb-logo-full { display: none; }
        body.sb-collapsed .sb-logo-mark { display: flex; }

        .sb-brand-name {
            font-family: var(--font-head);
            font-size: 17px; font-weight: 700;
            color: #fff; letter-spacing: -.02em;
            white-space: nowrap;
        }

        .sb-nav { flex: 1; padding: 10px 12px; overflow-y: auto; overflow-x: hidden; scrollbar-width: none; -ms-overflow-style: none; }
        .sb-nav::-webkit-scrollbar { display: none; }

        .sb-section {
            font-size: 10px; font-weight: 700; letter-spacing: .12em;
            text-transform: uppercase; color: var(--sb-muted);
            padding: 11px 12px 5px; white-space: nowrap;
        }

        .sb-link {
            display: flex; align-items: center; gap: 13px;
            padding: 10px 12px; margin-bottom: 3px;
            color: var(--sb-text); text-decoration: none;
            font-size: 14px; font-weight: 500;
            border-radius: 10px;
            white-space: nowrap;
            transition: color var(--dur), background var(--dur);
        }
        .sb-link:hover { color: var(--sb-text-hover); background: rgba(255, 255, 255, .05); }
        .sb-link.active {
            color: #fff;
            background: linear-gradient(90deg, #1A56A8 0%, #2F9E33 100%);
            box-shadow: 0 6px 16px rgba(26, 86, 168, .4);
        }

        .sb-icon {
            width: 26px; min-width: 26px; height: 26px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
        }

        .sb-foot { border-top: 1px solid var(--sb-line); padding: 12px; flex-shrink: 0; }
        .sb-user { display: flex; align-items: center; gap: 11px; padding: 6px; border-radius: var(--r); }
        .sb-avatar {
            width: 38px; height: 38px; border-radius: 11px;
            background: rgba(255, 255, 255, .08); border: 1px solid var(--sb-line);
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; color: #fff; flex-shrink: 0;
            font-family: var(--font-head);
        }
        .sb-user-meta { flex: 1; min-width: 0; }
        .sb-user-name { font-size: 13px; font-weight: 600; color: #E2E8F0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sb-user-role { font-size: 11px; color: var(--sb-muted); margin-top: 1px; white-space: nowrap; }
        .sb-logout {
            background: none; border: none; color: var(--sb-muted); cursor: pointer;
            width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;
            border-radius: 8px; font-size: 13px; transition: all var(--dur); flex-shrink: 0;
        }
        .sb-logout:hover { background: rgba(220, 38, 38, .18); color: #F87171; }

        /* ── État REPLIÉ (icônes seules) ── */
        body.sb-collapsed .sb { width: var(--sb-w-collapsed); }
        body.sb-collapsed .main { margin-left: var(--sb-w-collapsed); }
        body.sb-collapsed .sb-brand-name,
        body.sb-collapsed .sb-section,
        body.sb-collapsed .sb-user-meta,
        body.sb-collapsed .sb-label { display: none; }
        body.sb-collapsed .sb-link { justify-content: center; padding: 10px; }
        body.sb-collapsed .sb-head { justify-content: center; padding: 0; }
        body.sb-collapsed .sb-user { justify-content: center; padding: 6px 0; }
        body.sb-collapsed .sb-logout { display: none; }

        /* ╔═══════════════════════════════════════════════╗
           ║  MAIN                                          ║
           ╚═══════════════════════════════════════════════╝ */
        .main {
            margin-left: var(--sb-w);
            min-height: 100vh;
            display: flex; flex-direction: column;
            transition: margin-left var(--dur) var(--ease);
        }

        .topbar {
            height: var(--topbar-h);
            background: rgba(255, 255, 255, .8);
            backdrop-filter: saturate(180%) blur(10px);
            border-bottom: 1px solid var(--line);
            display: flex; align-items: center; padding: 0 22px; gap: 14px;
            position: sticky; top: 0; z-index: 200; flex-shrink: 0;
        }
        .tb-toggle {
            width: 38px; height: 38px; border: 1px solid var(--line-mid);
            background: var(--surface); border-radius: 10px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: var(--t2); transition: all var(--dur); flex-shrink: 0;
        }
        .tb-toggle:hover { background: var(--surface-3); color: var(--brand); border-color: var(--brand); }
        .tb-heading { flex: 1; min-width: 0; }
        .tb-title { font-family: var(--font-head); font-size: 18px; font-weight: 700; color: var(--t1); letter-spacing: -.02em; line-height: 1.2; }
        .tb-sub { font-size: 11.5px; color: var(--t3); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tb-right { display: flex; align-items: center; gap: 8px; margin-left: auto; }
        .tb-pill {
            display: flex; align-items: center; gap: 7px; padding: 7px 13px;
            background: var(--surface); border: 1px solid var(--line); border-radius: 100px;
            font-size: 12px; font-weight: 500; color: var(--t2);
        }
        .tb-pill i { font-size: 8px; color: var(--green); }

        .content { flex: 1; padding: 28px 30px; max-width: 1440px; width: 100%; }

        /* ╔═══════════════════════════════════════════════╗
           ║  TOASTS                                        ║
           ╚═══════════════════════════════════════════════╝ */
        .toast-shelf { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; width: 340px; }
        .toast-pill {
            display: flex; align-items: center; gap: 11px; padding: 13px 15px;
            background: var(--surface); border: 1px solid var(--line-mid);
            border-radius: 12px; box-shadow: var(--shadow-lg);
            animation: riseUp .3s var(--ease) both; position: relative; overflow: hidden;
        }
        .toast-pill::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 3px; }
        .toast-success::after { background: var(--green); }
        .toast-danger::after { background: var(--red); }
        .toast-warning::after { background: var(--amber); }
        .toast-info::after { background: var(--blue); }
        .toast-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
        .toast-success .toast-dot { background: var(--green); }
        .toast-danger .toast-dot { background: var(--red); }
        .toast-warning .toast-dot { background: var(--amber); }
        .toast-info .toast-dot { background: var(--blue); }
        .toast-text { flex: 1; min-width: 0; }
        .toast-title { font-size: 13px; font-weight: 600; color: var(--t1); }
        .toast-msg { font-size: 12px; color: var(--t2); margin-top: 2px; line-height: 1.4; }
        .toast-x { background: none; border: none; color: var(--t3); cursor: pointer; font-size: 11px; padding: 3px; transition: color var(--dur); flex-shrink: 0; }
        .toast-x:hover { color: var(--t1); }
        @keyframes riseUp { from { opacity: 0; transform: translateY(14px) scale(.97); } to { opacity: 1; transform: translateY(0) scale(1); } }

        /* ╔═══════════════════════════════════════════════╗
           ║  GUEST (login / register)                      ║
           ╚═══════════════════════════════════════════════╝ */
        .sb-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, .45); backdrop-filter: blur(3px); z-index: 299; }
        body.guest-mode { background: radial-gradient(1200px 600px at 15% -10%, #DCEBFF 0%, transparent 55%), radial-gradient(1000px 500px at 100% 10%, #DDF3E0 0%, transparent 50%), #F1F5F9; }
        body.guest-mode .sb, body.guest-mode .sb-overlay { display: none !important; }
        body.guest-mode .main { margin-left: 0; }
        body.guest-mode .topbar { display: none; }
        body.guest-mode .content { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 28px 16px; max-width: none; }

        /* ╔═══════════════════════════════════════════════╗
           ║  BOOTSTRAP OVERRIDES                           ║
           ╚═══════════════════════════════════════════════╝ */
        .card { background: var(--surface) !important; border: 1px solid var(--line) !important; border-radius: var(--r-lg) !important; box-shadow: var(--shadow-sm) !important; }
        .card-header { background: transparent !important; border-bottom: 1px solid var(--line) !important; padding: 15px 20px !important; font-size: 13.5px; font-weight: 600; color: var(--t1); }
        .card-header.bg-primary { background: var(--em-dim) !important; color: var(--brand) !important; border-bottom-color: var(--line) !important; }
        .card-header.bg-success { background: var(--green-dim) !important; color: var(--green) !important; }
        .card-header.bg-secondary { background: var(--surface-2) !important; }
        .card-header.bg-warning { background: var(--amber-dim) !important; color: var(--amber) !important; }
        .card-header.bg-info { background: var(--blue-dim) !important; color: var(--blue) !important; }
        .card-header.bg-danger { background: var(--red-dim) !important; color: var(--red) !important; }
        .card-body { padding: 20px !important; }

        .btn { font-family: var(--font-body); font-weight: 600; font-size: 13.5px; border-radius: 10px; transition: all var(--dur) var(--ease); }

        /* ── Barres de filtres ───────────────────────────────────────
           Les boutons d'action ne s'étirent plus sur toute la colonne :
           un bouton large et bas paraissait écrasé. Hauteur alignée sur
           celle des champs, largeur bornée. */
        .filtre-actions { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
        .filtre-actions .btn {
            min-height: 38px; padding: 0 20px; display: inline-flex;
            align-items: center; justify-content: center; gap: 7px;
            font-size: 13px; white-space: nowrap;
        }
        .filtre-actions .btn-filtrer { min-width: 130px; max-width: 190px; flex: 0 1 auto; }
        .filtre-actions .btn-reset { min-width: 44px; padding: 0 14px; }
        @media (max-width: 575.98px) {
            .filtre-actions { width: 100%; }
            .filtre-actions .btn-filtrer { flex: 1 1 auto; max-width: none; }
        }
        .btn-primary { background: var(--brand) !important; border-color: var(--brand) !important; color: #fff !important; }
        .btn-primary:hover { background: var(--brand-600) !important; border-color: var(--brand-600) !important; box-shadow: 0 8px 20px var(--em-glow) !important; transform: translateY(-1px); }
        .btn-success { background: var(--green) !important; border-color: var(--green) !important; color: #fff !important; }
        .btn-success:hover { background: #15803D !important; transform: translateY(-1px); }
        .btn-warning { background: var(--amber) !important; border-color: var(--amber) !important; color: #fff !important; }
        .btn-warning:hover { background: #92400E !important; color: #fff !important; }
        .btn-danger { background: var(--red) !important; border-color: var(--red) !important; color: #fff !important; }
        .btn-danger:hover { background: #B91C1C !important; color: #fff !important; transform: translateY(-1px); }
        .btn-info { background: var(--blue) !important; border-color: var(--blue) !important; color: #fff !important; }
        .btn-secondary { background: var(--surface-3) !important; border-color: var(--line-mid) !important; color: var(--t2) !important; }
        .btn-outline-primary { background: transparent !important; border-color: var(--line-mid) !important; color: var(--brand) !important; }
        .btn-outline-primary:hover { background: var(--em-dim) !important; border-color: var(--brand) !important; color: var(--brand) !important; }
        .btn-outline-secondary { background: transparent !important; border-color: var(--line-mid) !important; color: var(--t2) !important; }
        .btn-outline-secondary:hover { background: var(--surface-2) !important; color: var(--t1) !important; }
        .btn-outline-danger { background: transparent !important; border-color: rgba(220, 38, 38, .35) !important; color: var(--red) !important; }
        .btn-outline-danger:hover { background: var(--red) !important; color: #fff !important; }
        .btn-outline-warning { background: transparent !important; border-color: rgba(180, 83, 9, .4) !important; color: var(--amber) !important; }
        .btn-outline-warning:hover { background: var(--amber) !important; color: #fff !important; }
        .btn-outline-success { background: transparent !important; border-color: rgba(22, 163, 74, .4) !important; color: var(--green) !important; }
        .btn-outline-success:hover { background: var(--green) !important; color: #fff !important; }
        .btn:disabled { opacity: .5 !important; pointer-events: none; }

        .badge { font-family: var(--font-body); font-weight: 600; font-size: 11px; border-radius: 7px; padding: 4px 9px !important; }
        .bg-primary { background: var(--em-dim) !important; color: var(--brand) !important; }
        .bg-success { background: var(--green-dim) !important; color: var(--green) !important; }
        .bg-danger { background: var(--red-dim) !important; color: var(--red) !important; }
        .bg-warning { background: var(--amber-dim) !important; color: var(--amber) !important; }
        .bg-secondary { background: var(--surface-3) !important; color: var(--t2) !important; }
        .bg-info { background: var(--blue-dim) !important; color: var(--blue) !important; }
        .text-success { color: var(--green) !important; }
        .text-danger { color: var(--red) !important; }
        .text-warning { color: var(--amber) !important; }
        .text-info { color: var(--blue) !important; }
        .text-muted { color: var(--t2) !important; }
        .text-primary { color: var(--brand) !important; }
        .text-white { color: var(--t1) !important; }
        .icon-box { display: flex; align-items: center; justify-content: center; border-radius: 12px; flex-shrink: 0; }
        .icon-box.bg-primary { background: var(--brand) !important; color: #fff !important; }
        .icon-box.text-white { color: #fff !important; }

        .form-control, .form-select { font-family: var(--font-body); font-size: 13.5px; background: var(--surface) !important; border: 1px solid var(--line-mid) !important; border-radius: 10px !important; color: var(--t1) !important; padding: 10px 13px !important; transition: border-color var(--dur), box-shadow var(--dur); }
        .form-control::placeholder { color: var(--t3) !important; }
        .form-control:focus, .form-select:focus { border-color: var(--brand) !important; box-shadow: 0 0 0 3px var(--em-dim) !important; outline: none; background: #fff !important; }
        .form-control.is-invalid { border-color: var(--red) !important; box-shadow: 0 0 0 3px var(--red-dim) !important; }
        .form-label { font-size: 12.5px; font-weight: 600; color: var(--t2); margin-bottom: 6px; }
        .invalid-feedback { font-size: 11.5px; color: var(--red) !important; }
        textarea.form-control { resize: vertical; }
        .form-check-input { background-color: var(--surface-2) !important; border-color: var(--line-mid) !important; }
        .form-check-input:checked { background-color: var(--brand) !important; border-color: var(--brand) !important; }
        .form-check-input:focus { box-shadow: 0 0 0 3px var(--em-dim) !important; border-color: var(--brand) !important; }
        .form-check-label { color: var(--t1) !important; font-size: 13.5px; }
        .form-switch .form-check-input:checked { background-color: var(--green) !important; border-color: var(--green) !important; }
        .btn-check + .btn { background: var(--surface) !important; border: 1px solid var(--line-mid) !important; color: var(--t2) !important; }
        .btn-check:checked + .btn { background: var(--em-dim) !important; border-color: var(--brand) !important; color: var(--brand) !important; box-shadow: 0 0 0 1px var(--brand) inset; }
        .input-group-text { background: var(--surface-2) !important; border-color: var(--line-mid) !important; color: var(--t2) !important; }

        .nav-tabs { border-bottom: 1px solid var(--line) !important; gap: 2px; }
        .nav-tabs .nav-link { font-size: 13.5px; font-weight: 600; color: var(--t2) !important; border: 1px solid transparent !important; border-radius: 10px 10px 0 0; padding: 10px 16px; margin-bottom: -1px; }
        .nav-tabs .nav-link:hover { color: var(--t1) !important; background: var(--surface-2); }
        .nav-tabs .nav-link.active { color: var(--brand) !important; background: var(--surface) !important; border-color: var(--line) var(--line) var(--surface) !important; box-shadow: 0 -2px 0 var(--brand) inset; }

        .table { color: var(--t1); font-size: 13.5px; --bs-table-bg: transparent; }
        .table-responsive { border-radius: 0 0 var(--r-lg) var(--r-lg); }
        .table th { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--t3) !important; border-bottom: 1px solid var(--line) !important; padding: 11px 16px !important; background: var(--surface-2) !important; }
        .table td { padding: 13px 16px !important; border-bottom: 1px solid var(--line) !important; vertical-align: middle; background: transparent !important; color: var(--t1); }
        .table-hover tbody tr:hover td { background: var(--surface-2) !important; }

        .alert { border: 1px solid !important; border-radius: var(--r) !important; font-size: 13.5px; padding: 12px 15px !important; }
        .alert-success { background: var(--green-dim) !important; border-color: rgba(22, 163, 74, .25) !important; color: #15803D !important; }
        .alert-danger { background: var(--red-dim) !important; border-color: rgba(220, 38, 38, .25) !important; color: #B91C1C !important; }
        .alert-warning { background: var(--amber-dim) !important; border-color: rgba(180, 83, 9, .25) !important; color: #92400E !important; }
        .alert-info { background: var(--blue-dim) !important; border-color: rgba(37, 99, 235, .22) !important; color: #1D4ED8 !important; }

        .modal-content { background: var(--surface) !important; border: 1px solid var(--line-mid) !important; border-radius: var(--r-lg) !important; box-shadow: var(--shadow-lg) !important; }
        .modal-header { border-bottom: 1px solid var(--line) !important; padding: 16px 20px !important; }
        .modal-title { font-size: 15px; font-weight: 700; color: var(--t1); font-family: var(--font-head); }
        .modal-body { padding: 20px !important; }
        .modal-footer { border-top: 1px solid var(--line) !important; padding: 12px 20px !important; }
        .modal-backdrop { background: rgba(15, 23, 42, .5) !important; backdrop-filter: blur(3px); }

        .list-group-item { background: transparent !important; border-color: var(--line) !important; color: var(--t1) !important; padding: 14px 18px !important; font-size: 13.5px; }
        .list-group-flush .list-group-item:first-child { border-top: 0 !important; }
        .page-link { background: var(--surface) !important; border-color: var(--line) !important; color: var(--t2) !important; font-size: 13px; font-weight: 500; }
        .page-link:hover { background: var(--surface-2) !important; color: var(--t1) !important; }
        .page-item.active .page-link { background: var(--brand) !important; border-color: var(--brand) !important; color: #fff !important; }
        .page-item.disabled .page-link { opacity: .5; }
        hr { border-color: var(--line) !important; opacity: 1 !important; }
        code { font-size: 12px; background: var(--em-dim); padding: 2px 7px; border-radius: 5px; color: var(--brand); border: 1px solid var(--line); font-weight: 600; }
        .bg-light { background: var(--surface-2) !important; }
        .dropdown-menu { border: 1px solid var(--line-mid); border-radius: 12px; box-shadow: var(--shadow-lg); font-size: 13.5px; padding: 6px; background: var(--surface); }
        .dropdown-item { border-radius: 8px; padding: 8px 12px; color: var(--t1); }
        .dropdown-item:hover, .dropdown-item:focus { background: var(--surface-2); color: var(--t1); }
        .dropdown-item.active, .dropdown-item:active { background: var(--em-dim); color: var(--brand); }
        .dropdown-header { font-size: 11px; color: var(--t3); text-transform: uppercase; letter-spacing: .05em; }
        ::-webkit-scrollbar { width: 9px; height: 9px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--line-mid); border-radius: 99px; border: 2px solid var(--bg); }
        ::-webkit-scrollbar-thumb:hover { background: var(--t3); }

        /* ── Responsive ── */
        @media (max-width: 991px) {
            .sb { transform: translateX(-100%); box-shadow: var(--shadow-lg); }
            .sb.open { transform: translateX(0); }
            .main, body.sb-collapsed .main { margin-left: 0; }
            body.sb-collapsed .sb { width: var(--sb-w); }
            body.sb-collapsed .sb-brand-name, body.sb-collapsed .sb-section,
            body.sb-collapsed .sb-user-meta, body.sb-collapsed .sb-label { display: revert; }
            body.sb-collapsed .sb-link { justify-content: flex-start; padding: 10px 12px; }
            body.sb-collapsed .sb-logout { display: flex; }
            body.sb-collapsed .sb-logo-full { display: flex; }
            body.sb-collapsed .sb-logo-mark { display: none; }
            .sb-overlay.open { display: block; }
            .content { padding: 18px 15px; }
            .toast-shelf { width: calc(100vw - 32px); right: 16px; bottom: 16px; }
        }
        @media (max-width: 767px) {
            .topbar { padding: 0 14px; gap: 10px; }
            .tb-pill { display: none; }               /* pilule d'état : superflue sur mobile */
            .tb-title { font-size: 16px; }
            .tb-sub { display: none; }
            .card-body { padding: 15px !important; }
            .table { font-size: 13px; }
            .table td, .table th { padding: 8px 10px; }
            /* Boutons d'onglets / filtres : défilement horizontal plutôt qu'empilement */
            .d-flex.gap-2.flex-wrap.mb-4 { flex-wrap: nowrap !important; overflow-x: auto;
                padding-bottom: 6px; scrollbar-width: none; }
            .d-flex.gap-2.flex-wrap.mb-4::-webkit-scrollbar { display: none; }
            .d-flex.gap-2.flex-wrap.mb-4 .btn { white-space: nowrap; flex-shrink: 0; }
        }
    </style>

    @yield('css')
</head>

<body class="{{ !auth()->check() ? 'guest-mode' : '' }}">
    @auth
        <script>
            // Restaure l'état replié AVANT le rendu (évite le clignotement)
            if (localStorage.getItem('sbCollapsed') === '1') document.body.classList.add('sb-collapsed');
        </script>

        <div class="sb-overlay" id="sbOverlay"></div>
        <aside class="sb" id="sb">
            <div class="sb-head">
                <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('student.dashboard') }}"
                    class="sb-brand-link" title="Accueil">
                    <span class="sb-logo sb-logo-mark"><img src="{{ asset('assets/uk/logo-bg.png') }}" alt="UK"></span>
                    <span class="sb-logo-full"><img src="{{ asset('assets/uk/logo-sigle-bg.png') }}" alt="Université de Kara"></span>
                </a>
            </div>

            <nav class="sb-nav">
                @if (auth()->user()->isAdmin())
                    <div class="sb-section">Général</div>
                    <a href="{{ route('admin.dashboard') }}" class="sb-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" title="Vue d'ensemble">
                        <span class="sb-icon"><i class="fas fa-gauge-high"></i></span><span class="sb-label">Vue d'ensemble</span>
                    </a>
                    @can('statistiques.consulter')
                        <a href="{{ route('admin.statistiques.index') }}" class="sb-link {{ request()->routeIs('admin.statistiques.*') ? 'active' : '' }}" title="Statistiques">
                            <span class="sb-icon"><i class="fas fa-chart-column"></i></span><span class="sb-label">Statistiques</span>
                        </a>
                    @endcan
                    @can('archives.consulter')
                        <a href="{{ route('admin.archives.index') }}" class="sb-link {{ request()->routeIs('admin.archives.*') ? 'active' : '' }}" title="Archives numériques">
                            <span class="sb-icon"><i class="fas fa-box-archive"></i></span><span class="sb-label">Archives</span>
                        </a>
                    @endcan
                    @can('audit.consulter')
                        <a href="{{ route('admin.audit.index') }}" class="sb-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}" title="Journal d'audit">
                            <span class="sb-icon"><i class="fas fa-clipboard-list"></i></span><span class="sb-label">Journal d'audit</span>
                        </a>
                    @endcan

                    @php
                        $procIcons = ['depot'=>'fa-folder-open','attestation'=>'fa-certificate','diplome'=>'fa-graduation-cap','releve'=>'fa-list-alt','duplicat'=>'fa-id-card','homologation'=>'fa-stamp'];
                        $mesCategories = auth()->user()->treatableCategories();
                        // labels() est en cache permanent : aucune requête SQL ici
                        $reqProcs = collect(\App\Models\Procedure::labels())
                            ->only($mesCategories)->except('candidature');
                        $voitCandidatures = auth()->user()->can('candidature.traiter');
                    @endphp
                    @if ($reqProcs->isNotEmpty() || $voitCandidatures)
                        <div class="sb-section">Dépôts &amp; Demandes</div>
                        @foreach ($reqProcs as $procCode => $procNom)
                            <a href="{{ route('admin.requests.index', ['category' => $procCode]) }}"
                                class="sb-link {{ request()->routeIs('admin.requests.*') && request()->route('category') === $procCode ? 'active' : '' }}"
                                title="{{ $procNom }}">
                                <span class="sb-icon"><i class="fas {{ $procIcons[$procCode] ?? 'fa-file-lines' }}"></i></span><span class="sb-label">{{ $procNom }}</span>
                            </a>
                        @endforeach
                        @if ($voitCandidatures)
                            <a href="{{ route('admin.candidatures.index') }}" class="sb-link {{ request()->routeIs('admin.candidatures.*') ? 'active' : '' }}" title="Candidatures">
                                <span class="sb-icon"><i class="fas fa-file-signature"></i></span><span class="sb-label">Candidatures</span>
                            </a>
                        @endif
                    @endif

                    @php
                        // Un en-tête de section sans aucun lien en dessous fait
                        // croire à l'agent qu'il lui manque quelque chose.
                        $sectionConfig = auth()->user()->canAny([
                            'procedures.voir', 'parametres.gerer', 'types.voir',
                            'academique.voir', 'academique.gerer',
                            'annees.voir', 'annees.gerer',
                            'identites.voir', 'identites.importer',
                            'utilisateurs.voir', 'utilisateurs.gerer',
                            'perimetres.voir', 'perimetres.affecter',
                            'roles.voir', 'roles.gerer',
                            'paiements.voir', 'paiements.confirmer',
                            'audit.consulter', 'archives.consulter', 'statistiques.consulter',
                        ]) || auth()->user()->isSuperAdmin();
                    @endphp
                    @if ($sectionConfig)
                        <div class="sb-section">Configuration</div>
                    @endif
                    @canany(['procedures.voir', 'parametres.gerer'])
                        <a href="{{ route('admin.procedures.index') }}" class="sb-link {{ request()->routeIs('admin.procedures.*') ? 'active' : '' }}" title="Procédures">
                            <span class="sb-icon"><i class="fas fa-diagram-project"></i></span><span class="sb-label">Procédures</span>
                        </a>
                    @endcanany
                    @canany(['types.voir', 'parametres.gerer'])
                        <a href="{{ route('admin.request-types.index') }}" class="sb-link {{ request()->routeIs('admin.request-types.*') ? 'active' : '' }}" title="Types de documents">
                            <span class="sb-icon"><i class="fas fa-sliders"></i></span><span class="sb-label">Types de documents</span>
                        </a>
                    @endcanany
                    @canany(['academique.voir', 'academique.gerer'])
                        <a href="{{ route('admin.academique.index') }}" class="sb-link {{ request()->routeIs('admin.academique.*') ? 'active' : '' }}" title="Référentiel académique">
                            <span class="sb-icon"><i class="fas fa-sitemap"></i></span><span class="sb-label">Référentiel académique</span>
                        </a>
                    @endcanany
                    @canany(['annees.voir', 'annees.gerer'])
                        <a href="{{ route('admin.annees.index') }}" class="sb-link {{ request()->routeIs('admin.annees.*') ? 'active' : '' }}" title="Années académiques">
                            <span class="sb-icon"><i class="fas fa-calendar-days"></i></span><span class="sb-label">Années académiques</span>
                        </a>
                    @endcanany
                    @canany(['identites.voir', 'identites.importer'])
                        <a href="{{ route('admin.identites.index') }}" class="sb-link {{ request()->routeIs('admin.identites.*') ? 'active' : '' }}" title="Identités (import / export)">
                            <span class="sb-icon"><i class="fas fa-file-import"></i></span><span class="sb-label">Identités</span>
                        </a>
                    @endcanany
                    @canany(['utilisateurs.voir', 'utilisateurs.gerer'])
                        <a href="{{ route('admin.utilisateurs.index') }}" class="sb-link {{ request()->routeIs('admin.utilisateurs.*') ? 'active' : '' }}" title="Utilisateurs">
                            <span class="sb-icon"><i class="fas fa-users-gear"></i></span><span class="sb-label">Utilisateurs</span>
                        </a>
                    @endcanany
                    @canany(['perimetres.voir', 'perimetres.affecter', 'utilisateurs.gerer'])
                        <a href="{{ route('admin.perimetres.index') }}" class="sb-link {{ request()->routeIs('admin.perimetres.*') ? 'active' : '' }}" title="Périmètres agents">
                            <span class="sb-icon"><i class="fas fa-location-crosshairs"></i></span><span class="sb-label">Périmètres agents</span>
                        </a>
                    @endcanany
                    @if (\App\Models\Paiement::moduleActif())
                    @canany(['paiements.voir', 'paiements.confirmer'])
                        <a href="{{ route('admin.paiements.index') }}" class="sb-link {{ request()->routeIs('admin.paiements.*') ? 'active' : '' }}" title="Paiements">
                            <span class="sb-icon"><i class="fas fa-money-bill-transfer"></i></span><span class="sb-label">Paiements</span>
                        </a>
                    @endcanany
                    @endif
                    @canany(['roles.voir', 'roles.gerer'])
                        <a href="{{ route('admin.roles.index') }}" class="sb-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" title="Rôles & permissions">
                            <span class="sb-icon"><i class="fas fa-shield-halved"></i></span><span class="sb-label">Rôles &amp; permissions</span>
                        </a>
                    @endcanany
                    @if (auth()->user()->isSuperAdmin())
                        <a href="{{ route('superadmin.settings') }}" class="sb-link {{ request()->routeIs('superadmin.settings*') || request()->routeIs('superadmin.cachet*') ? 'active' : '' }}" title="Paramètres">
                            <span class="sb-icon"><i class="fas fa-gears"></i></span><span class="sb-label">Paramètres</span>
                        </a>
                        <a href="{{ route('superadmin.sauvegardes.index') }}" class="sb-link {{ request()->routeIs('superadmin.sauvegardes.*') ? 'active' : '' }}" title="Sauvegardes">
                            <span class="sb-icon"><i class="fas fa-box-archive"></i></span><span class="sb-label">Sauvegardes</span>
                        </a>
                    @endif

                    <div class="sb-section">Mon compte</div>
                    <a href="{{ route('admin.securite') }}" class="sb-link {{ request()->routeIs('admin.securite') ? 'active' : '' }}" title="Sécurité (2FA)">
                        <span class="sb-icon"><i class="fas fa-shield-halved"></i></span><span class="sb-label">Sécurité (2FA)</span>
                    </a>
                @else
                    <div class="sb-section">Mon espace</div>
                    <a href="{{ route('student.dashboard') }}" class="sb-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}" title="Mes demandes">
                        <span class="sb-icon"><i class="fas fa-folder-open"></i></span><span class="sb-label">Mes demandes</span>
                    </a>
                @endif
            </nav>

            <div class="sb-foot">
                <div class="sb-user">
                    <div class="sb-avatar">{{ strtoupper(substr(auth()->user()->numero_identifiant, 0, 2)) }}</div>
                    <div class="sb-user-meta">
                        <div class="sb-user-name">{{ auth()->user()->numero_identifiant }}</div>
                        <div class="sb-user-role">
                            {{ auth()->user()->isAdmin() ? 'Personnel' : (auth()->user()->type_etudiant === 'nouveau' ? 'Nouveau bachelier' : 'Ancien étudiant') }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" data-no-loading>
                        @csrf
                        <button type="submit" class="sb-logout" title="Se déconnecter"><i class="fas fa-arrow-right-from-bracket"></i></button>
                    </form>
                </div>
            </div>
        </aside>
    @endauth

    <div class="main">
        @auth
            <header class="topbar">
                <button class="tb-toggle" id="sbToggle" title="Réduire / agrandir le menu">
                    <i class="fas fa-bars" style="font-size:14px;"></i>
                </button>
                <div class="tb-heading">
                    <div class="tb-title">@yield('page-title', 'Tableau de bord')</div>
                    @hasSection('page-subtitle')
                        <div class="tb-sub">@yield('page-subtitle')</div>
                    @endif
                </div>
                <div class="tb-right">
                    @if (auth()->user()->isAdmin())
                        @php
                            $ctxAnnee = \App\Models\AnneeAcademique::context();
                            // Les années ARCHIVÉES sont retirées du sélecteur de contexte
                            // (elles restent consultables via les Archives numériques).
                            $allAnnees = \App\Models\AnneeAcademique::where('statut', '<>', 'archivee')
                                ->orderByDesc('libelle')->get();
                        @endphp
                        @if ($allAnnees->isNotEmpty())
                            <div class="dropdown">
                                <button class="tb-pill" data-bs-toggle="dropdown" style="cursor:pointer;">
                                    <i class="fas fa-calendar-days" style="color:var(--brand);"></i>
                                    <span>{{ $ctxAnnee?->libelle ?? 'Année' }}</span>
                                    <i class="fas fa-chevron-down" style="font-size:9px;color:var(--t3);"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><h6 class="dropdown-header">Année de consultation</h6></li>
                                    @foreach ($allAnnees as $an)
                                        <li>
                                            <form method="POST" action="{{ route('admin.contexte.annee') }}" data-no-loading>
                                                @csrf
                                                <input type="hidden" name="annee_id" value="{{ $an->id }}">
                                                <button type="submit" class="dropdown-item d-flex align-items-center justify-content-between {{ $ctxAnnee && $ctxAnnee->id === $an->id ? 'active' : '' }}">
                                                    <span>{{ $an->libelle }}</span>
                                                    @if ($an->is_active)<span class="badge bg-success ms-2">active</span>@endif
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endif
                    <div class="tb-pill"><i class="fas fa-circle"></i> {{ now()->translatedFormat('d M Y') }}</div>
                </div>
            </header>
        @endauth

        <div class="toast-shelf" id="toastShelf">
            @foreach ($errors->all() as $error)
                <div class="toast-pill toast-danger" data-ttl="6000">
                    <div class="toast-dot"></div>
                    <div class="toast-text"><div class="toast-title">Erreur</div><div class="toast-msg">{{ $error }}</div></div>
                    <button class="toast-x" onclick="this.closest('.toast-pill').remove()"><i class="fas fa-xmark"></i></button>
                </div>
            @endforeach
            @if (session('success'))
                <div class="toast-pill toast-success" data-ttl="5000">
                    <div class="toast-dot"></div>
                    <div class="toast-text"><div class="toast-title">Succès</div><div class="toast-msg">{{ session('success') }}</div></div>
                    <button class="toast-x" onclick="this.closest('.toast-pill').remove()"><i class="fas fa-xmark"></i></button>
                </div>
            @endif
            @if (session('info'))
                <div class="toast-pill toast-info" data-ttl="5000">
                    <div class="toast-dot"></div>
                    <div class="toast-text"><div class="toast-title">Information</div><div class="toast-msg">{{ session('info') }}</div></div>
                    <button class="toast-x" onclick="this.closest('.toast-pill').remove()"><i class="fas fa-xmark"></i></button>
                </div>
            @endif
        </div>

        <main class="content">
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            const body = document.body;
            const sb = document.getElementById('sb');
            const overlay = document.getElementById('sbOverlay');
            const toggle = document.getElementById('sbToggle');
            const isMobile = () => window.matchMedia('(max-width: 991px)').matches;

            toggle?.addEventListener('click', () => {
                if (isMobile()) {
                    const open = sb.classList.toggle('open');
                    overlay.classList.toggle('open', open);
                } else {
                    body.classList.toggle('sb-collapsed');
                    localStorage.setItem('sbCollapsed', body.classList.contains('sb-collapsed') ? '1' : '0');
                }
            });
            overlay?.addEventListener('click', () => { sb.classList.remove('open'); overlay.classList.remove('open'); });

            // Ferme le tiroir mobile après un clic sur un lien
            document.querySelectorAll('.sb-link').forEach(l => l.addEventListener('click', () => {
                if (isMobile()) { sb.classList.remove('open'); overlay.classList.remove('open'); }
            }));

            /* ── Le menu ne doit pas remonter en haut à chaque navigation ──
               Chaque clic charge une page entière : sans ça, le défilement
               du menu repart de zéro et il faut re-défiler pour retrouver
               l'onglet actif. On mémorise la position, et à défaut on centre
               directement sur le lien actif. */
            const sbNav = document.querySelector('.sb-nav');
            if (sbNav) {
                const CLE = 'sbNavScroll';

                document.querySelectorAll('.sb-nav .sb-link').forEach(l => {
                    l.addEventListener('click', () => {
                        try { sessionStorage.setItem(CLE, String(sbNav.scrollTop)); } catch (e) {}
                    });
                });

                let restaure = false;
                try {
                    const sauve = sessionStorage.getItem(CLE);
                    if (sauve !== null) {
                        sbNav.scrollTop = parseInt(sauve, 10) || 0;
                        restaure = true;
                    }
                } catch (e) {}

                if (!restaure) {
                    const actif = sbNav.querySelector('.sb-link.active');
                    if (actif) {
                        const cible = actif.offsetTop - sbNav.clientHeight / 2 + actif.clientHeight / 2;
                        sbNav.scrollTop = Math.max(0, cible);
                    }
                }
            }

            // Toasts auto-dismiss
            document.querySelectorAll('.toast-pill[data-ttl]').forEach(el => {
                const ttl = parseInt(el.dataset.ttl) || 5000;
                setTimeout(() => { el.style.transition = 'opacity .35s, transform .35s'; el.style.opacity = '0'; el.style.transform = 'translateY(10px)'; setTimeout(() => el.remove(), 360); }, ttl);
            });

            // Boutons « en attente » (feedback + anti double-clic)
            window.btnLoading = function (btn, text) {
                if (!btn || btn.dataset.loading) return;
                btn.dataset.loading = '1';
                btn.style.minWidth = btn.offsetWidth + 'px';
                btn.disabled = true;
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
