@extends('layouts.app')
@section('page-title', 'Candidatures')
@section('page-subtitle', "Demandes d'admission soumises en ligne")

@section('content')
    @php $statuts = \App\Models\Candidature::$statuts; @endphp

    @php
        $anneeChoisie = request('annee_id', $anneeContext?->id);
        $facParType = $facultes->groupBy(fn($f) => \App\Models\Faculte::$types[$f->type] ?? 'Autre');
        $nbFiltres = collect(['faculte_id', 'parcours_id', 'status', 'search'])->filter(fn($k) => request()->filled($k))->count();
    @endphp

    <div class="card mb-4"><div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label class="form-label small mb-1" style="color:var(--t2);">Recherche</label>
                <input name="search" class="form-control form-control-sm" placeholder="Nom, référence, email…" value="{{ request('search') }}">
            </div>
            <div class="col-lg-3 col-md-3 col-6">
                <label class="form-label small mb-1" style="color:var(--t2);">Statut</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach ($statuts as $k => $l)<option value="{{ $k }}" @selected(request('status') == $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-3 col-6">
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
            <div class="col-lg-2 col-md-6 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-search"></i> Filtrer</button>
                @if ($nbFiltres)
                    <a href="{{ route('admin.candidatures.index') }}" class="btn btn-outline-secondary btn-sm" title="Réinitialiser"><i class="fas fa-times"></i> {{ $nbFiltres }}</a>
                @endif
            </div>

            <div class="col-lg-6 col-md-6">
                <label class="form-label small mb-1" style="color:var(--t2);">
                    Faculté / Institut / École
                    @if ($restreint)<i class="fas fa-user-shield ms-1" title="Limité à votre périmètre"></i>@endif
                </label>
                <select name="faculte_id" class="form-select form-select-sm" onchange="this.form.parcours_id.value=''">
                    <option value="">Toutes</option>
                    @foreach ($facParType as $typeLabel => $liste)
                        <optgroup label="{{ $typeLabel }}">
                            @foreach ($liste as $f)
                                <option value="{{ $f->id }}" @selected((string) request('faculte_id') === (string) $f->id)>{{ $f->nom }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-6 col-md-6">
                <label class="form-label small mb-1" style="color:var(--t2);">Parcours demandé (l'un des vœux)</label>
                <select name="parcours_id" class="form-select form-select-sm">
                    <option value="">Tous les parcours</option>
                    @foreach ($parcoursList as $p)
                        <option value="{{ $p->id }}" @selected((string) request('parcours_id') === (string) $p->id)>
                            {{ $p->nom }} ({{ \App\Models\Parcours::$grades[$p->grade]['label'] ?? $p->grade }})
                        </option>
                    @endforeach
                </select>
            </div>
        </form>

        @if ($restreint)
            <div class="small mt-2" style="color:var(--t3);">
                <i class="fas fa-user-shield"></i>
                Vous ne voyez que les candidatures dont au moins un vœu relève de votre périmètre
                (les candidatures se filtrent <strong>par parcours uniquement</strong> — un candidat n'a pas d'année d'études).
            </div>
        @endif
    </div></div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span style="font-weight:600;color:var(--t1);"><i class="fas fa-file-signature"></i> Candidatures</span>
            <small class="text-muted">{{ $candidatures->total() }} candidature(s)</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Candidat</th><th>Référence</th><th>Parcours visé</th><th>Année</th><th>Pièces</th><th>Soumise</th><th>Statut</th><th class="text-center">Action</th></tr></thead>
                <tbody>
                    @forelse ($candidatures as $c)
                        <tr>
                            <td><strong>{{ $c->full_name }}</strong><br><small class="text-muted">{{ $c->email }}</small></td>
                            <td><code class="small">{{ $c->reference }}</code></td>
                            <td class="small">
                                {{ $c->parcours->nom ?? '—' }}
                                @if (($c->choix_count ?? 0) > 1)
                                    <span class="badge bg-secondary ms-1" title="Le candidat a classé {{ $c->choix_count }} vœux">+{{ $c->choix_count - 1 }} vœu(x)</span>
                                @endif
                            </td>
                            <td class="small" style="color:var(--t2);">{{ $c->anneeAcademique->libelle ?? '—' }}</td>
                            <td><span class="badge bg-secondary"><i class="fas fa-paperclip"></i> {{ $c->files_count }}</span></td>
                            <td class="small">{{ $c->created_at->format('d/m/Y') }}</td>
                            <td><span class="badge bg-{{ $c->status_badge }}">{{ $c->statut_label }}</span></td>
                            <td class="text-center"><a href="{{ route('admin.candidatures.show', $c) }}" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-inbox d-block fs-2 mb-2"></i>Aucune candidature.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex justify-content-center mt-4">{{ $candidatures->links('pagination::bootstrap-5') }}</div>
@endsection
