@extends('layouts.app')

@section('title', 'Inscription — Finalisation')

@section('content')

    <div style="width:100%;max-width:500px;">
        <div class="card" style="border-radius:16px!important;">
            <div class="card-body" style="padding:40px!important;">

                {{-- Header --}}
                <div class="text-center mb-4">
                    <div class="icon-box mx-auto mb-3"
                        style="width:72px;height:72px;font-size:1.8rem;
                            background:var(--em-dim);border:1px solid rgba(27,58,107,.22);">
                        <i class="fas fa-check-circle" style="color:var(--em);"></i>
                    </div>
                    <h2 style="font-family:var(--font-head);font-size:22px;color:var(--t1);margin-bottom:4px;">
                        Identité Vérifiée !
                    </h2>
                    <p class="text-muted " style="font-size:13px;">
                        Étape 2 sur 2 — Créez votre mot de passe
                    </p>
                </div>

                {{-- Identifiant généré --}}
                <div
                    style="background:var(--surface-2);border:1px solid rgba(27,58,107,.22);
                        border-radius:12px;padding:16px 20px;margin-bottom:20px;">
                    <div class="d-flex align-items-center gap-3">
                        <i class="fas fa-id-card" style="font-size:1.8rem;color:var(--em);flex-shrink:0;"></i>
                        <div>
                            <div
                                style="font-size:11px;color:var(--t3);margin-bottom:4px;text-transform:uppercase;letter-spacing:.06em;">
                                Votre identifiant de connexion
                            </div>
                            <div style="font-family:var(--font-head);font-size:24px;color:var(--em);letter-spacing:.05em;">
                                {{ session('reg_identifiant') }}
                            </div>
                            <div style="font-size:11.5px;color:var(--amber);margin-top:4px;">
                                <i class="fas fa-triangle-exclamation"></i>
                                Notez-le soigneusement avant de continuer
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Récap identité --}}
                <div
                    style="background:var(--surface-2);border:1px solid var(--line);
                        border-radius:10px;padding:14px 18px;margin-bottom:24px;">
                    <div class="row g-2" style="font-size:13px;">
                        <div class="col-6">
                            <span style="color:var(--t3);">Nom :</span>
                            <strong style="color:var(--t1);margin-left:4px;">{{ session('reg_nom') }}</strong>
                        </div>
                        <div class="col-6">
                            <span style="color:var(--t3);">Prénom :</span>
                            <strong style="color:var(--t1);margin-left:4px;">{{ session('reg_prenom') }}</strong>
                        </div>
                        <div class="col-6">
                            <span style="color:var(--t3);">Naissance :</span>
                            <strong style="color:var(--t1);margin-left:4px;">
                                {{ \Carbon\Carbon::parse(session('reg_date_naissance'))->format('d/m/Y') }}
                            </strong>
                        </div>
                        <div class="col-6">
                            <span style="color:var(--t3);">Profil :</span>
                            <strong style="color:var(--t1);margin-left:4px;">
                                {{ session('reg_type') === 'nouveau' ? 'Nouveau Bachelier' : 'Ancien Étudiant' }}
                            </strong>
                        </div>
                    </div>
                </div>

                {{-- Formulaire --}}
                <form method="POST" action="{{ route('register.complete.post') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Adresse E-mail
                        </label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" name="email"
                            value="{{ old('email') }}" placeholder="exemple@mail.com" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Mot de Passe
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                id="pwd" name="password" placeholder="Minimum 8 caractères" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('pwd',this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Confirmer le Mot de Passe
                        </label>
                        <input type="password" class="form-control" name="password_confirmation"
                            placeholder="Répétez le mot de passe" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" style="height:44px;font-size:15px;"
                        data-loading-text="Création du compte…">
                        <i class="fas fa-user-plus"></i> Créer mon Compte
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a href="{{ route('register') }}" style="font-size:13px;color:var(--t3);text-decoration:none;">
                        <i class="fas fa-arrow-left"></i> Modifier mes informations
                    </a>
                </div>

            </div>
        </div>
    </div>

@endsection

@section('js')
    <script>
        function togglePwd(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            input.type = input.type === 'password' ? 'text' : 'password';
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        }
    </script>
@endsection
