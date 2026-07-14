@extends('layouts.app')

@section('title', 'Connexion')

@section('css')
    <style>
        .login-wrap { width: 100%; max-width: 430px; position: relative; z-index: 1; }
        .login-logo { text-align: center; margin-bottom: 22px; }
        .login-logo img { height: 66px; width: auto; }
        .login-card { border-radius: 22px !important; box-shadow: 0 24px 60px rgba(20, 40, 80, .16) !important; overflow: hidden; }
        .login-top { height: 6px; background: linear-gradient(90deg, #1A56A8 0%, #2F9E33 100%); }
        .login-body { padding: 34px 34px 30px; }
        .login-body h2 { font-family: var(--font-head); font-size: 21px; font-weight: 800; color: var(--t1); letter-spacing: -.02em; }
    </style>
@endsection

@section('content')
    <div class="login-wrap">
        <div class="login-logo">
            <img src="{{ asset('assets/uk/logo-sigle-bg.png') }}" alt="Université de Kara">
        </div>

        <div class="card login-card">
            <div class="login-top"></div>
            <div class="login-body">
                <h2>Connexion</h2>
                <p style="font-size:13.5px;color:var(--t2);margin:3px 0 22px;">Accédez à votre espace personnel.</p>

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
                    <button type="submit" class="btn btn-primary w-100" style="height:48px;font-size:15px;" data-loading-text="Connexion…">
                        <i class="fas fa-right-to-bracket"></i> Se connecter
                    </button>
                </form>

                <div class="text-center mt-4" style="font-size:13.5px;color:var(--t2);">
                    Nouveau ? <a href="{{ route('register') }}" style="color:var(--brand);font-weight:600;text-decoration:none;">Créer mon compte</a>
                </div>

                <div class="alert alert-info mt-4 mb-0" style="font-size:12px;">
                    <strong><i class="fas fa-circle-info"></i> Démonstration</strong>
                    <div class="mt-1">Administrateur : <code>ADMIN001</code> / <code>admin123456</code></div>
                    <div class="mt-1" style="color:var(--t2);">Les étudiants créent leur compte via « Créer mon compte ».</div>
                </div>
            </div>
        </div>

        <p class="text-center mt-3" style="font-size:11.5px;color:var(--t3);">
            © {{ date('Y') }} Université de Kara — Portail de dématérialisation
        </p>
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
