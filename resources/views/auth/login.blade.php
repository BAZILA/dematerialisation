@extends('layouts.app')

@section('title', 'Connexion')

@section('content')

<div style="width:100%;max-width:430px;position:relative;z-index:1;">

    {{-- Marque --}}
    <div class="text-center mb-4">
        <div class="icon-box mx-auto mb-3" style="width:64px;height:64px;font-size:1.6rem;
                    background:linear-gradient(135deg,var(--brand) 0%,#2C5494 100%);
                    color:#fff;box-shadow:0 10px 26px var(--em-glow);">
            <i class="fas fa-graduation-cap"></i>
        </div>
        <img src="{{ asset('images/uk_logo.png') }}" alt="Université de Kara"
            style="width:200px;height:auto;margin-bottom:10px;">

        <h1 style="font-family:var(--font-head);font-size:21px;font-weight:700;color:var(--t1);letter-spacing:-.02em;">
            Portail de Dématérialisation
        </h1>
        <p style="font-size:13px;color:var(--t2);margin-top:2px;">
            Gestion en ligne des dossiers &amp; demandes universitaires
        </p>
    </div>

    <div class="card" style="border-radius:18px!important;box-shadow:var(--shadow-lg)!important;">
        <div class="card-body" style="padding:34px!important;">

            <h2 style="font-family:var(--font-head);font-size:18px;font-weight:700;color:var(--t1);margin-bottom:20px;">
                Connexion
            </h2>

            {{-- Formulaire --}}
            <form method="POST" action="{{ route('login.post') }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-id-card"></i> Identifiant
                    </label>
                    <input type="text" class="form-control @error('numero_identifiant') is-invalid @enderror"
                        name="numero_identifiant" placeholder="Ex : ADMIN001" value="{{ old('numero_identifiant') }}"
                        autofocus required>
                    @error('numero_identifiant')
                    <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-key"></i> Mot de passe
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

                <button type="submit" class="btn btn-primary w-100" style="height:46px;font-size:15px;"
                    data-loading-text="Connexion…">
                    <i class="fas fa-right-to-bracket"></i> Se connecter
                </button>
            </form>

            <hr style="margin:24px 0;">

            <div class="text-center">
                <p class="mb-0" style="font-size:13.5px;color:var(--t2);">
                    Pas encore de compte ?
                    <a href="{{ route('register') }}" style="color:var(--brand);font-weight:600;text-decoration:none;">
                        <i class="fas fa-user-plus"></i> S'inscrire
                    </a>
                </p>
            </div>

            <div class="alert alert-info mt-4 mb-0" style="font-size:12px;">
                <strong><i class="fas fa-circle-info"></i> Démonstration</strong>
                <div class="mt-1">Administrateur : <code>ADMIN001</code> / <code>admin123456</code></div>
                <div class="mt-1" style="color:var(--t2);">
                    Les étudiants créent leur compte via « S'inscrire ».
                </div>
            </div>

        </div>
    </div>

    <p class="text-center mt-3" style="font-size:11.5px;color:var(--t3);">
        © {{ date('Y') }} Université — Portail de dématérialisation
    </p>
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