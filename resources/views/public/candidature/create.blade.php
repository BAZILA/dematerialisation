@extends('layouts.app')
@section('title', 'Candidature en ligne')

@section('css')
    <style>
        body.guest-mode .content { align-items: flex-start !important; padding-top: 28px; padding-bottom: 44px; }
        .cand-wrap { width: 100%; max-width: 720px; position: relative; z-index: 1; }
    </style>
@endsection

@section('content')
    <div class="cand-wrap">
        <div class="text-center mb-4">
            <img src="{{ asset('assets/uk/logo-sigle-bg.png') }}" style="height:52px;" alt="Université de Kara">
            <h1 style="font-family:var(--font-head);font-size:22px;font-weight:800;margin-top:14px;color:var(--t1);">Candidature en ligne</h1>
            <p style="color:var(--t2);font-size:13.5px;">Soumettez votre candidature <strong>sans créer de compte</strong>. Un numéro de suivi vous sera remis.</p>
        </div>

        @if (!$ouverte)
            <div class="card"><div class="card-body text-center py-5">
                <i class="fas fa-door-closed" style="font-size:2.5rem;color:var(--t3);"></i>
                <h5 class="mt-3" style="color:var(--t1);">Candidatures fermées</h5>
                <p class="text-muted">Les candidatures ne sont pas ouvertes actuellement. Revenez pendant la période d'admission.</p>
                <a href="{{ route('login') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
            </div></div>
        @else
            <div class="card"><div class="card-body" style="padding:28px !important;">
                <form method="POST" action="{{ route('candidature.store') }}" enctype="multipart/form-data">
                    @csrf
                    <h6 class="fw-bold mb-3" style="color:var(--brand);"><i class="fas fa-user"></i> Vos informations</h6>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Nom *</label><input class="form-control" name="nom" value="{{ old('nom') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Prénom(s) *</label><input class="form-control" name="prenom" value="{{ old('prenom') }}" required></div>
                        <div class="col-md-4"><label class="form-label">Sexe *</label>
                            <select class="form-select" name="sexe" required>
                                <option value="">—</option>
                                <option value="M" @selected(old('sexe') == 'M')>Masculin</option>
                                <option value="F" @selected(old('sexe') == 'F')>Féminin</option>
                            </select></div>
                        <div class="col-md-4"><label class="form-label">Date de naissance *</label><input type="date" class="form-control" name="date_naissance" value="{{ old('date_naissance') }}" required></div>
                        <div class="col-md-4"><label class="form-label">Lieu de naissance *</label><input class="form-control" name="lieu_naissance" value="{{ old('lieu_naissance') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Email *</label><input type="email" class="form-control" name="email" value="{{ old('email') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Téléphone</label><input class="form-control" name="telephone" value="{{ old('telephone') }}"></div>
                    </div>

                    <h6 class="fw-bold mb-3 mt-4" style="color:var(--brand);"><i class="fas fa-graduation-cap"></i> Parcours visé *</h6>
                    <select class="form-select" name="parcours_id" required>
                        <option value="">— Choisissez un parcours —</option>
                        @foreach ($facultes as $fac)
                            @foreach ($fac->departements as $dep)
                                @if ($dep->parcours->count())
                                    <optgroup label="{{ $fac->sigle ?? $fac->nom }} · {{ $dep->nom }}">
                                        @foreach ($dep->parcours as $p)
                                            <option value="{{ $p->id }}" @selected(old('parcours_id') == $p->id)>{{ $p->nom }} ({{ $p->grade_label }})</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        @endforeach
                    </select>

                    @if ($types->count())
                        <h6 class="fw-bold mb-3 mt-4" style="color:var(--brand);"><i class="fas fa-paperclip"></i> Pièces à joindre</h6>
                        @foreach ($types as $t)
                            <div class="mb-3">
                                <label class="form-label">{{ $t->label }} @if ($t->is_required)<span class="text-danger">*</span>@else <small class="text-muted">(optionnel)</small>@endif</label>
                                <input type="file" class="form-control" name="files[{{ $t->id }}]" accept=".pdf,.jpg,.jpeg,.png" @required($t->is_required)>
                                @if ($t->description)<small class="text-muted">{{ $t->description }}</small>@endif
                            </div>
                        @endforeach
                    @endif

                    <button type="submit" class="btn btn-primary w-100 mt-3" style="height:48px;font-size:15px;" data-loading-text="Envoi de la candidature…">
                        <i class="fas fa-paper-plane"></i> Soumettre ma candidature
                    </button>
                </form>
            </div></div>
            <p class="text-center mt-3" style="font-size:13px;">
                <a href="{{ route('candidature.suivi') }}" style="color:var(--brand);text-decoration:none;"><i class="fas fa-magnifying-glass"></i> Suivre une candidature existante</a>
                &nbsp;·&nbsp;
                <a href="{{ route('login') }}" style="color:var(--t2);text-decoration:none;">Retour à la connexion</a>
            </p>
        @endif
    </div>
@endsection
