{{-- Page d'erreur commune : autonome (aucune session ni permission requise). --}}
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} — {{ $titre }}</title>
    <link rel="icon" href="{{ asset('assets/uk/logo-sigle.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --brand:#1A56A8; --brand-600:#14458a; --green:#2F9E33;
            --t1:#14243B; --t2:#4A5A70; --t3:#8A97A8; --line:#E3EAF3; --surface:#fff; --bg:#F4F7FB; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
            padding:24px; font-family:'Manrope',system-ui,sans-serif; color:var(--t1);
            background:var(--bg);
            background-image:radial-gradient(circle at 12% 18%, rgba(26,86,168,.09), transparent 42%),
                             radial-gradient(circle at 88% 82%, rgba(47,158,51,.09), transparent 42%); }
        .boite { width:100%; max-width:560px; background:var(--surface); border:1px solid var(--line);
            border-radius:20px; padding:38px 34px; text-align:center;
            box-shadow:0 18px 50px rgba(20,36,59,.09); }
        .logo { height:44px; margin-bottom:20px; }
        .code { font-size:64px; font-weight:800; line-height:1; letter-spacing:-2px;
            background:linear-gradient(135deg,var(--brand),var(--green));
            -webkit-background-clip:text; background-clip:text; color:transparent; }
        .pastille { width:60px; height:60px; border-radius:18px; margin:0 auto 16px;
            display:flex; align-items:center; justify-content:center; font-size:24px;
            background:{{ $fond }}; color:{{ $teinte }}; }
        h1 { font-size:20px; font-weight:800; margin:14px 0 8px; }
        p.msg { color:var(--t2); font-size:14.5px; line-height:1.6; margin:0 0 6px; }
        p.detail { color:var(--t3); font-size:12.5px; line-height:1.55; margin:10px 0 0;
            padding:10px 12px; background:var(--bg); border:1px solid var(--line); border-radius:10px; }
        .actions { display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:24px; }
        .btn { display:inline-flex; align-items:center; gap:7px; padding:11px 20px; border-radius:11px;
            font-size:13.5px; font-weight:600; text-decoration:none; border:1px solid transparent;
            transition:transform .15s, box-shadow .15s; cursor:pointer; }
        .btn-p { background:var(--brand); color:#fff; }
        .btn-p:hover { background:var(--brand-600); transform:translateY(-1px);
            box-shadow:0 8px 20px rgba(26,86,168,.28); }
        .btn-s { background:transparent; color:var(--t2); border-color:var(--line); }
        .btn-s:hover { border-color:var(--brand); color:var(--brand); }
        .pied { margin-top:22px; font-size:11.5px; color:var(--t3); }
        @media (max-width:480px) { .boite { padding:28px 20px; } .code { font-size:52px; } }
    </style>
</head>
<body>
    <div class="boite">
        <img src="{{ asset('assets/uk/logo-sigle-bg.png') }}" alt="Université de Kara" class="logo">
        <div class="pastille"><i class="fas {{ $icone }}"></i></div>
        <div class="code">{{ $code }}</div>
        <h1>{{ $titre }}</h1>
        <p class="msg">{{ $message }}</p>

        @if (!empty($detail))
            <p class="detail"><i class="fas fa-circle-info"></i> {{ $detail }}</p>
        @endif

        <div class="actions">
            <a href="javascript:history.back()" class="btn btn-s"><i class="fas fa-arrow-left"></i> Retour</a>
            <a href="{{ url('/dashboard') }}" class="btn btn-p"><i class="fas fa-house"></i> Accueil</a>
        </div>

        <div class="pied">
            {{ \App\Models\Setting::get('app.university_name', 'Université de Kara') }}
            — Plateforme de dématérialisation
        </div>
    </div>
</body>
</html>
