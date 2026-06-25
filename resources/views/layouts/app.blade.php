<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dépôt') — Université</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* ╔══════════════════════════════════════════════════════╗
       ║  TOKENS                                              ║
       ╚══════════════════════════════════════════════════════╝ */
        :root {
            --bg: #0C0E14;
            --surface: #13161F;
            --surface-2: #191C27;
            --surface-3: #1F2333;
            --line: rgba(255, 255, 255, .07);
            --line-mid: rgba(255, 255, 255, .11);

            --em: #10C97A;
            --em-dim: rgba(16, 201, 122, .15);
            --em-glow: rgba(16, 201, 122, .30);

            --amber: #F59E0B;
            --amber-dim: rgba(245, 158, 11, .13);

            --red: #F43F5E;
            --red-dim: rgba(244, 63, 94, .13);

            --blue: #3B82F6;
            --blue-dim: rgba(59, 130, 246, .13);

            --t1: #EDEEF2;
            --t2: #9BA3B5;
            --t3: #5C657A;

            --font-head: 'Syne', sans-serif;
            --font-body: 'Plus Jakarta Sans', sans-serif;

            --r: 10px;
            --r-lg: 16px;

            --ease: cubic-bezier(.4, 0, .2, 1);
            --dur: .22s;

            --sb-collapsed: 64px;
            --sb-expanded: 232px;
            --topbar-h: 56px;
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
       ║  SIDEBAR                                             ║
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

        .sb::before {
            content: '';
            position: absolute;
            top: -60px;
            left: -40px;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, var(--em-glow) 0%, transparent 70%);
            pointer-events: none;
            opacity: .6;
        }

        @media (min-width: 992px) {
            .sb:hover {
                width: var(--sb-expanded);
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
            padding: 0 17px;
            gap: 12px;
            border-bottom: 1px solid var(--line);
            flex-shrink: 0;
            text-decoration: none;
        }

        .sb-logo {
            width: 30px;
            height: 30px;
            background: var(--em);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--bg);
            font-size: 14px;
            flex-shrink: 0;
            box-shadow: 0 0 16px var(--em-glow);
        }

        .sb-brand-name {
            font-family: var(--font-head);
            font-size: 15px;
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
            font-size: 9.5px;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--t3);
            padding: 14px 20px 5px;
        }

        .sb-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 9px 17px;
            color: var(--t2);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 400;
            position: relative;
            transition: color var(--dur), background var(--dur);
            overflow: hidden;
        }

        .sb-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--em);
            transform: scaleY(0);
            transition: transform var(--dur) var(--ease);
            transform-origin: center;
        }

        .sb-link:hover {
            color: var(--t1);
            background: rgba(255, 255, 255, .04);
        }

        .sb-link.active {
            color: var(--em);
            background: var(--em-dim);
            font-weight: 500;
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
            padding: 8px;
            border-radius: var(--r);
        }

        .sb-avatar {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: var(--surface-3);
            border: 1px solid var(--line-mid);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            color: var(--em);
            flex-shrink: 0;
            font-family: var(--font-head);
        }

        .sb-user-meta {
            flex: 1;
            min-width: 0;
            flex-shrink: 0;
        }

        .sb-user-name {
            font-size: 12px;
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
            width: 26px;
            height: 26px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: 11px;
            transition: all var(--dur);
            flex-shrink: 0;
        }

        .sb-logout:hover {
            background: var(--red-dim);
            color: var(--red);
        }

        /* ╔══════════════════════════════════════════════════════╗
       ║  MAIN                                                ║
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
            background: var(--surface);
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            padding: 0 24px;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 200;
            flex-shrink: 0;
        }

        .tb-toggle {
            display: none;
            width: 34px;
            height: 34px;
            border: 1px solid var(--line-mid);
            background: var(--surface-2);
            border-radius: 8px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
            color: var(--t2);
            transition: all var(--dur);
        }

        .tb-toggle:hover {
            background: var(--surface-3);
            color: var(--t1);
        }

        .tb-heading {
            flex: 1;
        }

        .tb-title {
            font-family: var(--font-head);
            font-size: 17px;
            color: var(--t1);
            letter-spacing: -.02em;
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
            padding: 5px 12px;
            background: var(--surface-2);
            border: 1px solid var(--line);
            border-radius: 100px;
            font-size: 12px;
            color: var(--t3);
        }

        .tb-pill i {
            font-size: 10px;
            color: var(--em);
        }

        .content {
            flex: 1;
            padding: 28px 30px;
        }

        /* ╔══════════════════════════════════════════════════════╗
       ║  TOASTS                                              ║
       ╚══════════════════════════════════════════════════════╝ */
        .toast-shelf {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 320px;
        }

        .toast-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            background: var(--surface-2);
            border: 1px solid var(--line-mid);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .45);
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
            height: 2px;
        }

        .toast-success::after {
            background: var(--em);
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
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .toast-success .toast-dot {
            background: var(--em);
            box-shadow: 0 0 8px var(--em-glow);
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
       ║  GUEST MODE (login / register)                       ║
       ╚══════════════════════════════════════════════════════╝ */
        .sb-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .7);
            backdrop-filter: blur(4px);
            z-index: 299;
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
        }

        body.guest-mode .content::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: linear-gradient(var(--line) 1px, transparent 1px), linear-gradient(90deg, var(--line) 1px, transparent 1px);
            background-size: 48px 48px;
            pointer-events: none;
            mask-image: radial-gradient(ellipse at center, black 20%, transparent 75%);
        }

        body.guest-mode .content::after {
            content: '';
            position: fixed;
            top: -200px;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 400px;
            background: radial-gradient(ellipse, rgba(16, 201, 122, .12) 0%, transparent 70%);
            pointer-events: none;
        }

        /* ╔══════════════════════════════════════════════════════╗
       ║  COMPOSANTS BOOTSTRAP OVERRIDES                      ║
       ╚══════════════════════════════════════════════════════╝ */
        .card {
            background: var(--surface) !important;
            border: 1px solid var(--line) !important;
            border-radius: var(--r-lg) !important;
            box-shadow: none !important;
        }

        .card-header {
            background: transparent !important;
            border-bottom: 1px solid var(--line) !important;
            padding: 16px 20px !important;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--t1);
        }

        .card-header.bg-primary {
            background: var(--surface-2) !important;
            border-bottom-color: var(--line-mid) !important;
        }

        .card-header.bg-success {
            background: var(--em-dim) !important;
            color: var(--em) !important;
            border-bottom-color: rgba(16, 201, 122, .2) !important;
        }

        .card-header.bg-secondary {
            background: var(--surface-3) !important;
            border-bottom-color: var(--line-mid) !important;
        }

        .card-body {
            padding: 20px !important;
        }

        .btn {
            font-family: var(--font-body);
            font-weight: 500;
            font-size: 13.5px;
            border-radius: 8px;
            transition: all var(--dur) var(--ease);
        }

        .btn-primary {
            background: var(--em) !important;
            border-color: var(--em) !important;
            color: var(--bg) !important;
            font-weight: 600;
        }

        .btn-primary:hover {
            background: #0db86c !important;
            border-color: #0db86c !important;
            box-shadow: 0 0 20px var(--em-glow) !important;
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--em) !important;
            border-color: var(--em) !important;
            color: var(--bg) !important;
            font-weight: 600;
        }

        .btn-success:hover {
            background: #0db86c !important;
            box-shadow: 0 0 20px var(--em-glow) !important;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: var(--red-dim) !important;
            border-color: rgba(244, 63, 94, .3) !important;
            color: var(--red) !important;
            font-weight: 600;
        }

        .btn-danger:hover {
            background: var(--red) !important;
            border-color: var(--red) !important;
            color: #fff !important;
            transform: translateY(-1px);
        }

        .btn-outline-primary {
            background: transparent !important;
            border-color: rgba(16, 201, 122, .4) !important;
            color: var(--em) !important;
        }

        .btn-outline-primary:hover {
            background: var(--em-dim) !important;
            border-color: var(--em) !important;
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
            border-color: rgba(244, 63, 94, .3) !important;
            color: var(--red) !important;
        }

        .btn-outline-danger:hover {
            background: var(--red) !important;
            color: #fff !important;
            border-color: var(--red) !important;
        }

        .btn:disabled {
            opacity: .35 !important;
            pointer-events: none;
        }

        .badge {
            font-family: var(--font-body);
            font-weight: 500;
            font-size: 11px;
            letter-spacing: .03em;
            border-radius: 6px;
            padding: 3px 9px !important;
        }

        .bg-primary {
            background: rgba(59, 130, 246, .2) !important;
            color: #60a5fa !important;
        }

        .bg-success {
            background: var(--em-dim) !important;
            color: var(--em) !important;
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
            background: rgba(255, 255, 255, .08) !important;
            color: var(--t2) !important;
        }

        .bg-info {
            background: rgba(59, 130, 246, .15) !important;
            color: #93c5fd !important;
        }

        .text-success {
            color: var(--em) !important;
        }

        .text-danger {
            color: var(--red) !important;
        }

        .text-warning {
            color: var(--amber) !important;
        }

        .text-muted {
            color: var(--t2) !important;
        }

        .text-primary {
            color: var(--em) !important;
        }

        .text-white {
            color: var(--t1) !important;
        }

        .form-control,
        .form-select {
            font-family: var(--font-body);
            font-size: 13.5px;
            background: var(--surface-2) !important;
            border: 1px solid var(--line-mid) !important;
            border-radius: 8px !important;
            color: var(--t1) !important;
            padding: 9px 13px !important;
            transition: border-color var(--dur), box-shadow var(--dur);
        }

        .form-control::placeholder {
            color: var(--t3) !important;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--em) !important;
            box-shadow: 0 0 0 3px var(--em-dim) !important;
            outline: none;
            background: var(--surface-3) !important;
        }

        .form-control.is-invalid {
            border-color: var(--red) !important;
            box-shadow: 0 0 0 3px var(--red-dim) !important;
        }

        .form-label {
            font-size: 12.5px;
            font-weight: 500;
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
            background-color: var(--surface-3) !important;
            border-color: var(--line-mid) !important;
        }

        .form-check-input:checked {
            background-color: var(--em) !important;
            border-color: var(--em) !important;
        }

        .form-check-label {
            color: var(--t1) !important;
            font-size: 13.5px;
        }

        .form-switch .form-check-input:checked {
            background-color: var(--em) !important;
        }

        .btn-check+.btn {
            background: var(--surface-2) !important;
            border-color: var(--line-mid) !important;
            color: var(--t2) !important;
        }

        .btn-check:checked+.btn {
            background: var(--em-dim) !important;
            border-color: var(--em) !important;
            color: var(--em) !important;
        }

        .input-group-text {
            background: var(--surface-3) !important;
            border-color: var(--line-mid) !important;
            color: var(--t2) !important;
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
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--t3) !important;
            border-bottom: 1px solid var(--line) !important;
            padding: 10px 16px !important;
            background: var(--surface-2) !important;
        }

        .table td {
            padding: 13px 16px !important;
            border-bottom: 1px solid var(--line) !important;
            vertical-align: middle;
            background: transparent !important;
        }

        .table-hover tbody tr:hover td {
            background: rgba(255, 255, 255, .025) !important;
        }

        .alert {
            border: 1px solid !important;
            border-radius: var(--r) !important;
            font-size: 13.5px;
            padding: 11px 14px !important;
        }

        .alert-success {
            background: var(--em-dim) !important;
            border-color: rgba(16, 201, 122, .25) !important;
            color: var(--em) !important;
        }

        .alert-danger {
            background: var(--red-dim) !important;
            border-color: rgba(244, 63, 94, .25) !important;
            color: var(--red) !important;
        }

        .alert-warning {
            background: var(--amber-dim) !important;
            border-color: rgba(245, 158, 11, .25) !important;
            color: var(--amber) !important;
        }

        .alert-info {
            background: var(--blue-dim) !important;
            border-color: rgba(59, 130, 246, .25) !important;
            color: #60a5fa !important;
        }

        .modal-content {
            background: var(--surface) !important;
            border: 1px solid var(--line-mid) !important;
            border-radius: var(--r-lg) !important;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .6) !important;
        }

        .modal-header {
            border-bottom: 1px solid var(--line) !important;
            padding: 16px 20px !important;
        }

        .modal-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--t1);
        }

        .btn-close {
            filter: invert(1) opacity(.5);
        }

        .btn-close:hover {
            filter: invert(1) opacity(.9);
        }

        .modal-body {
            padding: 20px !important;
        }

        .modal-footer {
            border-top: 1px solid var(--line) !important;
            padding: 12px 20px !important;
        }

        .modal-backdrop {
            background: rgba(0, 0, 0, .7) !important;
            backdrop-filter: blur(4px);
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
            background: var(--surface-2) !important;
            border-color: var(--line) !important;
            color: var(--t2) !important;
            font-size: 13px;
        }

        .page-link:hover {
            background: var(--surface-3) !important;
            color: var(--t1) !important;
        }

        .page-item.active .page-link {
            background: var(--em) !important;
            border-color: var(--em) !important;
            color: var(--bg) !important;
        }

        .page-item.disabled .page-link {
            opacity: .35;
        }

        hr {
            border-color: var(--line) !important;
            opacity: 1 !important;
        }

        code {
            font-size: 12px;
            background: var(--surface-2);
            padding: 2px 7px;
            border-radius: 5px;
            color: var(--em);
            border: 1px solid var(--line);
        }

        .bg-light {
            background: var(--surface-2) !important;
        }

        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--line-mid);
            border-radius: 99px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--t3);
        }

        .icon-box {
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            flex-shrink: 0;
        }

        /* ╔══════════════════════════════════════════════════════╗
       ║  RESPONSIVE                                          ║
       ╚══════════════════════════════════════════════════════╝ */
        @media (max-width: 991px) {
            .sb {
                width: var(--sb-expanded);
                transform: translateX(calc(-1 * var(--sb-expanded)));
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
                <span class="sb-brand-name">Dépôt définitif</span>
            </a>

            <nav class="sb-nav">
                @if (auth()->user()->isAdmin())
                    <div class="sb-section">Tableau de bord</div>

                    <a href="{{ route('admin.dashboard') }}"
                        class="sb-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <span class="sb-icon"><i class="fas fa-gauge-high"></i></span>
                        <span class="sb-label">Vue d'ensemble</span>
                    </a>

                    <div class="sb-section" style="margin-top:8px;">Dépôts & Demandes</div>

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
                    <div class="sb-section">Mon Espace</div>

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
                    <form method="POST" action="{{ route('logout') }}" style="margin-left:auto;">
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
                        <i class="fas fa-circle-dot"></i>
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
        })();
    </script>

    @yield('js')
</body>

</html>
