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
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('admin.requests.index', $category) }}" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control form-control-sm"
                        placeholder="Nom, prénom, numéro identifiant…" value="{{ request('search') }}">
                </div>
                <div class="col-md-4">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Tous les statuts</option>
                        @foreach ($statusLabels as $k => $l)
                            <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>
                                {{ $l }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                    <a href="{{ route('admin.requests.index', $category) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
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
                <a href="{{ route('admin.requests.export', $category) }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
                    class="btn btn-sm btn-success" title="Exporter la liste (Excel) pour le logiciel d'inscription">
                    <i class="fas fa-file-excel"></i> Exporter
                </a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Étudiant</th>
                        <th>Catégorie</th>
                        <th>Profil</th>
                        <th>Fichiers</th>
                        <th>Soumis le</th>
                        <th>Statut</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr>
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
                            <td colspan="7" class="text-center py-5" style="color:var(--t3);">
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
