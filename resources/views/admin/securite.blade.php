@extends('layouts.app')
@section('page-title', 'Sécurité du compte')
@section('page-subtitle', 'Double authentification (2FA) — protégez votre accès')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">

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
