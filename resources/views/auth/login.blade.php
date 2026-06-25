@extends('layouts.app')

@section('title', 'Connexion')

@section('content')

    <div style="width:100%;max-width:510px;">
        <div class="card" style="border-radius:16px!important;">
            <div class="card-body" style="padding:40px!important;">

                <div class="text-center mb-4">

                    {{-- Logo --}}
                    <img src="{{ asset('images/uk_logo.png') }}" alt="Université de Kara"
                        style="width:160px; margin-bottom:15px;">

                    <div class="icon-box mx-auto mb-3"
                        style="width:80px;height:80px;font-size:1.8rem;
        background:var(--em-dim);border:1px solid rgba(16,201,122,.25);">
                        <i class="fas fa-lock" style="color:var(--em);"></i>
                    </div>

                    <h2 style="font-family:var(--font-head);font-size:22px;color:var(--t1);margin-bottom:4px;">
                        Connexion
                    </h2>

                    <p class="text-muted" style="font-size:13px;">
                        Plateforme de gestion de dossiers universitaires
                    </p>
                </div>

                {{-- Formulaire --}}
                <form method="POST" action="{{ route('login.post') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-id-card"></i> Identifiant
                        </label>
                        <input type="text" class="form-control @error('numero_identifiant') is-invalid @enderror"
                            name="numero_identifiant" placeholder="Ex : ADMIN001" value="{{ old('numero_identifiant') }}"
                            required>
                        @error('numero_identifiant')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">
                            <i class="fas fa-key"></i> Mot de Passe
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                id="pwdLogin" name="password" placeholder="••••••••" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('pwdLogin',this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        @error('password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100" style="height:44px;font-size:15px;">
                        <i class="fas fa-sign-in-alt"></i> Se Connecter
                    </button>
                </form>

                <hr style="margin:28px 0;">

                <div class="text-center">
                    <p class="text-muted  mb-0" style="font-size:13.5px;">
                        Pas encore inscrit ?
                        <a href="{{ route('register') }}" style="color:var(--em);font-weight:600;text-decoration:none;">
                            <i class="fas fa-user-plus"></i> S'inscrire
                        </a>
                    </p>
                </div>

                <div class="alert alert-info mt-4" style="font-size:12px;">
                    <strong><i class="fas fa-lightbulb"></i> Identifiants de test :</strong>
                    <div class="mt-1"><code> Administrateur : ADMIN001 / admin123456</code></div>
                    <div><code> Etuiant :ATETTEH / ATETTEH2025</code></div>
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
