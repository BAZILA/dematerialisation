@extends('layouts.app')
@section('page-title', 'Candidature — ' . $candidature->full_name)
@section('page-subtitle', $candidature->reference)

@section('content')
    <div class="mb-3"><a href="{{ route('admin.candidatures.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a></div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4"><div class="card-body">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="icon-box bg-primary text-white" style="width:55px;height:55px;font-size:1.3rem;"><i class="fas fa-user"></i></div>
                    <div class="flex-grow-1">
                        <h5 class="mb-0 fw-bold" style="color:var(--t1);">{{ $candidature->full_name }}</h5>
                        <small class="text-muted"><code>{{ $candidature->reference }}</code> · {{ $candidature->email }}@if ($candidature->telephone) · {{ $candidature->telephone }}@endif</small>
                    </div>
                    <span class="badge bg-{{ $candidature->status_badge }} fs-6 px-3 py-2">{{ $candidature->statut_label }}</span>
                </div>
                <hr>
                <div class="row g-2 small">
                    <div class="col-md-4"><span class="text-muted">Sexe :</span> {{ $candidature->sexe }}</div>
                    <div class="col-md-4"><span class="text-muted">Naissance :</span> {{ $candidature->date_naissance?->format('d/m/Y') }}</div>
                    <div class="col-md-4"><span class="text-muted">Lieu :</span> {{ $candidature->lieu_naissance }}</div>
                    <div class="col-12 mt-2"><span class="text-muted">Parcours visé :</span>
                        <strong>{{ $candidature->parcours->nom ?? '—' }}</strong>
                        @if ($candidature->parcours)<span class="text-muted">({{ $candidature->parcours->departement->faculte->nom ?? '' }})</span>@endif
                    </div>
                </div>
            </div></div>

            <div class="card"><div class="card-header"><i class="fas fa-paperclip"></i> Pièces jointes</div>
                <div class="list-group list-group-flush">
                    @forelse ($candidature->files as $f)
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <i class="fas {{ $f->isPdf() ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary' }}"></i>
                            <div class="flex-grow-1"><strong>{{ $f->requestType->label ?? 'Document' }}</strong><br><small class="text-muted">{{ $f->original_filename }} ({{ $f->file_size_formatted }})</small></div>
                            <a href="{{ route('admin.candidatures.file.preview', $f) }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('admin.candidatures.file.download', $f) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download"></i></a>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">Aucune pièce jointe.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card"><div class="card-header bg-primary text-white"><i class="fas fa-gavel"></i> Décision</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.candidatures.status', $candidature) }}">
                        @csrf
                        <label class="form-label small">Statut</label>
                        <select class="form-select mb-3" name="status" required>
                            @foreach (['en_instruction' => 'En instruction', 'en_correction' => 'À compléter', 'admis' => 'Admettre', 'refuse' => 'Refuser'] as $k => $l)
                                <option value="{{ $k }}" @selected($candidature->status == $k)>{{ $l }}</option>
                            @endforeach
                        </select>
                        <label class="form-label small">Commentaire <span class="text-muted">(visible par le candidat au suivi)</span></label>
                        <textarea class="form-control mb-3" name="admin_comment" rows="3">{{ $candidature->admin_comment }}</textarea>
                        <button class="btn btn-primary w-100" data-loading-text="Enregistrement…"><i class="fas fa-save"></i> Enregistrer la décision</button>
                    </form>
                    @if ($candidature->traiteur)<div class="small text-muted mt-3">Traité par : {{ $candidature->traiteur->staff_name }}</div>@endif
                    @if ($candidature->decision_at)<div class="small text-muted">Décision le {{ $candidature->decision_at->format('d/m/Y à H:i') }}</div>@endif
                </div>
            </div>
        </div>
    </div>
@endsection
