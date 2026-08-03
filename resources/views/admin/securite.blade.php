@extends('layouts.app')
@section('page-title', 'Sécurité du compte')
@section('page-subtitle', 'Double authentification (2FA) — protégez votre accès')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">

            {{-- ═══ CODES DE SECOURS — affichés une seule fois ═══ --}}
            @if (session('codes_secours'))
                <div class="card mb-4" style="border:2px solid var(--brand,#0d6efd);">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span><i class="fas fa-key"></i> Vos codes de secours</span>
                        <span class="badge bg-warning text-dark">Affichés une seule fois</span>
                    </div>
                    <div class="card-body">
                        <p class="small mb-3" style="color:var(--t2);">
                            Conservez-les hors de votre téléphone — imprimés, ou dans un gestionnaire
                            de mots de passe. Chacun remplace votre code à 6 chiffres <strong>une fois</strong>,
                            si vous perdez l'accès à votre application d'authentification.
                        </p>

                        <div class="row row-cols-2 row-cols-md-4 g-2 mb-3" id="blocCodes">
                            @foreach (session('codes_secours') as $code)
                                <div class="col">
                                    <div class="text-center py-2 rounded"
                                        style="background:var(--bg2,#f6f7f9);border:1px solid var(--line);
                                               font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
                                               font-weight:700;letter-spacing:.05em;user-select:all;">
                                        {{ $code }}
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-primary" id="btnCopierCodes">
                                <i class="fas fa-copy"></i> Copier les codes
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print();">
                                <i class="fas fa-print"></i> Imprimer
                            </button>
                        </div>
                    </div>
                </div>

                <script>
                    document.getElementById('btnCopierCodes')?.addEventListener('click', function () {
                        const codes = [...document.querySelectorAll('#blocCodes .col')]
                            .map(c => c.textContent.trim()).join('\n');
                        navigator.clipboard?.writeText(codes).then(() => {
                            this.innerHTML = '<i class="fas fa-check"></i> Copiés';
                            setTimeout(() => {
                                this.innerHTML = '<i class="fas fa-copy"></i> Copier les codes';
                            }, 2000);
                        });
                    });
                </script>
            @endif

            @if ($user->hasTwoFactor())
                {{-- ═══ 2FA ACTIVE ═══ --}}
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-shield-halved fs-1 mb-3" style="color:var(--green,#2F9E33);"></i>
                        <h5 class="fw-bold" style="color:var(--t1);">Double authentification active</h5>
                        <p style="color:var(--t2);max-width:460px;margin:0 auto;">
                            Depuis le {{ $user->two_factor_confirmed_at->format('d/m/Y à H:i') }}.
                            Un code à 6 chiffres est demandé à chaque connexion.
                        </p>

                        {{-- Codes de secours : combien il en reste, comment en refaire --}}
                        @php $restants = $user->codesSecoursRestants(); @endphp
                        <div class="mt-4 pt-4 mx-auto text-start" style="border-top:1px solid var(--line);max-width:380px;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-semibold small" style="color:var(--t2);">
                                    <i class="fas fa-key"></i> Codes de secours
                                </span>
                                <span class="badge bg-{{ $restants === 0 ? 'danger' : ($restants <= 2 ? 'warning text-dark' : 'secondary') }}">
                                    {{ $restants }} restant{{ $restants > 1 ? 's' : '' }}
                                </span>
                            </div>
                            <p class="small mb-2" style="color:var(--t3);">
                                @if ($restants === 0)
                                    Aucun code disponible : si vous perdez votre téléphone, seul un
                                    gestionnaire pourra vous débloquer. Générez-en dès maintenant.
                                @else
                                    Ils remplacent votre code à 6 chiffres en cas de perte du téléphone.
                                    En générer de nouveaux annule les précédents.
                                @endif
                            </p>
                            <form method="POST" action="{{ route('admin.securite.2fa.codes') }}"
                                onsubmit="return confirm('Générer de nouveaux codes ? Les anciens cesseront de fonctionner.');">
                                @csrf
                                <div class="d-flex gap-2">
                                    <input type="password" name="password" class="form-control form-control-sm"
                                        placeholder="Votre mot de passe" required>
                                    <button type="submit" class="btn btn-sm btn-outline-primary flex-shrink-0">
                                        <i class="fas fa-rotate"></i> Générer
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="mt-4 pt-4 mx-auto text-start" style="border-top:1px solid var(--line);max-width:380px;">
                            <div class="fw-semibold small mb-2" style="color:var(--t2);">
                                <i class="fas fa-triangle-exclamation text-warning"></i> Désactiver la 2FA
                            </div>
                            <form method="POST" action="{{ route('admin.securite.2fa.desactiver') }}"
                                onsubmit="return confirm('Désactiver la double authentification ? Votre compte sera moins protégé.');">
                                @csrf
                                <div class="d-flex gap-2">
                                    <input type="password" name="password" class="form-control form-control-sm"
                                        placeholder="Votre mot de passe" required>
                                    <button type="submit" class="btn btn-sm btn-outline-danger flex-shrink-0">
                                        Désactiver
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @else
                {{-- ═══ ACTIVATION ═══ --}}
                <div class="card">
                    <div class="card-header"><i class="fas fa-shield-halved"></i> Activer la double authentification</div>
                    <div class="card-body">
                        <div class="row g-4 align-items-center">
                            <div class="col-md-5 text-center">
                                <div class="p-3 rounded d-inline-block" style="background:#fff;border:1px solid var(--line);">
                                    {!! $qrSvg !!}
                                </div>
                                <div class="small mt-2" style="color:var(--t3);">
                                    Clé manuelle : <code style="user-select:all;">{{ $secret }}</code>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <ol class="small" style="color:var(--t2);padding-left:18px;">
                                    <li class="mb-2">Installez <strong>Google Authenticator</strong>, <strong>Authy</strong>
                                        ou <strong>FreeOTP</strong> sur votre téléphone.</li>
                                    <li class="mb-2">Scannez le QR code ci-contre (ou saisissez la clé manuelle).</li>
                                    <li class="mb-2">Entrez le code à 6 chiffres affiché pour confirmer.</li>
                                </ol>
                                <form method="POST" action="{{ route('admin.securite.2fa.activer') }}" class="mt-3">
                                    @csrf
                                    <label class="form-label small" style="color:var(--t2);">Code de confirmation</label>
                                    <div class="d-flex gap-2">
                                        <input type="text" name="code" class="form-control" maxlength="6" pattern="\d{6}"
                                            inputmode="numeric" autocomplete="one-time-code" placeholder="123456"
                                            style="max-width:160px;letter-spacing:.3em;font-weight:700;" required>
                                        <button type="submit" class="btn btn-primary" data-loading-text="Vérification…">
                                            <i class="fas fa-check"></i> Activer
                                        </button>
                                    </div>
                                </form>
                                <p class="small mt-3 mb-0" style="color:var(--t3);">
                                    <i class="fas fa-circle-info"></i> Une fois activée, un code vous sera demandé
                                    à chaque connexion au back-office. En cas de perte du téléphone,
                                    un gestionnaire pourra réinitialiser votre 2FA.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection
