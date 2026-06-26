<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Accueil') — Université</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* ╔══════════════════════════════════════════════════════╗
           ║  TOKENS — Thème clair institutionnel                  ║
           ╚══════════════════════════════════════════════════════╝ */
        :root {
            --bg: #EEF1F7;
            --surface: #FFFFFF;
            --surface-2: #F5F7FB;
            --surface-3: #E9EEF6;
            --line: #E4E9F1;
            --line-mid: #D3DBE8;

            /* Accent marque (bleu marine institutionnel) — alias historique --em */
            --brand: #1B3A6B;
            --brand-700: #16335E;
            --em: #1B3A6B;
            --em-dim: rgba(27, 58, 107, .08);
            --em-glow: rgba(27, 58, 107, .16);

            /* Succès (vert) */
            --green: #15885A;
            --green-dim: rgba(21, 136, 90, .12);

            --amber: #B6791C;
            --amber-dim: rgba(182, 121, 28, .13);

            --red: #D23B4E;
            --red-dim: rgba(210, 59, 78, .10);

            --blue: #2563EB;
            --blue-dim: rgba(37, 99, 235, .10);

            --t1: #19222F;
            --t2: #51607A;
            --t3: #8A95A8;

            --font-head: 'Sora', sans-serif;
            --font-body: 'Inter', sans-serif;

            --r: 10px;
            --r-lg: 16px;

            --shadow-sm: 0 1px 2px rgba(16, 32, 64, .04), 0 1px 3px rgba(16, 32, 64, .06);
            --shadow: 0 4px 16px rgba(16, 32, 64, .06), 0 1px 3px rgba(16, 32, 64, .05);
            --shadow-lg: 0 18px 48px rgba(16, 32, 64, .14);

            --ease: cubic-bezier(.4, 0, .2, 1);
            --dur: .2s;

            --sb-collapsed: 66px;
            --sb-expanded: 248px;
            --topbar-h: 60px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-body);
            font-size: 14px;
            background: var(--bg);
            color: var(--t1);
            min-height: 100vh;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║  SIDEBAR                                              ║
           ╚══════════════════════════════════════════════════════╝ */
        .sb {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sb-collapsed);
            height: 100vh;
            background: var(--surface);
            border-right: 1px solid var(--line);
            display: flex;
            flex-direction: column;
            z-index: 300;
            transition: width var(--dur) var(--ease);
            overflow: hidden;
        }

        @media (min-width: 992px) {
            .sb:hover {
                width: var(--sb-expanded);
                box-shadow: var(--shadow-lg);
            }

            .sb:hover .sb-label,
            .sb:hover .sb-section,
            .sb:hover .sb-user-meta {
                opacity: 1;
                transform: translateX(0);
                pointer-events: auto;
            }

            .sb:hover .sb-brand-name {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .sb-label,
        .sb-section,
        .sb-brand-name,
        .sb-user-meta {
            opacity: 0;
            transform: translateX(-8px);
            pointer-events: none;
            transition: opacity var(--dur) var(--ease), transform var(--dur) var(--ease);
            white-space: nowrap;
        }

        .sb-brand {
            height: var(--topbar-h);
            display: flex;
            align-items: center;
            padding: 0 16px;
            gap: 13px;
            border-bottom: 1px solid var(--line);
            flex-shrink: 0;
            text-decoration: none;
        }

        .sb-logo {
            width: 34px;
            height: 34px;
            background: linear-gradient(135deg, var(--brand) 0%, #2C5494 100%);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 15px;
            flex-shrink: 0;
            box-shadow: 0 4px 12px var(--em-glow);
        }

        .sb-brand-name {
            font-family: var(--font-head);
            font-size: 16px;
            font-weight: 700;
            color: var(--t1);
            letter-spacing: -.01em;
        }

        .sb-nav {
            flex: 1;
            padding: 10px 0;
            overflow-y: auto;
            scrollbar-width: none;
        }

        .sb-nav::-webkit-scrollbar {
            display: none;
        }

        .sb-section {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--t3);
            padding: 14px 20px 6px;
        }

        .sb-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 10px 16px;
            color: var(--t2);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            position: relative;
            transition: color var(--dur), background var(--dur);
            overflow: hidden;
        }

        .sb-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 6px;
            bottom: 6px;
            width: 3px;
            border-radius: 0 3px 3px 0;
            background: var(--brand);
            transform: scaleY(0);
            transition: transform var(--dur) var(--ease);
            transform-origin: center;
        }

        .sb-link:hover {
            color: var(--t1);
            background: var(--surface-2);
        }

        .sb-link.active {
            color: var(--brand);
            background: var(--em-dim);
            font-weight: 600;
        }

        .sb-link.active::before {
            transform: scaleY(1);
        }

        .sb-icon {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }

        .sb-foot {
            border-top: 1px solid var(--line);
            padding: 12px;
            flex-shrink: 0;
        }

        .sb-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px;
            border-radius: var(--r);
        }

        .sb-avatar {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: var(--em-dim);
            border: 1px solid var(--line-mid);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            color: var(--brand);
            flex-shrink: 0;
            font-family: var(--font-head);
        }

        .sb-user-meta {
            flex: 1;
            min-width: 0;
            flex-shrink: 0;
        }

        .sb-user-name {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--t1);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sb-user-role {
            font-size: 10.5px;
            color: var(--t3);
            margin-top: 1px;
        }

        .sb-logout {
            background: none;
            border: none;
            color: var(--t3);
            cursor: pointer;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 7px;
            font-size: 12px;
            transition: all var(--dur);
            flex-shrink: 0;
        }

        .sb-logout:hover {
            background: var(--red-dim);
            color: var(--red);
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║  MAIN                                                 ║
           ╚══════════════════════════════════════════════════════╝ */
        .main {
            margin-left: var(--sb-collapsed);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left var(--dur) var(--ease);
        }

        .topbar {
            height: var(--topbar-h);
            background: rgba(255, 255, 255, .85);
            backdrop-filter: saturate(180%) blur(8px);
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            padding: 0 26px;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 200;
            flex-shrink: 0;
        }

        .tb-toggle {
            display: none;
            width: 36px;
            height: 36px;
            border: 1px solid var(--line-mid);
            background: var(--surface);
            border-radius: 8px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            color: var(--t2);
            transition: all var(--dur);
        }

        .tb-toggle:hover {
            background: var(--surface-2);
            color: var(--t1);
        }

        .tb-heading {
            flex: 1;
            min-width: 0;
        }

        .tb-title {
            font-family: var(--font-head);
            font-size: 18px;
            font-weight: 700;
            color: var(--t1);
            letter-spacing: -.02em;
            line-height: 1.2;
        }

        .tb-sub {
            font-size: 11.5px;
            color: var(--t3);
            margin-top: 2px;
        }

        .tb-right {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
        }

        .tb-pill {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 6px 13px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 100px;
            font-size: 12px;
            font-weight: 500;
            color: var(--t2);
        }

        .tb-pill i {
            font-size: 9px;
            color: var(--green);
        }

        .content {
            flex: 1;
            padding: 30px 32px;
            max-width: 1400px;
            width: 100%;
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║  TOASTS                                               ║
           ╚══════════════════════════════════════════════════════╝ */
        .toast-shelf {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 340px;
        }

        .toast-pill {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 13px 15px;
            background: var(--surface);
            border: 1px solid var(--line-mid);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            animation: riseUp .3s var(--ease) both;
            position: relative;
            overflow: hidden;
        }

        .toast-pill::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
        }

        .toast-success::after {
            background: var(--green);
        }

        .toast-danger::after {
            background: var(--red);
        }

        .toast-warning::after {
            background: var(--amber);
        }

        .toast-info::after {
            background: var(--blue);
        }

        .toast-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .toast-success .toast-dot {
            background: var(--green);
        }

        .toast-danger .toast-dot {
            background: var(--red);
        }

        .toast-warning .toast-dot {
            background: var(--amber);
        }

        .toast-info .toast-dot {
            background: var(--blue);
        }

        .toast-text {
            flex: 1;
            min-width: 0;
        }

        .toast-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--t1);
        }

        .toast-msg {
            font-size: 12px;
            color: var(--t2);
            margin-top: 2px;
            line-height: 1.4;
        }

        .toast-x {
            background: none;
            border: none;
            color: var(--t3);
            cursor: pointer;
            font-size: 11px;
            padding: 3px;
            transition: color var(--dur);
            flex-shrink: 0;
        }

        .toast-x:hover {
            color: var(--t1);
        }

        @keyframes riseUp {
            from {
                opacity: 0;
                transform: translateY(14px) scale(.97);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║  GUEST MODE (login / register)                        ║
           ╚══════════════════════════════════════════════════════╝ */
        .sb-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(16, 32, 64, .35);
            backdrop-filter: blur(3px);
            z-index: 299;
        }

        body.guest-mode {
            background: linear-gradient(160deg, #EEF2F9 0%, #E4EAF4 55%, #DCE6F3 100%);
        }

        body.guest-mode .sb,
        body.guest-mode .sb-overlay {
            display: none !important;
        }

        body.guest-mode .main {
            margin-left: 0;
        }

        body.guest-mode .topbar {
            display: none;
        }

        body.guest-mode .content {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
            position: relative;
            overflow: hidden;
            max-width: none;
        }

        body.guest-mode .content::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(27, 58, 107, .045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(27, 58, 107, .045) 1px, transparent 1px);
            background-size: 46px 46px;
            pointer-events: none;
            mask-image: radial-gradient(ellipse at center, black 10%, transparent 72%);
            -webkit-mask-image: radial-gradient(ellipse at center, black 10%, transparent 72%);
        }

        body.guest-mode .content::after {
            content: '';
            position: fixed;
            top: -220px;
            left: 50%;
            transform: translateX(-50%);
            width: 680px;
            height: 460px;
            background: radial-gradient(ellipse, rgba(27, 58, 107, .10) 0%, transparent 70%);
            pointer-events: none;
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║  BOOTSTRAP OVERRIDES                                  ║
           ╚══════════════════════════════════════════════════════╝ */
        .card {
            background: var(--surface) !important;
            border: 1px solid var(--line) !important;
            border-radius: var(--r-lg) !important;
            box-shadow: var(--shadow-sm) !important;
        }

        .card-header {
            background: transparent !important;
            border-bottom: 1px solid var(--line) !important;
            padding: 15px 20px !important;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--t1);
        }

        .card-header.bg-primary {
            background: var(--em-dim) !important;
            color: var(--brand) !important;
            border-bottom-color: var(--line) !important;
        }

        .card-header.bg-success {
            background: var(--green-dim) !important;
            color: var(--green) !important;
            border-bottom-color: rgba(21, 136, 90, .2) !important;
        }

        .card-header.bg-secondary {
            background: var(--surface-2) !important;
            border-bottom-color: var(--line) !important;
        }

        .card-header.bg-warning {
            background: var(--amber-dim) !important;
            color: var(--amber) !important;
            border-bottom-color: rgba(182, 121, 28, .2) !important;
        }

        .card-header.bg-info {
            background: var(--blue-dim) !important;
            color: var(--blue) !important;
            border-bottom-color: rgba(37, 99, 235, .2) !important;
        }

        .card-header.bg-danger {
            background: var(--red-dim) !important;
            color: var(--red) !important;
            border-bottom-color: rgba(210, 59, 78, .2) !important;
        }

        .card-body {
            padding: 20px !important;
        }

        .btn {
            font-family: var(--font-body);
            font-weight: 600;
            font-size: 13.5px;
            border-radius: 9px;
            transition: all var(--dur) var(--ease);
        }

        .btn-primary {
            background: var(--brand) !important;
            border-color: var(--brand) !important;
            color: #fff !important;
        }

        .btn-primary:hover {
            background: var(--brand-700) !important;
            border-color: var(--brand-700) !important;
            box-shadow: 0 6px 18px var(--em-glow) !important;
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--green) !important;
            border-color: var(--green) !important;
            color: #fff !important;
        }

        .btn-success:hover {
            background: #117049 !important;
            border-color: #117049 !important;
            box-shadow: 0 6px 18px rgba(21, 136, 90, .25) !important;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: var(--amber) !important;
            border-color: var(--amber) !important;
            color: #fff !important;
        }

        .btn-warning:hover {
            background: #9c6716 !important;
            color: #fff !important;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--red) !important;
            border-color: var(--red) !important;
            color: #fff !important;
        }

        .btn-danger:hover {
            background: #bb2f41 !important;
            border-color: #bb2f41 !important;
            color: #fff !important;
            transform: translateY(-1px);
        }

        .btn-info {
            background: var(--blue) !important;
            border-color: var(--blue) !important;
            color: #fff !important;
        }

        .btn-info:hover {
            background: #1d4fd0 !important;
            color: #fff !important;
        }

        .btn-secondary {
            background: var(--surface-3) !important;
            border-color: var(--line-mid) !important;
            color: var(--t2) !important;
        }

        .btn-outline-primary {
            background: transparent !important;
            border-color: var(--line-mid) !important;
            color: var(--brand) !important;
        }

        .btn-outline-primary:hover {
            background: var(--em-dim) !important;
            border-color: var(--brand) !important;
            color: var(--brand) !important;
        }

        .btn-outline-secondary {
            background: transparent !important;
            border-color: var(--line-mid) !important;
            color: var(--t2) !important;
        }

        .btn-outline-secondary:hover {
            background: var(--surface-2) !important;
            color: var(--t1) !important;
            border-color: var(--line-mid) !important;
        }

        .btn-outline-danger {
            background: transparent !important;
            border-color: rgba(210, 59, 78, .35) !important;
            color: var(--red) !important;
        }

        .btn-outline-danger:hover {
            background: var(--red) !important;
            color: #fff !important;
            border-color: var(--red) !important;
        }

        .btn-outline-warning {
            background: transparent !important;
            border-color: rgba(182, 121, 28, .4) !important;
            color: var(--amber) !important;
        }

        .btn-outline-warning:hover {
            background: var(--amber) !important;
            color: #fff !important;
        }

        .btn-outline-success {
            background: transparent !important;
            border-color: rgba(21, 136, 90, .4) !important;
            color: var(--green) !important;
        }

        .btn-outline-success:hover {
            background: var(--green) !important;
            color: #fff !important;
        }

        .btn:disabled {
            opacity: .5 !important;
            pointer-events: none;
        }

        .badge {
            font-family: var(--font-body);
            font-weight: 600;
            font-size: 11px;
            letter-spacing: .01em;
            border-radius: 6px;
            padding: 4px 9px !important;
        }

        .bg-primary {
            background: var(--em-dim) !important;
            color: var(--brand) !important;
        }

        .bg-success {
            background: var(--green-dim) !important;
            color: var(--green) !important;
        }

        .bg-danger {
            background: var(--red-dim) !important;
            color: var(--red) !important;
        }

        .bg-warning {
            background: var(--amber-dim) !important;
            color: var(--amber) !important;
        }

        .bg-secondary {
            background: var(--surface-3) !important;
            color: var(--t2) !important;
        }

        .bg-info {
            background: var(--blue-dim) !important;
            color: var(--blue) !important;
        }

        .text-success {
            color: var(--green) !important;
        }

        .text-danger {
            color: var(--red) !important;
        }

        .text-warning {
            color: var(--amber) !important;
        }

        .text-info {
            color: var(--blue) !important;
        }

        .text-muted {
            color: var(--t2) !important;
        }

        .text-primary {
            color: var(--brand) !important;
        }

        .text-white {
            color: var(--t1) !important;
        }

        /* Icônes-pastilles (icon-box) restent colorées sur fond plein */
        .icon-box.bg-primary {
            background: var(--brand) !important;
            color: #fff !important;
        }

        .icon-box.text-white {
            color: #fff !important;
        }

        .form-control,
        .form-select {
            font-family: var(--font-body);
            font-size: 13.5px;
            background: var(--surface) !important;
            border: 1px solid var(--line-mid) !important;
            border-radius: 9px !important;
            color: var(--t1) !important;
            padding: 10px 13px !important;
            transition: border-color var(--dur), box-shadow var(--dur);
        }

        .form-control::placeholder {
            color: var(--t3) !important;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--brand) !important;
            box-shadow: 0 0 0 3px var(--em-dim) !important;
            outline: none;
            background: #fff !important;
        }

        .form-control.is-invalid {
            border-color: var(--red) !important;
            box-shadow: 0 0 0 3px var(--red-dim) !important;
        }

        .form-label {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--t2);
            margin-bottom: 6px;
        }

        .invalid-feedback {
            font-size: 11.5px;
            color: var(--red) !important;
        }

        textarea.form-control {
            resize: vertical;
        }

        .form-check-input {
            background-color: var(--surface-2) !important;
            border-color: var(--line-mid) !important;
        }

        .form-check-input:checked {
            background-color: var(--brand) !important;
            border-color: var(--brand) !important;
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 3px var(--em-dim) !important;
            border-color: var(--brand) !important;
        }

        .form-check-label {
            color: var(--t1) !important;
            font-size: 13.5px;
        }

        .form-switch .form-check-input:checked {
            background-color: var(--green) !important;
            border-color: var(--green) !important;
        }

        .btn-check+.btn {
            background: var(--surface) !important;
            border: 1px solid var(--line-mid) !important;
            color: var(--t2) !important;
        }

        .btn-check:checked+.btn {
            background: var(--em-dim) !important;
            border-color: var(--brand) !important;
            color: var(--brand) !important;
            box-shadow: 0 0 0 1px var(--brand) inset;
        }

        .input-group-text {
            background: var(--surface-2) !important;
            border-color: var(--line-mid) !important;
            color: var(--t2) !important;
        }

        /* ── Onglets ── */
        .nav-tabs {
            border-bottom: 1px solid var(--line) !important;
            gap: 2px;
        }

        .nav-tabs .nav-link {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--t2) !important;
            border: 1px solid transparent !important;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
            padding: 10px 16px;
            margin-bottom: -1px;
            transition: all var(--dur);
        }

        .nav-tabs .nav-link:hover {
            color: var(--t1) !important;
            background: var(--surface-2);
            border-color: transparent !important;
        }

        .nav-tabs .nav-link.active {
            color: var(--brand) !important;
            background: var(--surface) !important;
            border-color: var(--line) var(--line) var(--surface) !important;
            box-shadow: 0 -2px 0 var(--brand) inset;
        }

        .table {
            color: var(--t1);
            font-size: 13.5px;
            --bs-table-bg: transparent;
        }

        .table-responsive {
            border-radius: 0 0 var(--r-lg) var(--r-lg);
        }

        .table th {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--t3) !important;
            border-bottom: 1px solid var(--line) !important;
            padding: 11px 16px !important;
            background: var(--surface-2) !important;
        }

        .table td {
            padding: 13px 16px !important;
            border-bottom: 1px solid var(--line) !important;
            vertical-align: middle;
            background: transparent !important;
            color: var(--t1);
        }

        .table-hover tbody tr:hover td {
            background: var(--surface-2) !important;
        }

        .alert {
            border: 1px solid !important;
            border-radius: var(--r) !important;
            font-size: 13.5px;
            padding: 12px 15px !important;
        }

        .alert-success {
            background: var(--green-dim) !important;
            border-color: rgba(21, 136, 90, .25) !important;
            color: #0f6f49 !important;
        }

        .alert-danger {
            background: var(--red-dim) !important;
            border-color: rgba(210, 59, 78, .25) !important;
            color: #b32f41 !important;
        }

        .alert-warning {
            background: var(--amber-dim) !important;
            border-color: rgba(182, 121, 28, .25) !important;
            color: #9a6717 !important;
        }

        .alert-info {
            background: var(--blue-dim) !important;
            border-color: rgba(37, 99, 235, .22) !important;
            color: #1d4fd0 !important;
        }

        .modal-content {
            background: var(--surface) !important;
            border: 1px solid var(--line-mid) !important;
            border-radius: var(--r-lg) !important;
            box-shadow: var(--shadow-lg) !important;
        }

        .modal-header {
            border-bottom: 1px solid var(--line) !important;
            padding: 16px 20px !important;
        }

        .modal-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--t1);
            font-family: var(--font-head);
        }

        .modal-body {
            padding: 20px !important;
        }

        .modal-footer {
            border-top: 1px solid var(--line) !important;
            padding: 12px 20px !important;
        }

        .modal-backdrop {
            background: rgba(16, 32, 64, .45) !important;
            backdrop-filter: blur(3px);
        }

        .list-group-item {
            background: transparent !important;
            border-color: var(--line) !important;
            color: var(--t1) !important;
            padding: 14px 18px !important;
            font-size: 13.5px;
        }

        .list-group-flush .list-group-item:first-child {
            border-top: 0 !important;
        }

        .page-link {
            background: var(--surface) !important;
            border-color: var(--line) !important;
            color: var(--t2) !important;
            font-size: 13px;
            font-weight: 500;
        }

        .page-link:hover {
            background: var(--surface-2) !important;
            color: var(--t1) !important;
        }

        .page-item.active .page-link {
            background: var(--brand) !important;
            border-color: var(--brand) !important;
            color: #fff !important;
        }

        .page-item.disabled .page-link {
            opacity: .5;
            background: var(--surface) !important;
        }

        hr {
            border-color: var(--line) !important;
            opacity: 1 !important;
        }

        code {
            font-size: 12px;
            background: var(--em-dim);
            padding: 2px 7px;
            border-radius: 5px;
            color: var(--brand);
            border: 1px solid var(--line);
            font-weight: 600;
        }

        .bg-light {
            background: var(--surface-2) !important;
        }

        ::-webkit-scrollbar {
            width: 9px;
            height: 9px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--line-mid);
            border-radius: 99px;
            border: 2px solid var(--bg);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--t3);
        }

        .icon-box {
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            flex-shrink: 0;
        }

        /* ╔══════════════════════════════════════════════════════╗
           ║  RESPONSIVE                                           ║
           ╚══════════════════════════════════════════════════════╝ */
        @media (max-width: 991px) {
            .sb {
                width: var(--sb-expanded);
                transform: translateX(calc(-1 * var(--sb-expanded)));
                box-shadow: var(--shadow-lg);
            }

            .sb.open {
                transform: translateX(0);
            }

            .sb-label,
            .sb-section,
            .sb-brand-name,
            .sb-user-meta {
                opacity: 1;
                transform: none;
                pointer-events: auto;
            }

            .sb-overlay.open {
                display: block;
            }

            .main {
                margin-left: 0;
            }

            .tb-toggle {
                display: flex;
            }

            .content {
                padding: 18px 16px;
            }

            .toast-shelf {
                width: calc(100vw - 32px);
                right: 16px;
                bottom: 16px;
            }
        }
    </style>

    @yield('css')
</head>

<body class="{{ !auth()->check() ? 'guest-mode' : '' }}">

    @auth
        <div class="sb-overlay" id="sbOverlay"></div>
        <aside class="sb" id="sb">

            <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('student.dashboard') }}"
                class="sb-brand">
                <div class="sb-logo"><i class="fas fa-graduation-cap"></i></div>
                <span class="sb-brand-name">Université</span>
            </a>

            <nav class="sb-nav">
                @if (auth()->user()->isAdmin())
                    <div class="sb-section">Tableau de bord</div>

                    <a href="{{ route('admin.dashboard') }}"
                        class="sb-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <span class="sb-icon"><i class="fas fa-gauge-high"></i></span>
                        <span class="sb-label">Vue d'ensemble</span>
                    </a>

                    <div class="sb-section" style="margin-top:8px;">Dépôts &amp; Demandes</div>

                    <a href="{{ route('admin.requests.index', ['category' => 'depot']) }}"
                        class="sb-link {{ request()->routeIs('admin.requests.*') && request()->route('category') === 'depot' ? 'active' : '' }}">
                        <span class="sb-icon"><i class="fas fa-folder-open"></i></span>
                        <span class="sb-label">Dépôts</span>
                    </a>

                    <a href="{{ route('admin.requests.index', ['category' => 'attestation']) }}"
                        class="sb-link {{ request()->route('category') === 'attestation' ? 'active' : '' }}">
                        <span class="sb-icon"><i class="fas fa-certificate"></i></span>
                        <span class="sb-label">Attestations</span>
                    </a>

                    <a href="{{ route('admin.requests.index', ['category' => 'diplome']) }}"
                        class="sb-link {{ request()->route('category') === 'diplome' ? 'active' : '' }}">
                        <span class="sb-icon"><i class="fas fa-graduation-cap"></i></span>
                        <span class="sb-label">Diplômes</span>
                    </a>

                    <a href="{{ route('admin.requests.index', ['category' => 'releve']) }}"
                        class="sb-link {{ request()->route('category') === 'releve' ? 'active' : '' }}">
                        <span class="sb-icon"><i class="fas fa-list-alt"></i></span>
                        <span class="sb-label">Relevés de notes</span>
                    </a>

                    <a href="{{ route('admin.requests.index', ['category' => 'duplicat']) }}"
                        class="sb-link {{ request()->route('category') === 'duplicat' ? 'active' : '' }}">
                        <span class="sb-icon"><i class="fas fa-id-card"></i></span>
                        <span class="sb-label">Duplicatas carte</span>
                    </a>

                    <div class="sb-section" style="margin-top:8px;">Configuration</div>

                    <a href="{{ route('admin.request-types.index') }}"
                        class="sb-link {{ request()->routeIs('admin.request-types.*') ? 'active' : '' }}">
                        <span class="sb-icon"><i class="fas fa-sliders"></i></span>
                        <span class="sb-label">Types de documents</span>
                    </a>
                @else
                    <div class="sb-section">Mon espace</div>

                    <a href="{{ route('student.dashboard') }}"
                        class="sb-link {{ request()->routeIs('student.dashboard') ? 'active' : '' }}">
                        <span class="sb-icon"><i class="fas fa-folder-open"></i></span>
                        <span class="sb-label">Mes demandes</span>
                    </a>
                @endif
            </nav>

            <div class="sb-foot">
                <div class="sb-user">
                    <div class="sb-avatar">{{ strtoupper(substr(auth()->user()->numero_identifiant, 0, 2)) }}</div>
                    <div class="sb-user-meta">
                        <div class="sb-user-name">{{ auth()->user()->numero_identifiant }}</div>
                        <div class="sb-user-role">
                            {{ auth()->user()->isAdmin() ? 'Administrateur' : (auth()->user()->type_etudiant === 'nouveau' ? 'Nouveau bachelier' : 'Ancien étudiant') }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" style="margin-left:auto;" data-no-loading>
                        @csrf
                        <button type="submit" class="sb-logout" title="Se déconnecter">
                            <i class="fas fa-arrow-right-from-bracket"></i>
                        </button>
                    </form>
                </div>
            </div>

        </aside>
    @endauth

    <div class="main">

        @auth
            <header class="topbar">
                <button class="tb-toggle" id="sbToggle">
                    <i class="fas fa-bars" style="font-size:13px;"></i>
                </button>
                <div class="tb-heading">
                    <div class="tb-title">@yield('page-title', 'Tableau de bord')</div>
                    @hasSection('page-subtitle')
                        <div class="tb-sub">@yield('page-subtitle')</div>
                    @endif
                </div>
                <div class="tb-right">
                    <div class="tb-pill">
                        <i class="fas fa-circle"></i>
                        {{ now()->translatedFormat('d M Y') }}
                    </div>
                </div>
            </header>
        @endauth

        <div class="toast-shelf" id="toastShelf">
            @foreach ($errors->all() as $error)
                <div class="toast-pill toast-danger" data-ttl="6000">
                    <div class="toast-dot"></div>
                    <div class="toast-text">
                        <div class="toast-title">Erreur</div>
                        <div class="toast-msg">{{ $error }}</div>
                    </div>
                    <button class="toast-x" onclick="this.closest('.toast-pill').remove()"><i
                            class="fas fa-xmark"></i></button>
                </div>
            @endforeach

            @if (session('success'))
                <div class="toast-pill toast-success" data-ttl="5000">
                    <div class="toast-dot"></div>
                    <div class="toast-text">
                        <div class="toast-title">Succès</div>
                        <div class="toast-msg">{{ session('success') }}</div>
                    </div>
                    <button class="toast-x" onclick="this.closest('.toast-pill').remove()"><i
                            class="fas fa-xmark"></i></button>
                </div>
            @endif

            @if (session('info'))
                <div class="toast-pill toast-info" data-ttl="5000">
                    <div class="toast-dot"></div>
                    <div class="toast-text">
                        <div class="toast-title">Information</div>
                        <div class="toast-msg">{{ session('info') }}</div>
                    </div>
                    <button class="toast-x" onclick="this.closest('.toast-pill').remove()"><i
                            class="fas fa-xmark"></i></button>
                </div>
            @endif
        </div>

        <main class="content">
            @yield('content')
        </main>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function() {
            const sb = document.getElementById('sb');
            const overlay = document.getElementById('sbOverlay');
            const toggle = document.getElementById('sbToggle');
            const open = () => {
                sb?.classList.add('open');
                overlay?.classList.add('open');
            };
            const close = () => {
                sb?.classList.remove('open');
                overlay?.classList.remove('open');
            };
            toggle?.addEventListener('click', () => sb?.classList.contains('open') ? close() : open());
            overlay?.addEventListener('click', close);

            document.querySelectorAll('.toast-pill[data-ttl]').forEach(el => {
                const ttl = parseInt(el.dataset.ttl) || 5000;
                setTimeout(() => {
                    el.style.transition = 'opacity .35s, transform .35s';
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(10px)';
                    setTimeout(() => el.remove(), 360);
                }, ttl);
            });

            /* ── Boutons « en attente » : feedback + anti double-clic ──
               Passe n'importe quel bouton en mode chargement (spinner + désactivé).
               Réutilisable depuis n'importe quelle vue via window.btnLoading(). */
            window.btnLoading = function(btn, text) {
                if (!btn || btn.dataset.loading) return;
                btn.dataset.loading = '1';
                btn.style.minWidth = btn.offsetWidth + 'px';
                btn.disabled = true;
                btn.innerHTML =
                    '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="vertical-align:-2px;"></span> ' +
                    (text || 'Veuillez patienter…');
            };

            /* Applique automatiquement l'état « en attente » à la soumission
               de TOUT formulaire (sauf opt-out via data-no-loading). */
            document.addEventListener('submit', function(e) {
                if (e.defaultPrevented) return; // confirm() annulé, validation échouée…
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
