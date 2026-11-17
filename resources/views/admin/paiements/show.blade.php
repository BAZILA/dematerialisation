@extends('layouts.app')
@section('page-title', 'Paiement ' . $paiement->reference)
@section('page-subtitle', 'Rapprochement du versement')

@section('content')
    @php $payable = $paiement->payable; @endphp

    <a href="{{ route('admin.paiements.index') }}" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="fas fa-arrow-left"></i> Retour aux paiements
    </a>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-receipt"></i> Détail</span>
                    <span class="badge bg-{{ $paiement->statut_badge }}">{{ $paiement->statut_label }}</span>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4 p-3 rounded"
                        style="background:var(--surface-2);border:1px solid var(--line);">
                        <div class="fw-bold" style="font-size:2rem;color:var(--brand);">{{ $paiement->montant_formate }}</div>
                        <div class="small text-muted">Montant dû</div>
                    </div>

                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><td class="text-muted" style="width:220px;">Référence</td>
                                <td><code>{{ $paiement->reference }}</code></td></tr>
                            <tr><td class="text-muted">Procédure</td>
                                <td>{{ \App\Models\Procedure::labels()[$paiement->procedure_code] ?? $paiement->procedure_code }}</td></tr>
                            <tr><td class="text-muted">Année académique</td>
                                <td>{{ $paiement->anneeAcademique?->libelle ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Payeur</td>
                                <td>
                                    @if ($payable instanceof \App\Models\Candidature)
                                        <strong>{{ $payable->full_name }}</strong> —
                                        <a href="{{ route('admin.candidatures.show', $payable) }}">candidature {{ $payable->reference }}</a>
                                    @elseif ($payable instanceof \App\Models\StudentRequest)
                                        <strong>{{ $payable->student?->full_name ?? '—' }}</strong> —
                                        <a href="{{ route('admin.requests.show', $payable) }}">demande #{{ $payable->id }}</a>
                                    @else
                                        <span class="text-muted">Dossier supprimé</span>
                                    @endif
                                </td></tr>
                            <tr><td class="text-muted">Opérateur</td><td>{{ $paiement->operateur_label }}</td></tr>
                            <tr><td class="text-muted">N° de transaction</td>
                                <td>@if ($paiement->transaction_id)<code>{{ $paiement->transaction_id }}</code>@else <span class="text-muted">Non déclarée</span> @endif</td></tr>
                            <tr><td class="text-muted">Numéro payeur</td><td>{{ $paiement->numero_payeur ?: '—' }}</td></tr>
                            <tr><td class="text-muted">Déclaré le</td><td>{{ $paiement->declare_at?->format('d/m/Y à H:i') ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Vérifié par</td>
                                <td>
                                    @if ($paiement->verifie_auto)
                                        <span class="badge bg-info"><i class="fas fa-bolt"></i> Opérateur (automatique)</span>
                                    @else
                                        {{ $paiement->validateur?->staff_name ?? '—' }}
                                    @endif
                                    @if ($paiement->valide_at)
                                        <small class="text-muted">le {{ $paiement->valide_at->format('d/m/Y à H:i') }}</small>
                                    @endif
                                </td></tr>
                        </tbody>
                    </table>

                    @if ($paiement->motif_rejet)
                        <div class="alert alert-danger small mt-3 mb-0">
                            <i class="fas fa-comment-dots"></i> <strong>Motif du rejet :</strong> {{ $paiement->motif_rejet }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ═══ Actions ═══ --}}
        <div class="col-lg-4">
            <div class="card sticky-top" style="top:80px;">
                <div class="card-header bg-primary text-white"><i class="fas fa-gavel"></i> Rapprochement</div>
                <div class="card-body">
                    @if ($paiement->statut === 'en_attente')
                        <div class="alert alert-secondary small mb-0">
                            <i class="fas fa-hourglass-half"></i>
                            L'usager n'a pas encore déclaré de transaction. Rien à vérifier pour l'instant.
                        </div>
                    @elseif ($paiement->estPaye())
                        <div class="alert alert-success small">
                            <i class="fas fa-circle-check"></i> Paiement validé.
                        </div>
                        <a href="{{ route('admin.paiements.recu', $paiement) }}" class="btn btn-outline-primary w-100">
                            <i class="fas fa-file-pdf"></i> Télécharger le reçu
                        </a>
                    @else
                        <p class="small" style="color:var(--t2);">
                            Comparez l'identifiant de transaction avec le relevé de l'opérateur avant de valider.
                        </p>

                        @can('paiements.confirmer')
                            <form method="POST" action="{{ route('admin.paiements.valider', $paiement) }}" class="mb-3"
                                onsubmit="return confirm('Confirmer la réception de {{ $paiement->montant_formate }} ?');">
                                @csrf
                                <button class="btn btn-success w-100">
                                    <i class="fas fa-check"></i> Versement reçu — valider
                                </button>
                            </form>

                            <form method="POST" action="{{ route('admin.paiements.rejeter', $paiement) }}">
                                @csrf
                                <label class="form-label small">Motif du rejet <span class="text-danger">*</span></label>
                                <textarea name="motif_rejet" class="form-control form-control-sm mb-2" rows="3"
                                    maxlength="500" required
                                    placeholder="Ex : transaction introuvable, montant insuffisant…"></textarea>
                                <button class="btn btn-outline-danger w-100">
                                    <i class="fas fa-xmark"></i> Rejeter
                                </button>
                                <small class="text-muted d-block mt-2">
                                    L'usager verra le motif et pourra déclarer une nouvelle transaction.
                                </small>
                            </form>
                        @else
                            <div class="alert alert-secondary small mb-0">
                                Vous pouvez consulter ce paiement mais pas le valider.
                            </div>
                        @endcan
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
