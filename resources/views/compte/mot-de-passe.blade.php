@extends(auth()->user()->isAdmin() ? 'layouts.app' : 'layouts.student')
@section('title', 'Mot de passe')
@section('page-title', 'Mon mot de passe')
@section('page-subtitle', 'Vous seul devez le connaître')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">

            @if ($impose)
                <div class="alert alert-warning d-flex gap-2 align-items-start">
                    <i class="fas fa-triangle-exclamation mt-1"></i>
                    <div>
                        <strong>Votre mot de passe est provisoire.</strong><br>
                        Il vous a été remis par l'administration, qui le connaît donc aussi.
                        Choisissez-en un nouveau pour accéder à votre espace.
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card-header"><i class="fas fa-key"></i> Changer mon mot de passe</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('compte.mot-de-passe.changer') }}" autocomplete="off">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="mot_de_passe_actuel">Mot de passe actuel</label>
                            <input type="password" id="mot_de_passe_actuel" name="mot_de_passe_actuel"
                                class="form-control @error('mot_de_passe_actuel') is-invalid @enderror"
                                autocomplete="current-password" required autofocus>
                            @error('mot_de_passe_actuel')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Nouveau mot de passe</label>
                            <input type="password" id="password" name="password"
                                class="form-control @error('password') is-invalid @enderror"
                                autocomplete="new-password" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                @if (auth()->user()->isAdmin())
                                    Au moins 10 caractères, avec majuscules, minuscules et chiffres.
                                @else
                                    Au moins 8 caractères, avec des lettres et des chiffres.
                                @endif
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="password_confirmation">Confirmer le nouveau mot de passe</label>
                            <input type="password" id="password_confirmation" name="password_confirmation"
                                class="form-control" autocomplete="new-password" required>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Enregistrer
                            </button>
                            @unless ($impose)
                                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Annuler</a>
                            @endunless
                        </div>
                    </form>
                </div>
            </div>

            <p class="small mt-3" style="color:var(--t3);">
                Après le changement, les autres appareils connectés à votre compte seront déconnectés.
            </p>
        </div>
    </div>
@endsection
