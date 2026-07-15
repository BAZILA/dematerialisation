@extends('layouts.app')
@section('page-title', 'Candidatures')
@section('page-subtitle', "Demandes d'admission soumises en ligne")

@section('content')
    @php $statuts = \App\Models\Candidature::$statuts; @endphp

    <div class="card mb-4"><div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-5"><input name="search" class="form-control form-control-sm" placeholder="Nom, référence, email…" value="{{ request('search') }}"></div>
            <div class="col-md-4"><select name="status" class="form-select form-select-sm">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $k => $l)<option value="{{ $k }}" @selected(request('status') == $k)>{{ $l }}</option>@endforeach
            </select></div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-search"></i> Filtrer</button>
                <a href="{{ route('admin.candidatures.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div></div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span style="font-weight:600;color:var(--t1);"><i class="fas fa-file-signature"></i> Candidatures</span>
            <small class="text-muted">{{ $candidatures->total() }} candidature(s)</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Candidat</th><th>Référence</th><th>Parcours visé</th><th>Pièces</th><th>Soumise</th><th>Statut</th><th class="text-center">Action</th></tr></thead>
                <tbody>
                    @forelse ($candidatures as $c)
                        <tr>
                            <td><strong>{{ $c->full_name }}</strong><br><small class="text-muted">{{ $c->email }}</small></td>
                            <td><code class="small">{{ $c->reference }}</code></td>
                            <td class="small">{{ $c->parcours->nom ?? '—' }}</td>
                            <td><span class="badge bg-secondary"><i class="fas fa-paperclip"></i> {{ $c->files_count }}</span></td>
                            <td class="small">{{ $c->created_at->format('d/m/Y') }}</td>
                            <td><span class="badge bg-{{ $c->status_badge }}">{{ $c->statut_label }}</span></td>
                            <td class="text-center"><a href="{{ route('admin.candidatures.show', $c) }}" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-inbox d-block fs-2 mb-2"></i>Aucune candidature.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex justify-content-center mt-4">{{ $candidatures->links('pagination::bootstrap-5') }}</div>
@endsection
