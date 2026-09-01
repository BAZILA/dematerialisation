@extends('layouts.app')

@section('title', "Code d'accès")

@section('css')
    <style>
        .ac-carte {
            width: 100%; max-width: 460px; margin: 0 auto;
            border-radius: 14px !important; overflow: hidden;
            border: 1px solid var(--line) !important;
            box-shadow: var(--shadow-sm) !important;
        }

        /* En-tête sobre : le logo porte l'identité, pas un dégradé */
        .ac-tete {
            padding: 22px 28px 18px;
            border-bottom: 1px solid var(--line);
            display: flex; align-items: center; gap: 14px;
        }
        .ac-tete img { height: 40px; width: auto; flex-shrink: 0; }
        .ac-tete-texte h1 {
            font-family: var(--font-head); font-size: 17px; font-weight: 700;
            color: var(--t1); margin: 0; letter-spacing: -.01em;
        }
        .ac-tete-texte p { font-size: 12.5px; color: var(--t2); margin: 2px 0 0; }

        .ac-corps { padding: 24px 28px 26px; }

        /* Le code se lit comme une référence officielle : encadré net,
           chiffres alignés, pas d'effet décoratif. */
        .ac-code {
            border: 1px solid var(--line); border-left: 3px solid var(--brand);
            border-radius: 8px; background: #fff;
            padding: 16px 18px; text-align: center;
        }
        .ac-code-libelle {
            font-size: 10.5px; font-weight: 600; letter-spacing: .1em;
            text-transform: uppercase; color: var(--t3); margin-bottom: 6px;
        }
        .ac-code-valeur {
            font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
            font-size: 27px; font-weight: 600; letter-spacing: .12em;
            color: var(--t1); user-select: all; line-height: 1.15;
            font-variant-numeric: tabular-nums;
        }
        .ac-meta {
            font-size: 12px; color: var(--t2);
            margin-top: 10px; text-align: center;
        }

        .ac-champ {
            font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
            font-size: 17px; letter-spacing: .12em; text-align: center;
            text-transform: uppercase; height: 46px;
        }
        .ac-champ::placeholder { letter-spacing: .12em; color: var(--t3); opacity: .55; }

        .ac-liens {
            display: flex; gap: 8px; margin-top: 14px;
            padding-top: 14px; border-top: 1px solid var(--line);
        }
        .ac-lien {
            flex: 1; text-align: center; font-size: 12.5px; padding: 7px 4px;
            color: var(--t2); text-decoration: none; border-radius: 7px;
            border: 1px solid var(--line); background: none;
            transition: border-color .15s, color .15s;
        }
        .ac-lien:hover { color: var(--brand); border-color: var(--brand); }
    </style>
@endsection

@section('content')
    <div class="card ac-carte">

        <div class="ac-tete">
            <img src="{{ asset('assets/uk/logo-sigle-bg.png') }}" alt="Université de Kara">
            <div class="ac-tete-texte">
                <h1>Votre code d'accès</h1>
                <p>Identité vérifiée — {{ $user->numero_identifiant }}</p>
            </div>
        </div>

        <div class="ac-corps">

            @if (session('success'))
                <div class="alert alert-success py-2 px-3 mb-3" style="font-size:12.5px;">
                    {{ session('success') }}
                </div>
            @endif

            @if ($code)
                <div class="ac-code">
                    <div class="ac-code-libelle">Code</div>
                    <div class="ac-code-valeur">{{ $code }}</div>
                </div>
                <p class="ac-meta">
                    Valable {{ $minutes }} minutes · utilisable une seule fois
                    @if ($envoyeParMail)
                        <br>Également envoyé à {{ $user->email }}
                    @endif
                </p>
            @else
                <div class="alert alert-warning py-2 px-3 mb-0" style="font-size:12.5px;">
                    Votre code ne s'affiche qu'une fois. S'il a disparu de l'écran,
                    demandez-en un nouveau ci-dessous.
                </div>
            @endif

            @if ($errors->has('general'))
                <div class="alert alert-danger py-2 px-3 mt-3 mb-0" style="font-size:12.5px;">
                    {{ $errors->first('general') }}
                </div>
            @endif

            <form method="POST" action="{{ route('acces.code.post') }}" class="mt-3">
                @csrf
                <label class="form-label" style="font-size:13px;">Saisissez-le pour continuer</label>
                <input type="text" name="code" maxlength="20" autocomplete="one-time-code"
                    class="form-control ac-champ @error('code') is-invalid @enderror"
                    placeholder="XXXX-XXXX" autofocus required>
                @error('code')
                    <div class="invalid-feedback text-center">{{ $message }}</div>
                @enderror

                <button type="submit" class="btn btn-primary w-100 mt-3"
                    style="height:44px;font-size:14.5px;" data-loading-text="Vérification…">
                    Accéder à mon espace
                </button>
            </form>

            <div class="ac-liens">
                <form method="POST" action="{{ route('acces.renvoyer') }}" style="flex:1;">
                    @csrf
                    <button type="submit" class="ac-lien w-100">Nouveau code</button>
                </form>
                <a href="{{ route('acces.identite') }}" class="ac-lien">Changer d'identité</a>
            </div>
        </div>
    </div>
@endsection
