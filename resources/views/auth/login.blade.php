@extends('layouts.app')

@section('title', 'Connexion')

@section('css')
    <style>
        .auth-card { width: 100%; max-width: 960px; border-radius: 20px !important; overflow: hidden; box-shadow: var(--shadow-lg) !important; }
        .auth-aside {
            background: linear-gradient(150deg, #1A56A8 0%, #1E7B67 55%, #2F9E33 100%);
            color: #fff; padding: 44px 40px; position: relative; overflow: hidden;
        }
        .auth-aside::after { content: ''; position: absolute; right: -60px; bottom: -60px; width: 240px; height: 240px; border-radius: 50%; background: rgba(255, 255, 255, .10); }
        .auth-aside::before { content: ''; position: absolute; left: -40px; top: -40px; width: 150px; height: 150px; border-radius: 50%; background: rgba(255, 255, 255, .08); }
        .auth-feat { display: flex; align-items: center; gap: 12px; margin-top: 16px; font-size: 13.5px; color: rgba(255, 255, 255, .92); position: relative; z-index: 1; }
        .auth-feat i { width: 30px; height: 30px; border-radius: 9px; background: rgba(255, 255, 255, .15); display: flex; align-items: center; justify-content: center; font-size: 12px; }
        .auth-form { padding: 40px 42px; }
        .auth-form-logo { height: 54px; width: auto; margin-bottom: 22px; }
        @media (max-width: 767px) { .auth-form { padding: 30px 24px; } .auth-form-logo { height: 46px; } }
    </style>
@endsection

@section('content')
    <div class="card auth-card">
        <div class="row g-0">

            {{-- Panneau marque --}}
            <div class="col-md-5 auth-aside d-none d-md-flex flex-column">
                <h1 style="font-family:var(--font-head);font-size:24px;font-weight:800;letter-spacing:-.02em;position:relative;z-index:1;">
                    Portail de Dématérialisation
                </h1>
                <p style="font-size:14px;color:rgba(255,255,255,.88);margin-top:6px;position:relative;z-index:1;">
                    Dépôts, demandes et dossiers numériques de l'Université de Kara — en ligne.
                </p>
                <div style="margin-top:26px;">
                    <div class="auth-feat"><i class="fas fa-folder-open"></i> Déposez vos dossiers sans vous déplacer</div>
                    <div class="auth-feat"><i class="fas fa-file-shield"></i> Documents officiels vérifiables</div>
                    <div class="auth-feat"><i class="fas fa-clock-rotate-left"></i> Suivi en temps réel de vos demandes</div>
                </div>
                <div style="margin-top:auto;padding-top:26px;font-size:11.5px;color:rgba(255,255,255,.72);position:relative;z-index:1;">
                    © {{ date('Y') }} Université de Kara
                </div>
            </div>

            {{-- Formulaire --}}
            <div class="col-md-7 auth-form">
                <img src="{{ asset('assets/uk/logo-sigle-bg.png') }}" alt="Université de Kara" class="auth-form-logo">
                <h2 style="font-family:var(--font-head);font-size:22px;font-weight:700;color:var(--t1);">Bienvenue</h2>
                <p style="font-size:13.5px;color:var(--t2);margin-top:2px;margin-bottom:24px;">Connectez-vous à votre espace.</p>

                <form method="POST" action="{{ route('login.post') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-id-card"></i> Identifiant</label>
                        <input type="text" class="form-control @error('numero_identifiant') is-invalid @enderror"
                            name="numero_identifiant" placeholder="Ex : ADMIN001" value="{{ old('numero_identifiant') }}" autofocus required>
                        @error('numero_identifiant')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-key"></i> Mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="pwdLogin" name="password" placeholder="••••••••" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePwd('pwdLogin',this)"><i class="fas fa-eye"></i></button>
                        </div>
                        @error('password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100" style="height:46px;font-size:15px;" data-loading-text="Connexion…">
                        <i class="fas fa-right-to-bracket"></i> Se connecter
                    </button>
                </form>

                <hr style="margin:22px 0;">
                <p class="text-center mb-0" style="font-size:13.5px;color:var(--t2);">
                    Pas encore de compte ?
                    <a href="{{ route('register') }}" style="color:var(--brand);font-weight:600;text-decoration:none;"><i class="fas fa-user-plus"></i> S'inscrire</a>
                </p>

                <div class="alert alert-info mt-4 mb-0" style="font-size:12px;">
                    <strong><i class="fas fa-circle-info"></i> Démonstration</strong>
                    <div class="mt-1">Administrateur : <code>ADMIN001</code> / <code>admin123456</code></div>
                    <div class="mt-1" style="color:var(--t2);">Les étudiants créent leur compte via « S'inscrire ».</div>
                </div>
            </div>

        </div>
    </div>
@endsection

@section('js')
    <script>
        function togglePwd(id, btn) {
            const i = document.getElementById(id), ic = btn.querySelector('i');
            i.type = i.type === 'password' ? 'text' : 'password';
            ic.classList.toggle('fa-eye'); ic.classList.toggle('fa-eye-slash');
        }
    </script>
@endsection
