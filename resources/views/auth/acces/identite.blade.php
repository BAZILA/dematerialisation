@extends('layouts.app')

@section('title', 'Accéder à mon espace')

@section('css')
    <style>
        .acces-carte {
            width: 100%; max-width: 520px; margin: 0 auto;
            border-radius: 14px !important; overflow: hidden;
            border: 1px solid var(--line) !important;
            box-shadow: var(--shadow-sm) !important;
        }
        /* En-tete sobre : le logo porte l'identite, pas un degrade */
        .acces-tete {
            padding: 22px 28px 18px; border-bottom: 1px solid var(--line);
            display: flex; align-items: center; gap: 14px;
        }
        .acces-tete img { height: 40px; width: auto; flex-shrink: 0; }
        .acces-tete h1 {
            font-family: var(--font-head); font-size: 17px; font-weight: 700;
            color: var(--t1); margin: 0; letter-spacing: -.01em;
        }
        .acces-tete p { font-size: 12.5px; color: var(--t2); margin: 2px 0 0; }
        .acces-corps { padding: 24px 28px 26px; }
        .profil-choix { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .profil-carte { border: 1.5px solid var(--line); border-radius: 12px; padding: 12px 14px; cursor: pointer; transition: border-color .15s, background .15s; }
        .profil-carte:hover { border-color: var(--brand); }
        .profil-carte input { margin-right: 6px; }
        .profil-carte.actif { border-color: var(--brand); background: rgba(26, 86, 168, .05); }
        .profil-carte .titre { font-weight: 700; font-size: 14px; color: var(--t1); }
        .profil-carte .sous { font-size: 11.5px; color: var(--t2); }
    </style>
@endsection

@section('content')
    <div class="card acces-carte">
        <div class="acces-tete">
            <img src="{{ asset('assets/uk/logo-sigle-bg.png') }}" alt="Université de Kara">
            <div>
                <h1>Accéder à mon espace</h1>
                <p>Renseignez votre identité, un code vous sera remis.</p>
            </div>
        </div>

        <div class="acces-corps">
            @if ($errors->has('general'))
                <div class="alert alert-danger py-2 small">
                    <i class="fas fa-circle-exclamation"></i> {{ $errors->first('general') }}
                </div>
            @endif

            <form method="POST" action="{{ route('acces.identite.post') }}">
                @csrf

                <label class="form-label">Vous êtes <span class="text-danger">*</span></label>
                <div class="profil-choix mb-3">
                    @php $typeActuel = old('type_etudiant', 'nouveau'); @endphp
                    <label class="profil-carte {{ $typeActuel === 'nouveau' ? 'actif' : '' }}" data-profil>
                        <input type="radio" name="type_etudiant" value="nouveau"
                            {{ $typeActuel === 'nouveau' ? 'checked' : '' }} onchange="majProfil()">
                        <span class="titre">Nouveau bachelier</span>
                        <div class="sous">Vous venez d'obtenir le BAC</div>
                    </label>
                    <label class="profil-carte {{ $typeActuel === 'ancien' ? 'actif' : '' }}" data-profil>
                        <input type="radio" name="type_etudiant" value="ancien"
                            {{ $typeActuel === 'ancien' ? 'checked' : '' }} onchange="majProfil()">
                        <span class="titre">Ancien étudiant</span>
                        <div class="sous">Vous avez une carte d'étudiant</div>
                    </label>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" name="nom" class="form-control @error('nom') is-invalid @enderror"
                            value="{{ old('nom') }}" required>
                        @error('nom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Prénom(s) <span class="text-danger">*</span></label>
                        <input type="text" name="prenom" class="form-control @error('prenom') is-invalid @enderror"
                            value="{{ old('prenom') }}" required>
                        @error('prenom')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date de naissance <span class="text-danger">*</span></label>
                        <input type="date" name="date_naissance" class="form-control @error('date_naissance') is-invalid @enderror"
                            value="{{ old('date_naissance') }}" required>
                        @error('date_naissance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sexe <span class="text-danger">*</span></label>
                        <select name="sexe" class="form-select @error('sexe') is-invalid @enderror" required>
                            <option value="">—</option>
                            <option value="M" @selected(old('sexe') === 'M')>Masculin</option>
                            <option value="F" @selected(old('sexe') === 'F')>Féminin</option>
                        </select>
                        @error('sexe')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12" data-champ="nouveau">
                        <label class="form-label">Lieu de naissance <span class="text-danger">*</span></label>
                        <input type="text" name="lieu_naissance" class="form-control @error('lieu_naissance') is-invalid @enderror"
                            value="{{ old('lieu_naissance') }}">
                        @error('lieu_naissance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Tel qu'il figure sur votre relevé de BAC.</small>
                    </div>

                    <div class="col-12" data-champ="ancien" style="display:none;">
                        <label class="form-label">Numéro de carte d'étudiant <span class="text-danger">*</span></label>
                        <input type="text" name="numero_carte" class="form-control @error('numero_carte') is-invalid @enderror"
                            value="{{ old('numero_carte') }}">
                        @error('numero_carte')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-4" style="height:46px;font-size:15px;"
                    data-loading-text="Vérification…">
                    <i class="fas fa-shield-halved"></i> Vérifier mon identité
                </button>
            </form>

            <hr style="margin:22px 0;">
            <div class="text-center">
                <a href="{{ route('login') }}" style="font-size:13px;color:var(--t2);text-decoration:none;">
                    <i class="fas fa-arrow-left"></i> Vous faites partie du personnel ? Connexion habituelle
                </a>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        function majProfil() {
            const type = document.querySelector('input[name="type_etudiant"]:checked')?.value;
            document.querySelectorAll('[data-champ]').forEach(bloc => {
                const concerne = bloc.dataset.champ === type;
                bloc.style.display = concerne ? '' : 'none';
                // Un champ masqué ne doit pas bloquer l'envoi du formulaire
                bloc.querySelectorAll('input').forEach(i => { i.required = concerne; });
            });
            document.querySelectorAll('[data-profil]').forEach(c => {
                c.classList.toggle('actif', c.querySelector('input').checked);
            });
        }
        majProfil();
    </script>
@endsection
