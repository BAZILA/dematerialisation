{{--
    Champs d'une fiche du référentiel.

    $item : la fiche à modifier (null = création)
    $type : 'bacheliers' ou 'anciens'

    L'identité civile sert de clé de connexion : une faute de frappe sur
    le nom ou la date empêche l'usager d'entrer. C'est pourquoi ces
    champs doivent rester corrigeables.
--}}
@php
    $estAncien = ($type ?? 'bacheliers') === 'anciens';
    $u = $item?->id ?? 'new';
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="f-nom-{{ $type }}-{{ $u }}">
            Nom <span class="text-danger">*</span>
        </label>
        <input type="text" class="form-control" id="f-nom-{{ $type }}-{{ $u }}"
            name="nom" value="{{ old('nom', $item?->nom) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="f-prenom-{{ $type }}-{{ $u }}">
            Prénom <span class="text-danger">*</span>
        </label>
        <input type="text" class="form-control" id="f-prenom-{{ $type }}-{{ $u }}"
            name="prenom" value="{{ old('prenom', $item?->prenom) }}" required>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="f-date-{{ $type }}-{{ $u }}">
            Date de naissance <span class="text-danger">*</span>
        </label>
        <input type="date" class="form-control" id="f-date-{{ $type }}-{{ $u }}"
            name="date_naissance"
            value="{{ old('date_naissance', $item?->date_naissance?->format('Y-m-d')) }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="f-lieu-{{ $type }}-{{ $u }}">
            Lieu de naissance <span class="text-danger">*</span>
        </label>
        <input type="text" class="form-control" id="f-lieu-{{ $type }}-{{ $u }}"
            name="lieu_naissance" value="{{ old('lieu_naissance', $item?->lieu_naissance) }}" required>
    </div>

    <div class="col-md-6">
        <label class="form-label">Sexe <span class="text-danger">*</span></label>
        <div class="d-flex gap-3 pt-1">
            @foreach (['M' => 'Masculin', 'F' => 'Féminin'] as $code => $libelle)
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="sexe" value="{{ $code }}"
                        id="f-sexe-{{ $type }}-{{ $u }}-{{ $code }}"
                        @checked(old('sexe', $item?->sexe) === $code) required>
                    <label class="form-check-label" for="f-sexe-{{ $type }}-{{ $u }}-{{ $code }}">
                        {{ $libelle }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    @if ($estAncien)
        <div class="col-md-6">
            <label class="form-label" for="f-carte-{{ $u }}">
                N° de carte étudiant <span class="text-danger">*</span>
            </label>
            <input type="text" class="form-control" id="f-carte-{{ $u }}"
                name="numero_carte" value="{{ old('numero_carte', $item?->numero_carte) }}" required>
            <small class="text-muted">C'est la clé de connexion : la modifier change la façon dont l'étudiant s'identifie.</small>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="f-suivi-a-{{ $u }}">N° de suivi</label>
            <input type="text" class="form-control" id="f-suivi-a-{{ $u }}"
                name="numero_suivi" value="{{ old('numero_suivi', $item?->numero_suivi) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="f-spec-{{ $u }}">Spécialité</label>
            <input type="text" class="form-control" id="f-spec-{{ $u }}"
                name="specialite" value="{{ old('specialite', $item?->specialite) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="f-univ-{{ $u }}">Dernière université</label>
            <input type="text" class="form-control" id="f-univ-{{ $u }}"
                name="derniere_universite"
                value="{{ old('derniere_universite', $item?->derniere_universite) }}">
        </div>
        <div class="col-12">
            <label class="form-label" for="f-adresse-{{ $u }}">Adresse</label>
            <input type="text" class="form-control" id="f-adresse-{{ $u }}"
                name="adresse" value="{{ old('adresse', $item?->adresse) }}">
        </div>
    @else
        <div class="col-md-6">
            <label class="form-label" for="f-suivi-b-{{ $u }}">N° de suivi</label>
            <input type="text" class="form-control" id="f-suivi-b-{{ $u }}"
                name="numero_suivi" value="{{ old('numero_suivi', $item?->numero_suivi) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="f-releve-{{ $u }}">N° de relevé</label>
            <input type="text" class="form-control" id="f-releve-{{ $u }}"
                name="numero_releve" value="{{ old('numero_releve', $item?->numero_releve) }}">
        </div>
        <div class="col-12">
            <div class="alert alert-light border py-2 px-3 mb-0" style="font-size:12.5px;">
                <i class="fas fa-key"></i>
                Gardez au moins l'un des deux numéros ci-dessus : c'est la clé
                qui permet au bachelier de se connecter.
            </div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="f-table-{{ $u }}">N° de table</label>
            <input type="text" class="form-control" id="f-table-{{ $u }}"
                name="numero_table" value="{{ old('numero_table', $item?->numero_table) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="f-serie-{{ $u }}">Série du bac</label>
            <input type="text" class="form-control" id="f-serie-{{ $u }}"
                name="serie_bac" value="{{ old('serie_bac', $item?->serie_bac) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="f-annee-{{ $u }}">Année du bac</label>
            <input type="text" class="form-control" id="f-annee-{{ $u }}"
                name="annee_bac" value="{{ old('annee_bac', $item?->annee_bac) }}">
        </div>
        <div class="col-12">
            <label class="form-label" for="f-academie-{{ $u }}">Académie</label>
            <input type="text" class="form-control" id="f-academie-{{ $u }}"
                name="academie" value="{{ old('academie', $item?->academie) }}">
        </div>
    @endif

    <div class="col-md-6">
        <label class="form-label" for="f-tel-{{ $type }}-{{ $u }}">Téléphone</label>
        <input type="text" class="form-control" id="f-tel-{{ $type }}-{{ $u }}"
            name="telephone" value="{{ old('telephone', $item?->telephone) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label" for="f-mail-{{ $type }}-{{ $u }}">Courriel</label>
        <input type="email" class="form-control" id="f-mail-{{ $type }}-{{ $u }}"
            name="email" value="{{ old('email', $item?->email) }}">
        <small class="text-muted">Facultatif. Sans adresse, l'usager reçoit son code à l'écran.</small>
    </div>
</div>

@if ($item && ($item->is_registered || $item->student))
    <div class="alert alert-warning mt-3 mb-0" style="font-size:12.5px;">
        <i class="fas fa-triangle-exclamation"></i>
        <strong>Cette personne a déjà un compte.</strong>
        Corriger son identité met aussi à jour sa fiche étudiant — donc les
        documents qui lui seront délivrés. Si vous changez la clé de
        connexion, prévenez-la : elle s'identifiera autrement.
    </div>
@endif
