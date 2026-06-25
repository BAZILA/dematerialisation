@extends('layouts.app')

@section('title', "Inscription — Vérification d'identité")

@section('content')

    <div style="width:100%;max-width:600px;">
        <div class="card" style="border-radius:16px!important;">
            <div class="card-body" style="padding:40px!important;">

                {{-- Header --}}
                <div class="text-center mb-4">
                    <div class="icon-box mx-auto mb-3"
                        style="width:72px;height:72px;font-size:1.8rem;
                            background:var(--em-dim);border:1px solid rgba(16,201,122,.25);">
                        <i class="fas fa-user-check" style="color:var(--em);"></i>
                    </div>
                    <h2 style="font-family:var(--font-head);font-size:22px;color:var(--t1);margin-bottom:4px;">
                        Créer un Compte
                    </h2>
                    <p class="text-muted " style="font-size:13px;">
                        Étape 1 sur 2 — Vérification de votre identité
                    </p>
                </div>

                @if ($errors->has('general'))
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-exclamation-triangle"></i>
                        {{ $errors->first('general') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('register.verify') }}" id="registerForm">
                    @csrf

                    {{-- Type d'étudiant --}}
                    <div class="mb-4">
                        <label class="form-label" style="font-weight:600;">
                            <i class="fas fa-user-graduate"></i> Je suis un(e) :
                        </label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="type_etudiant" id="type_nouveau"
                                    value="nouveau" {{ old('type_etudiant', 'nouveau') === 'nouveau' ? 'checked' : '' }}>
                                <label class="btn btn-outline-primary w-100 py-3" for="type_nouveau"
                                    style="min-height:80px;display:flex;flex-direction:column;
                                          align-items:center;justify-content:center;gap:6px;">
                                    <i class="fas fa-graduation-cap" style="font-size:1.5rem;"></i>
                                    <strong>Nouveau Bachelier</strong>
                                </label>
                            </div>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="type_etudiant" id="type_ancien" value="ancien"
                                    {{ old('type_etudiant') === 'ancien' ? 'checked' : '' }}>
                                <label class="btn btn-outline-secondary w-100 py-3" for="type_ancien"
                                    style="min-height:80px;display:flex;flex-direction:column;
                                          align-items:center;justify-content:center;gap:6px;">
                                    <i class="fas fa-id-card" style="font-size:1.5rem;"></i>
                                    <strong>Ancien Étudiant</strong>
                                </label>
                            </div>
                        </div>
                        @error('type_etudiant')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Numéro carte (ancien uniquement) --}}
                    <div id="field_carte" class="mb-3" style="display:none;">
                        <label class="form-label">
                            <i class="fas fa-id-badge"></i> Numéro de Carte Étudiant
                        </label>
                        <input type="text" class="form-control @error('numero_carte') is-invalid @enderror"
                            name="numero_carte" value="{{ old('numero_carte') }}" placeholder="Ex : CARTE0000001">
                        @error('numero_carte')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Nom & Prénom --}}
                    <div class="row mb-3 g-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-user"></i> Nom
                            </label>
                            <input type="text" class="form-control @error('nom') is-invalid @enderror" name="nom"
                                value="{{ old('nom') }}" placeholder="MENSAH" required>
                            @error('nom')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-user"></i> Prénom
                            </label>
                            <input type="text" class="form-control @error('prenom') is-invalid @enderror" name="prenom"
                                value="{{ old('prenom') }}" placeholder="Kofi" required>
                            @error('prenom')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Date & Lieu de naissance --}}
                    <div class="row mb-3 g-3">
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-calendar-alt"></i> Date de Naissance
                            </label>
                            <input type="date" class="form-control @error('date_naissance') is-invalid @enderror"
                                name="date_naissance" value="{{ old('date_naissance') }}" required>
                            @error('date_naissance')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-6" id="field_lieu">
                            <label class="form-label">
                                <i class="fas fa-map-marker-alt"></i> Lieu de Naissance
                            </label>
                            <input type="text" class="form-control @error('lieu_naissance') is-invalid @enderror"
                                name="lieu_naissance" value="{{ old('lieu_naissance') }}" placeholder="Lomé">
                            @error('lieu_naissance')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Sexe --}}
                    <div class="mb-4">
                        <label class="form-label" style="font-weight:600;">
                            <i class="fas fa-venus-mars"></i> Sexe
                        </label>
                        <div class="d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="sexe" id="sexe_m"
                                    value="M" {{ old('sexe', 'M') === 'M' ? 'checked' : '' }}>
                                <label class="form-check-label" for="sexe_m">Masculin</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="sexe" id="sexe_f"
                                    value="F" {{ old('sexe') === 'F' ? 'checked' : '' }}>
                                <label class="form-check-label" for="sexe_f">Féminin</label>
                            </div>
                        </div>
                        @error('sexe')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100" style="height:44px;font-size:15px;">
                        <i class="fas fa-search"></i> Vérifier mon Identité
                    </button>
                </form>

                <hr style="margin:28px 0;">

                <div class="text-center">
                    <p class="text-muted  mb-0" style="font-size:13.5px;">
                        Déjà inscrit ?
                        <a href="{{ route('login') }}" style="color:var(--em);font-weight:600;text-decoration:none;">
                            <i class="fas fa-sign-in-alt"></i> Se connecter
                        </a>
                    </p>
                </div>

            </div>
        </div>
    </div>

@endsection

@section('js')
    <script>
        (function() {
            const radios = document.querySelectorAll('input[name="type_etudiant"]');
            const carte = document.getElementById('field_carte');
            const lieu = document.getElementById('field_lieu');

            function toggle() {
                const isAncien = document.getElementById('type_ancien').checked;
                carte.style.display = isAncien ? 'block' : 'none';
                lieu.style.display = isAncien ? 'none' : 'block';
                carte.querySelector('input').required = isAncien;
                lieu.querySelector('input').required = !isAncien;
            }

            radios.forEach(r => r.addEventListener('change', toggle));
            toggle();
        })();
    </script>
@endsection
