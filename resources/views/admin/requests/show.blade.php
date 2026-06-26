@extends('layouts.app')
@section('page-title', 'Dossier — ' . $studentRequest->student->full_name)
@section('page-subtitle', $studentRequest->category_label . ' · v#' . $studentRequest->version)

@section('content')

    @php
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
        $isDepot = $studentRequest->category === 'depot';
    @endphp

    <div class="mb-3">
        <a href="{{ route('admin.requests.index', $studentRequest->category) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
    </div>

    <div class="row g-4">

        {{-- ══════════════ Colonne principale ══════════════ --}}
        <div class="col-lg-8">

            {{-- Infos étudiant --}}
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="icon-box bg-primary text-white" style="width:55px;height:55px;font-size:1.3rem;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-0 fw-bold" style="color:var(--t1);">
                                {{ $studentRequest->student->full_name }}
                            </h5>
                            <small style="color:var(--t2);">
                                <code>{{ $studentRequest->student->user->numero_identifiant }}</code>
                                &nbsp;·&nbsp;
                                {{ $studentRequest->student->user->type_etudiant === 'nouveau' ? 'Nouveau Bachelier' : 'Ancien Étudiant' }}
                                &nbsp;·&nbsp; Version : <strong
                                    style="color:var(--t1);">#{{ $studentRequest->version }}</strong>
                                @if ($studentRequest->submitted_at)
                                    &nbsp;·&nbsp; Soumis le : <strong
                                        style="color:var(--t1);">{{ $studentRequest->submitted_at->format('d/m/Y') }}</strong>
                                @endif
                            </small>
                        </div>
                        <span class="badge bg-{{ $statusColors[$studentRequest->status] ?? 'secondary' }} fs-6 px-3 py-2">
                            {{ $statusLabels[$studentRequest->status] ?? $studentRequest->status }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Documents --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span style="color:var(--t1);font-weight:600;">
                        <i class="fas fa-paperclip"></i> Documents soumis
                    </span>
                    @if ($studentRequest->isSubmitted())
                        <small style="color:var(--t2);">Annotez chaque fichier avant de décider</small>
                    @endif
                </div>
                <div class="list-group list-group-flush">
                    @foreach ($types as $type)
                        @php $file = $studentRequest->fileForType($type); @endphp
                        <div class="list-group-item px-4 py-3"
                            style="{{ $file && $file->is_valid === false ? 'background:rgba(244,63,94,.07);' : ($file && $file->is_valid ? 'background:rgba(16,201,122,.06);' : '') }}">

                            <div class="d-flex align-items-start gap-3">

                                {{-- Icône statut --}}
                                <div class="mt-1">
                                    @if (!$file)
                                        <i class="fas fa-minus-circle fs-5" style="color:var(--t3);"></i>
                                    @elseif($file->is_valid === true)
                                        <i class="fas fa-check-circle text-success fs-5"></i>
                                    @elseif($file->is_valid === false)
                                        <i class="fas fa-times-circle text-danger fs-5"></i>
                                    @else
                                        <i class="fas fa-question-circle text-warning fs-5"></i>
                                    @endif
                                </div>

                                <div class="flex-grow-1">
                                    <div class="fw-semibold mb-1 d-flex align-items-center gap-2 flex-wrap"
                                        style="color:var(--t1);">
                                        {{ $type->label }}
                                        @if ($type->is_required)
                                            <span class="badge bg-danger small">Obligatoire</span>
                                        @endif
                                        @if ($isDepot && $type->has_stamp)
                                            <span class="badge small"
                                                style="background:rgba(59,130,246,.15);color:#93c5fd;border:1px solid rgba(59,130,246,.3);">
                                                <i class="fas fa-stamp"></i> Sera tamponné
                                            </span>
                                        @endif
                                        @if ($file?->is_stamped)
                                            <span class="badge small"
                                                style="background:rgba(59,130,246,.2);color:#93c5fd;border:1px solid rgba(59,130,246,.35);">
                                                <i class="fas fa-stamp"></i> Tamponné ✓
                                            </span>
                                        @endif
                                        @if ($file?->is_locked)
                                            <span class="badge bg-success small">
                                                <i class="fas fa-lock"></i> Verrouillé
                                            </span>
                                        @endif
                                    </div>

                                    @if (!$file)
                                        <span style="color:var(--t3);" class="small">
                                            <i class="fas fa-ban"></i> Aucun fichier fourni
                                        </span>
                                    @else
                                        <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                            <i
                                                class="fas {{ $file->isPdf() ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary' }}"></i>
                                            <span class="small"
                                                style="color:var(--t1);">{{ $file->original_filename }}</span>
                                            <span class="small"
                                                style="color:var(--t2);">({{ $file->file_size_formatted }})</span>
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#preview{{ $file->id }}">
                                                <i class="fas fa-eye"></i> Aperçu
                                            </button>
                                            <a href="{{ route('admin.requests.file.download', $file) }}"
                                                class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                        @if ($file->admin_comment)
                                            <div class="mt-2 p-2 rounded small"
                                                style="background:var(--amber-dim);border:1px solid rgba(245,158,11,.25);color:var(--amber);">
                                                <i class="fas fa-comment-alt"></i> {{ $file->admin_comment }}
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                {{-- Toggle correct (si soumis) --}}
                                @if ($studentRequest->isSubmitted() && $file)
                                    <div class="d-flex flex-column align-items-end gap-1 ms-2">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input file-valid-toggle" type="checkbox"
                                                data-file="{{ $file->id }}" id="v{{ $file->id }}"
                                                {{ $file->is_valid ? 'checked' : '' }}>
                                            <label class="form-check-label small" style="color:var(--t2);"
                                                for="v{{ $file->id }}">Correct</label>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Champ commentaire (si soumis) --}}
                            @if ($studentRequest->isSubmitted() && $file)
                                <div class="mt-2 ps-5">
                                    <input type="text" class="form-control form-control-sm file-comment-input"
                                        data-file="{{ $file->id }}"
                                        placeholder="Commentaire sur ce fichier (optionnel)…"
                                        value="{{ $file->admin_comment ?? '' }}">
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

        </div>{{-- /col principale --}}

        {{-- ══════════════ Colonne décision ══════════════ --}}
        <div class="col-lg-4">
            <div class="card sticky-top" style="top:80px;">

                @if ($studentRequest->isSubmitted())
                    {{-- ─── Actions de décision ─── --}}
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-tasks"></i> Décision</h6>
                    </div>
                    <div class="card-body">

                        {{-- Formulaire Valider --}}
                        <form method="POST" action="{{ route('admin.requests.validate', $studentRequest) }}"
                            id="valForm">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small" style="color:var(--t2);">
                                    Commentaire global <span style="color:var(--t3);">(optionnel)</span>
                                </label>
                                <textarea class="form-control form-control-sm" name="admin_comment" rows="2" placeholder="Note pour l'étudiant…"></textarea>
                            </div>

                            @if (!$isDepot)
                                {{-- Rendez-vous : uniquement pour les demandes non-dépôt --}}
                                <div class="mb-3 p-3 rounded"
                                    style="background:rgba(16,201,122,.07);border:1px solid rgba(16,201,122,.2);">
                                    <label class="form-label small fw-semibold" style="color:var(--em);">
                                        <i class="fas fa-calendar-check"></i> Date de rendez-vous pour le retrait
                                        <span class="text-danger">*</span>
                                    </label>
                                    <input type="datetime-local" class="form-control form-control-sm"
                                        name="rendez_vous_at" required>
                                    <div class="mt-2">
                                        <label class="form-label small" style="color:var(--t2);">
                                            Instructions / lieu de retrait <span
                                                style="color:var(--t3);">(optionnel)</span>
                                        </label>
                                        <textarea class="form-control form-control-sm" name="rendez_vous_note" rows="2"
                                            placeholder="Ex : Bâtiment A, bureau 12, apportez votre carte étudiant…"></textarea>
                                    </div>
                                </div>
                            @endif

                            <button type="button" class="btn btn-success w-100 mb-3" id="btnVal">
                                <i class="fas fa-check-circle"></i>
                                {{ $isDepot ? 'Valider le dossier' : 'Valider & fixer le rendez-vous' }}
                            </button>
                        </form>

                        <hr>

                        {{-- Formulaire renvoyer en correction --}}
                        <form method="POST" action="{{ route('admin.requests.reject', $studentRequest) }}"
                            id="corrForm">
                            @csrf
                            <div id="hiddenFileData"></div>
                            <label class="form-label fw-semibold small" style="color:var(--amber);">
                                <i class="fas fa-rotate-left"></i> Renvoyer pour correction
                            </label>
                            <textarea class="form-control form-control-sm mb-2" name="rejection_message" rows="3"
                                placeholder="Expliquez ce qui doit être corrigé…" minlength="10"></textarea>
                            <button type="button" class="btn btn-warning w-100 mb-3" id="btnCorr">
                                <i class="fas fa-rotate-left"></i> Renvoyer
                            </button>
                        </form>

                        <hr>

                        {{-- Rejet définitif --}}
                        <form method="POST" action="{{ route('admin.requests.reject-final', $studentRequest) }}"
                            id="rejForm">
                            @csrf
                            <label class="form-label fw-semibold small" style="color:var(--red);">
                                <i class="fas fa-ban"></i> Rejet définitif
                            </label>
                            <textarea class="form-control form-control-sm mb-2" name="rejection_message" rows="2"
                                placeholder="Motif du rejet…" minlength="10"></textarea>
                            <button type="button" class="btn btn-danger w-100" id="btnRej">
                                <i class="fas fa-ban"></i> Rejeter définitivement
                            </button>
                        </form>

                    </div>
                @elseif($studentRequest->isValidated())
                    <div class="card-body text-center py-4">
                        <i class="fas fa-check-circle text-success fs-1 mb-2"></i>
                        <h6 class="fw-bold" style="color:var(--em);">Demande validée</h6>
                        @if ($studentRequest->validated_at)
                            <small style="color:var(--t2);">
                                Le {{ $studentRequest->validated_at->format('d/m/Y à H:i') }}
                            </small>
                        @endif
                        @if (!$isDepot && $studentRequest->rendez_vous_at)
                            <div class="mt-3 p-3 rounded text-start"
                                style="background:rgba(16,201,122,.08);border:1px solid rgba(16,201,122,.2);">
                                <div class="fw-semibold small" style="color:var(--em);">
                                    <i class="fas fa-calendar-check"></i> Rendez-vous fixé :
                                </div>
                                <div style="color:var(--t1);">
                                    {{ $studentRequest->rendez_vous_at->format('d/m/Y à H:i') }}
                                </div>
                                @if ($studentRequest->rendez_vous_note)
                                    <div class="mt-1 small" style="color:var(--t2);">
                                        {{ $studentRequest->rendez_vous_note }}
                                    </div>
                                @endif
                            </div>
                        @endif
                        @if ($studentRequest->admin_comment)
                            <div class="alert alert-success mt-3 text-start small">
                                {{ $studentRequest->admin_comment }}
                            </div>
                        @endif
                    </div>
                @elseif($studentRequest->isInCorrection())
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <i class="fas fa-rotate-left text-warning fs-1"></i>
                            <h6 class="fw-bold mt-2" style="color:var(--amber);">En correction</h6>
                        </div>
                        <div class="alert alert-warning small">{{ $studentRequest->rejection_message }}</div>
                    </div>
                @elseif($studentRequest->isRejected())
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <i class="fas fa-ban text-danger fs-1"></i>
                            <h6 class="fw-bold mt-2" style="color:var(--red);">Rejetée définitivement</h6>
                        </div>
                        <div class="alert alert-danger small">{{ $studentRequest->rejection_message }}</div>
                    </div>
                @endif

            </div>
        </div>

    </div>{{-- /row --}}

    {{-- ══════════════ Modals aperçu fichiers ══════════════ --}}
    @foreach ($types as $type)
        @php $file = $studentRequest->fileForType($type); @endphp
        @if ($file)
            <div class="modal fade" id="preview{{ $file->id }}" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2">
                            <h6 class="modal-title" style="color:var(--t1);">
                                {{ $type->label }} — {{ $file->original_filename }}
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-0" style="height:80vh;">
                            @if ($file->isPdf())
                                <iframe src="{{ route('admin.requests.file.preview', $file) }}"
                                    style="width:100%;height:100%;border:none;"></iframe>
                            @else
                                <div class="d-flex justify-content-center align-items-center h-100"
                                    style="background:var(--surface-2);">
                                    <img src="{{ route('admin.requests.file.preview', $file) }}"
                                        style="max-height:100%;max-width:100%;object-fit:contain;">
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer py-2">
                            <a href="{{ route('admin.requests.file.download', $file) }}"
                                class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-download"></i> Télécharger
                            </a>
                            <button type="button" class="btn btn-sm btn-secondary"
                                data-bs-dismiss="modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

@endsection

@section('js')
    <script>
        (function() {
            function collectFiles(container) {
                container.innerHTML = '';
                document.querySelectorAll('.file-comment-input').forEach(inp => {
                    if (inp.value.trim()) {
                        const h = document.createElement('input');
                        h.type = 'hidden';
                        h.name = `file_comments[${inp.dataset.file}]`;
                        h.value = inp.value.trim();
                        container.appendChild(h);
                    }
                });
                document.querySelectorAll('.file-valid-toggle').forEach(chk => {
                    if (chk.checked) {
                        const h = document.createElement('input');
                        h.type = 'hidden';
                        h.name = `file_valid[${chk.dataset.file}]`;
                        h.value = '1';
                        container.appendChild(h);
                    }
                });
            }

            document.getElementById('btnVal')?.addEventListener('click', () => {
                const rdv = document.querySelector('[name="rendez_vous_at"]');
                if (rdv && !rdv.value) {
                    rdv.classList.add('is-invalid');
                    rdv.focus();
                    return;
                }
                if (confirm('Valider cette demande ?')) {
                    window.btnLoading(document.getElementById('btnVal'), 'Validation…');
                    document.getElementById('valForm').submit();
                }
            });

            document.getElementById('btnCorr')?.addEventListener('click', () => {
                const msg = document.querySelector('#corrForm [name="rejection_message"]');
                if (!msg || msg.value.trim().length < 10) {
                    msg?.classList.add('is-invalid');
                    return;
                }
                msg.classList.remove('is-invalid');
                if (confirm('Renvoyer pour correction ?')) {
                    collectFiles(document.getElementById('hiddenFileData'));
                    window.btnLoading(document.getElementById('btnCorr'), 'Envoi…');
                    document.getElementById('corrForm').submit();
                }
            });

            document.getElementById('btnRej')?.addEventListener('click', () => {
                const msg = document.querySelector('#rejForm [name="rejection_message"]');
                if (!msg || msg.value.trim().length < 10) {
                    msg?.classList.add('is-invalid');
                    return;
                }
                if (confirm('Rejeter définitivement cette demande ?')) {
                    window.btnLoading(document.getElementById('btnRej'), 'Rejet…');
                    document.getElementById('rejForm').submit();
                }
            });

            document.querySelectorAll('.file-valid-toggle').forEach(chk => {
                chk.addEventListener('change', function() {
                    const row = this.closest('.list-group-item');
                    if (this.checked) {
                        row.style.background = 'rgba(16,201,122,.06)';
                    } else {
                        row.style.background = '';
                    }
                });
            });
        })();
    </script>
@endsection
