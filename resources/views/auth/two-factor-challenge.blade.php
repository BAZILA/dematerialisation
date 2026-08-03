<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vérification — {{ \App\Models\Setting::get('app.short_name', 'UK') }} Démat</title>
    <link rel="icon" href="{{ asset('assets/uk/logo.ico') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #F5F8FC; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; }
        .tfa-card { background: #fff; border: 1px solid #E5EBF3; border-radius: 18px;
            box-shadow: 0 10px 40px rgba(20,36,59,.08); padding: 36px 32px; width: 100%; max-width: 400px; }
        .tfa-ic { width: 58px; height: 58px; border-radius: 16px; margin: 0 auto 18px;
            background: linear-gradient(135deg, #1A56A8, #2F9E33); color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .tfa-code { font-size: 26px; letter-spacing: .45em; text-align: center; font-weight: 700;
            border: 1px solid #CBD6E4; border-radius: 12px; padding: 12px 0 12px .45em; width: 100%; }
        .tfa-code:focus { outline: none; border-color: #1A56A8; box-shadow: 0 0 0 3px rgba(26,86,168,.15); }
        .btn-uk { background: #1A56A8; border: none; color: #fff; font-weight: 600;
            border-radius: 12px; padding: 12px; width: 100%; }
        .btn-uk:hover { background: #14437f; color: #fff; }
    </style>
</head>
<body>
    <div class="tfa-card">
        <div class="tfa-ic"><i class="fas fa-shield-halved"></i></div>
        <h5 class="fw-bold text-center mb-1">Double authentification</h5>
        <p class="text-muted text-center small mb-4" id="texteAide">
            Saisissez le code à 6 chiffres généré par votre application
            (Google Authenticator, Authy…) pour continuer.
        </p>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small"><i class="fas fa-circle-exclamation"></i> {{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('2fa.verify') }}">
            @csrf
            <input type="text" name="code" id="champCode" class="tfa-code mb-3" maxlength="6" pattern="\d{6}"
                inputmode="numeric" autocomplete="one-time-code" placeholder="••••••" autofocus required>
            <button type="submit" class="btn-uk"><i class="fas fa-unlock"></i> Vérifier</button>
        </form>

        {{-- Téléphone perdu : les codes de secours évitent le blocage total --}}
        <div class="text-center mt-3">
            <button type="button" class="btn btn-link btn-sm text-decoration-none" id="basculerSecours">
                <i class="fas fa-key"></i> Utiliser un code de secours
            </button>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="text-center mt-1">
            @csrf
            <button type="submit" class="btn btn-link btn-sm text-muted">
                <i class="fas fa-arrow-left"></i> Revenir à la connexion
            </button>
        </form>

        <script>
            (function () {
                const champ  = document.getElementById('champCode');
                const lien   = document.getElementById('basculerSecours');
                const aide   = document.getElementById('texteAide');
                let secours  = false;

                lien.addEventListener('click', function () {
                    secours = !secours;
                    if (secours) {
                        champ.maxLength = 11;
                        champ.removeAttribute('pattern');
                        champ.inputMode = 'text';
                        champ.placeholder = 'XXXXX-XXXXX';
                        champ.style.fontSize = '18px';
                        champ.style.letterSpacing = '.12em';
                        champ.style.textTransform = 'uppercase';
                        aide.textContent = "Saisissez l'un des codes de secours notés à l'activation. "
                            + 'Chaque code ne fonctionne qu\'une seule fois.';
                        this.innerHTML = '<i class="fas fa-mobile-screen"></i> Utiliser mon application';
                    } else {
                        champ.maxLength = 6;
                        champ.setAttribute('pattern', '\\d{6}');
                        champ.inputMode = 'numeric';
                        champ.placeholder = '••••••';
                        champ.style.fontSize = '26px';
                        champ.style.letterSpacing = '.45em';
                        champ.style.textTransform = 'none';
                        aide.textContent = 'Saisissez le code à 6 chiffres généré par votre application '
                            + '(Google Authenticator, Authy…) pour continuer.';
                        this.innerHTML = '<i class="fas fa-key"></i> Utiliser un code de secours';
                    }
                    champ.value = '';
                    champ.focus();
                });
            })();
        </script>
    </div>
</body>
</html>
