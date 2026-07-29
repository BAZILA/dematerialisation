@extends('layouts.app')
@section('page-title', 'Demandes & Dépôts')
@section('page-subtitle', 'Toutes les demandes soumises par les étudiants')

@section('content')

    @php
        $cats = \App\Models\RequestType::categories();
        $catColors = [
            'depot' => 'primary',
            'attestation' => 'success',
            'diplome' => 'warning',
            'releve' => 'info',
            'duplicat' => 'danger',
        ];
        $statusColors = [
            'brouillon' => 'secondary',
            'soumis' => 'warning',
            'en_correction' => 'info',
            'validé' => 'success',
            'rejeté' => 'danger',
        ];
        $statusLabels = [
            'brouillon' => 'Brouillon',
            'soumis' => 'En attente',
            'en_correction' => 'En correction',
            'validé' => 'Validé',
            'rejeté' => 'Rejeté',
        ];
    @endphp

    {{-- Onglets catégories --}}
    <div class="d-flex gap-2 flex-wrap mb-4">
        <a href="{{ route('admin.requests.index', 'all') }}"
            class="btn btn-sm {{ $category === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">
            <i class="fas fa-th-list"></i> Toutes
        </a>
        @foreach (array_intersect_key($cats, array_flip($treatable ?? [])) as $key => $label)
            <a href="{{ route('admin.requests.index', $key) }}"
                class="btn btn-sm {{ $category === $key ? 'btn-' . ($catColors[$key] ?? 'secondary') : 'btn-outline-secondary' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Filtres --}}
    @php
        // Périmètre restreint = l'agent ne voit dans ses filtres que ce qu'il couvre
        $restreint = auth()->user()->perimeterParcoursIds() !== null;
        $anneeChoisie = request('annee_id', $anneeContext?->id);
        // Facultés / instituts / écoles regroupés par type
        $facParType = $facultes->groupBy(fn($f) => \App\Models\Faculte::$types[$f->type] ?? 'Autre');
        $nbFiltres = collect(['faculte_id', 'parcours_id', 'niveau', 'status', 'search'])->filter(
            fn($k) => request()->filled($k),
        )->count();
    @endphp

    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('admin.requests.index', $category) }}" class="row g-2 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label small mb-1" style="color:var(--t2);">Recherche</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                        placeholder="Nom, prénom, numéro identifiant…" value="{{ request('search') }}">
                </div>

                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small mb-1" style="color:var(--t2);">Statut</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        @foreach ($statusLabels as $k => $l)
                            <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>
                                {{ $l }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2 col-md-3 col-6">
                    <label class="form-label small mb-1" style="color:var(--t2);">Année académique</label>
                    <select name="annee_id" class="form-select form-select-sm">
                        @foreach ($annees as $a)
                            <option value="{{ $a->id }}" {{ (string) $anneeChoisie === (string) $a->id ? 'selected' : '' }}>
                                {{ $a->libelle }}{{ $a->is_active ? ' (en cours)' : '' }}
                            </option>
                        @endforeach
                        <option value="all" {{ request('annee_id') === 'all' ? 'selected' : '' }}>Toutes les années
                        </option>
                    </select>
                </div>

                <div class="col-lg-4 col-md-6">
                    <label class="form-label small mb-1" style="color:var(--t2);">
                        Faculté / Institut / École
                        @if ($restreint)
                            <i class="fas fa-user-shield ms-1" title="Limité à votre périmètre"></i>
                        @endif
                    </label>
                    <select name="faculte_id" class="form-select form-select-sm" onchange="this.form.parcours_id.value=''">
                        <option value="">Toutes</option>
                        @foreach ($facParType as $typeLabel => $liste)
                            <optgroup label="{{ $typeLabel }}">
                                @foreach ($liste as $f)
                                    <option value="{{ $f->id }}"
                                        {{ (string) request('faculte_id') === (string) $f->id ? 'selected' : '' }}>
                                        {{ $f->nom }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-4 col-md-6">
                    <label class="form-label small mb-1" style="color:var(--t2);">Parcours</label>
                    <select name="parcours_id" class="form-select form-select-sm">
                        <option value="">Tous les parcours</option>
                        @foreach ($parcoursList as $p)
                            <option value="{{ $p->id }}"
                                {{ (string) request('parcours_id') === (string) $p->id ? 'selected' : '' }}>
                                {{ $p->nom }} ({{ \App\Models\Parcours::$grades[$p->grade]['label'] ?? $p->grade }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <label class="form-label small mb-1" style="color:var(--t2);">Année d'études</label>
                    <select name="niveau" class="form-select form-select-sm">
                        <option value="">Toutes les années</option>
                        @foreach ($niveaux as $code => $label)
                            <option value="{{ $code }}" {{ request('niveau') === $code ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-5 col-md-6 filtre-actions">
                    <button type="submit" class="btn btn-primary btn-filtrer">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    @if ($nbFiltres)
                        <a href="{{ route('admin.requests.index', $category) }}"
                            class="btn btn-outline-secondary btn-reset" title="Réinitialiser les filtres">
                            <i class="fas fa-times"></i> {{ $nbFiltres }}
                        </a>
                    @endif
                </div>
            </form>

            @if ($restreint)
                <div class="small mt-2" style="color:var(--t3);">
                    <i class="fas fa-user-shield"></i>
                    Les filtres sont limités à votre périmètre d'affectation.
                </div>
            @endif
        </div>
    </div>

    {{-- Tableau --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span style="color:var(--t1);font-weight:600;">
                <i class="fas fa-list"></i> Résultats
            </span>
            <div class="d-flex align-items-center gap-3">
                <small style="color:var(--t2);">{{ $requests->total() }} demande(s)</small>
                @php
                    $qs = request()->getQueryString();
                    $baseExport = route('admin.requests.export', $category) . ($qs ? '?' . $qs : '');
                    $sepPdf = $qs ? '&' : '?';
                @endphp
                <div class="btn-group btn-group-sm">
                    <a href="{{ $baseExport }}"
                        class="btn btn-success" title="Exporter la liste (Excel) pour le logiciel d'inscription">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="{{ $baseExport }}{{ $sepPdf }}format=pdf"
                        class="btn btn-outline-danger" title="Rapport PDF avec en-tête de l'université">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Étudiant</th>
                        <th>Catégorie</th>
                        <th>Parcours / Année</th>
                        <th>Profil</th>
                        <th>Fichiers</th>
                        <th>Soumis le</th>
                        <th>Statut</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr @if ($req->status === 'soumis') style="background:rgba(245,158,11,.09);box-shadow:inset 3px 0 0 var(--amber,#f59e0b);" title="En attente de traitement" @endif>
                            <td>
                                <strong style="color:var(--t1);">{{ $req->student->full_name }}</strong><br>
                                <code class="small">{{ $req->student->user->numero_identifiant }}</code>
                            </td>
                            <td>
                                <span class="badge bg-{{ $catColors[$req->category] ?? 'secondary' }}">
                                    {{ $cats[$req->category] ?? $req->category }}
                                </span>
                            </td>
                            <td>
                                @if ($req->parcours)
                                    <span style="color:var(--t1);">{{ $req->parcours->nom }}</span><br>
                                    <small style="color:var(--t2);">
                                        {{ $req->parcours->departement?->faculte?->nom ?? '—' }}
                                        @if ($req->semestre)
                                            @php $derog = \App\Models\Parcours::estDerogatoire($req->semestre, $req->parcours->grade); @endphp
                                            · <span class="{{ $derog ? 'text-warning fw-semibold' : '' }}">
                                                {{ \App\Models\Parcours::libelleNiveau($req->semestre, $req->parcours->grade) }}
                                            </span>
                                        @endif
                                    </small>
                                @else
                                    <span style="color:var(--t3);">—</span>
                                @endif
                            </td>
                            <td>
                                <span
                                    class="badge bg-{{ $req->student->user->type_etudiant === 'nouveau' ? 'primary' : 'secondary' }}">
                                    {{ $req->student->user->type_etudiant === 'nouveau' ? 'Nouveau' : 'Ancien' }}
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-secondary">
                                    <i class="fas fa-paperclip"></i> {{ $req->files->count() }}
                                </span>
                            </td>
                            <td>
                                @if ($req->submitted_at)
                                    <small style="color:var(--t1);">{{ $req->submitted_at->format('d/m/Y') }}</small>
                                @else
                                    <span style="color:var(--t3);">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $statusColors[$req->status] ?? 'secondary' }}">
                                    {{ $statusLabels[$req->status] ?? $req->status }}
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.requests.show', $req) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5" style="color:var(--t3);">
                                <i class="fas fa-inbox d-block fs-2 mb-2"></i>
                                Aucune demande trouvée
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $requests->links('pagination::bootstrap-5') }}
    </div>

@endsection
