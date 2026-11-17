@extends('layouts.app')

@section('page-title', 'Pièces attendues')
@section('page-subtitle', "Choisissez un niveau d'études pour voir ses procédures et les pièces à fournir")

@section('css')
    <style>
        .niv-card {
            display: block; text-decoration: none; height: 100%;
            border: 1px solid var(--line); border-radius: 14px;
            background: var(--card); padding: 20px;
            transition: border-color .15s, transform .15s, box-shadow .15s;
        }
        .niv-card:hover { border-color: var(--brand); transform: translateY(-2px); box-shadow: var(--shadow); }
        .niv-icone {
            width: 46px; height: 46px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(140deg, #1A56A8, #2F9E33); color: #fff; font-size: 18px;
        }
        .niv-nom { font-family: var(--font-head); font-size: 17px; font-weight: 700; color: var(--t1); }
        .niv-chiffres { display: flex; gap: 18px; margin-top: 14px; }
        .niv-chiffre b { display: block; font-size: 19px; font-weight: 700; color: var(--t1); line-height: 1.1; }
        .niv-chiffre span { font-size: 11.5px; color: var(--t3); }
    </style>
@endsection

@section('content')

    @if (!$annee)
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation"></i>
            Aucune année académique de contexte : les états d'ouverture ne peuvent pas être calculés.
        </div>
    @endif

    <div class="alert alert-info d-flex align-items-start gap-2" style="font-size:13.5px;">
        <i class="fas fa-circle-info mt-1"></i>
        <div>
            Une pièce peut être réclamée à <strong>tout le monde</strong>, à <strong>tout un niveau</strong>
            (« tous les masters »), ou à des <strong>parcours précis</strong>. Les portées se cumulent.
            Entrez dans un niveau pour régler ses procédures et ses pièces.
        </div>
    </div>

    <div class="row g-3">
        @foreach ($niveaux as $niv)
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('admin.request-types.niveau', $niv['code']) }}" class="niv-card">
                    <div class="d-flex align-items-center gap-3">
                        <div class="niv-icone"><i class="fas fa-graduation-cap"></i></div>
                        <div>
                            <div class="niv-nom">{{ $niv['label'] }}</div>
                            <div class="small" style="color:var(--t3);">
                                {{ $niv['parcours'] }} parcours actif{{ $niv['parcours'] > 1 ? 's' : '' }}
                            </div>
                        </div>
                    </div>

                    <div class="niv-chiffres">
                        <div class="niv-chiffre">
                            <b>{{ $niv['ouvertes'] }}</b>
                            <span>procédure{{ $niv['ouvertes'] > 1 ? 's' : '' }} ouverte{{ $niv['ouvertes'] > 1 ? 's' : '' }}</span>
                        </div>
                        <div class="niv-chiffre">
                            <b>{{ $niv['pieces'] }}</b>
                            <span>pièce{{ $niv['pieces'] > 1 ? 's' : '' }} propre{{ $niv['pieces'] > 1 ? 's' : '' }}</span>
                        </div>
                    </div>

                    @if ($niv['parcours'] === 0)
                        <div class="small mt-3" style="color:var(--t3);">
                            <i class="fas fa-circle-minus"></i> Aucun parcours à ce niveau pour l'instant.
                        </div>
                    @endif
                </a>
            </div>
        @endforeach

    </div>

    @if ($niveauxVides->isNotEmpty())
        {{-- Un niveau sans pièce accepte des dossiers vides : le dire ici,
             plutôt que de le laisser découvrir par un dossier soumis à blanc. --}}
        <div class="alert alert-warning mt-4" style="font-size:13px;">
            <i class="fas fa-triangle-exclamation"></i>
            <strong>Aucune pièce exigée</strong> pour :
            <strong>{{ $niveauxVides->implode(', ') }}</strong>.
            Tant qu'aucune pièce n'y est définie, les dossiers de ce ou ces niveaux
            peuvent être soumis vides. Entrez dans le niveau pour en ajouter, ou
            recopiez celles d'un autre niveau depuis son écran.
        </div>
    @endif

@endsection
