@extends('layouts.app')
@section('page-title', 'Paiements')
@section('page-subtitle', 'Frais de dossier — rapprochement des versements')

@section('content')
    @php
        $statuts    = \App\Models\Paiement::$statuts;
        $operateurs = \App\Models\Paiement::$operateurs;
        $anneeChoisie = request('annee_id', $anneeContext?->id);
        $nbFiltres = collect(['statut', 'procedure', 'operateur', 'search'])->filter(fn($k) => request()->filled($k))->count();
        $fmt = fn($m) => number_format($m, 0, ',', ' ') . ' FCFA';
    @endphp

    {{-- ═══ Chiffres clés ═══ --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body text-center">
                <div class="fw-bold" style="font-size:1.5rem;color:var(--green);">{{ $fmt($totaux['encaisse']) }}</div>
                <div class="small text-muted">Encaissé</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body text-center">
                <div class="fw-bold" style="font-size:1.5rem;color:var(--t1);">{{ $fmt($totaux['attente']) }}</div>
                <div class="small text-muted">En attente de règlement</div>
            </div></div>
        </div>
        <div class="col-md-4">
            <div class="card h-100"><div class="card-body text-center">
                <div class="fw-bold" style="font-size:1.5rem;color:{{ $totaux['a_verifier'] ? 'var(--amber)' : 'var(--t1)' }};">
                    {{ $totaux['a_verifier'] }}
                </div>
                <div class="small text-muted">Transaction(s) à vérifier</div>
            </div></div>
        </div>
    </div>

    {{-- ═══ Filtres ═══ --}}
    <div class="card mb-4"><div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label class="form-label small mb-1" style="color:var(--t2);">Recherche</label>
                <input name="search" class="form-control form-control-sm"
                    placeholder="Référence, n° de transaction, numéro payeur…" value="{{ request('search') }}">
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small mb-1" style="color:var(--t2);">Statut</label>
                <select name="statut" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach ($statuts as $k => $l)
                        <option value="{{ $k }}" @selected(request('statut') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label class="form-label small mb-1" style="color:var(--t2);">Année académique</label>
                <select name="annee_id" class="form-select form-select-sm">
                    @foreach ($annees as $a)
                        <option value="{{ $a->id }}" @selected((string) $anneeChoisie === (string) $a->id)>
                            {{ $a->libelle }}{{ $a->is_active ? ' (en cours)' : '' }}
                        </option>
                    @endforeach
                    <option value="all" @selected(request('annee_id') === 'all')>Toutes les années</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small mb-1" style="color:var(--t2);">Procédure</label>
                <select name="procedure" class="form-select form-select-sm">
                    <option value="">Toutes</option>
                    @foreach ($procedures as $code => $nom)
                        <option value="{{ $code }}" @selected(request('procedure') === $code)>{{ $nom }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small mb-1" style="color:var(--t2);">Opérateur</label>
                <select name="operateur" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach ($operateurs as $k => $l)
                        <option value="{{ $k }}" @selected(request('operateur') === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 filtre-actions">
                <button class="btn btn-primary btn-filtrer"><i class="fas fa-search"></i> Filtrer</button>
                @if ($nbFiltres)
                    <a href="{{ route('admin.paiements.index') }}" class="btn btn-outline-secondary btn-reset"
                        title="Réinitialiser"><i class="fas fa-times"></i> {{ $nbFiltres }}</a>
                @endif
            </div>
        </form>
    </div></div>

    {{-- ═══ Tableau ═══ --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span style="font-weight:600;color:var(--t1);"><i class="fas fa-money-bill-transfer"></i> Paiements</span>
            <div class="d-flex align-items-center gap-3">
                <small class="text-muted">{{ $paiements->total() }} ligne(s)</small>
                @php $qs = request()->getQueryString(); $qs = $qs ? '?' . $qs : ''; @endphp
                <div class="btn-group btn-group-sm">
                    <a href="{{ route('admin.paiements.export', 'excel') }}{{ $qs }}" class="btn btn-outline-success">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="{{ route('admin.paiements.export', 'csv') }}{{ $qs }}" class="btn btn-outline-secondary">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                    <a href="{{ route('admin.paiements.export', 'pdf') }}{{ $qs }}" class="btn btn-outline-danger"
                        title="Rapport PDF avec en-tête de l'université">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Payeur / dossier</th>
                        <th>Procédure</th>
                        <th>Montant</th>
                        <th>Transaction</th>
                        <th>Statut</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($paiements as $p)
                        @php $payable = $p->payable; @endphp
                        <tr @if ($p->statut === 'soumis') style="background:rgba(245,158,11,.09);box-shadow:inset 3px 0 0 var(--amber,#f59e0b);" title="Transaction à vérifier" @endif>
                            <td>
                                <code class="small">{{ $p->reference }}</code><br>
                                <small class="text-muted">{{ $p->created_at->format('d/m/Y') }}</small>
                            </td>
                            <td>
                                @if ($payable instanceof \App\Models\Candidature)
                                    <strong style="color:var(--t1);">{{ $payable->full_name }}</strong><br>
                                    <small class="text-muted">Candidature {{ $payable->reference }}</small>
                                @elseif ($payable instanceof \App\Models\StudentRequest)
                                    <strong style="color:var(--t1);">{{ $payable->student?->full_name ?? '—' }}</strong><br>
                                    <small class="text-muted">Demande #{{ $payable->id }}</small>
                                @else
                                    <span class="text-muted">Dossier supprimé</span>
                                @endif
                            </td>
                            <td class="small">{{ $procedures[$p->procedure_code] ?? $p->procedure_code }}</td>
                            <td><strong style="color:var(--t1);">{{ $p->montant_formate }}</strong></td>
                            <td class="small">
                                @if ($p->transaction_id)
                                    <code>{{ $p->transaction_id }}</code><br>
                                    <small class="text-muted">{{ $p->operateur_label }}{{ $p->numero_payeur ? ' · ' . $p->numero_payeur : '' }}</small>
                                @else
                                    <span class="text-muted">Non déclarée</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $p->statut_badge }}">{{ $p->statut_label }}</span>
                                @if ($p->verifie_auto)
                                    <br><small class="text-muted"><i class="fas fa-bolt"></i> auto</small>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.paiements.show', $p) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-inbox d-block fs-2 mb-2"></i>
                                Aucun paiement pour ces critères.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">{{ $paiements->links('pagination::bootstrap-5') }}</div>
@endsection
