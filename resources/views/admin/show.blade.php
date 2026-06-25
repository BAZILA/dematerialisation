@extends('layouts.app')

@section('title', 'Dossier — ' . $submission->student->full_name)

@section('content')

    {{-- Retour --}}
    <div class="mb-3">
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Retour aux dossiers
        </a>
    </div>

    <div class="row g-4">

        {{-- ══════════════════ COLONNE GAUCHE : infos + fichiers ══════════════════ --}}
        <div class="col-lg-8">

            {{-- En-tête étudiant --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="icon-box bg-primary text-white" style="width:60px;height:60px;font-size:1.5rem;">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-0 fw-bold">{{ $submission->student->full_name }}</h5>
                            <code class="text-muted ">{{ $submission->student->user->numero_identifiant }}</code>
                            &nbsp;·&nbsp;
                            <span
                                class="badge {{ $submission->student->user->type_etudiant === 'nouveau' ? 'bg-primary' : 'bg-secondary' }}">
                                {{ $submission->student->user->type_etudiant === 'nouveau' ? 'Nouveau Bachelier' : 'Ancien Étudiant' }}
                            </span>
                        </div>
                        <div>
                            @php
                                $colorMap = [
                                    'brouillon' => 'secondary',
                                    'soumis' => 'warning',
                                    'validé' => 'success',
                                    'rejeté' => 'danger',
                                ];
                                $labelMap = [
                                    'brouillon' => '✏️ Brouillon',
                                    'soumis' => '⏳ En Attente',
                                    'validé' => '✅ Validé',
                                    'rejeté' => '⚠️ Rejeté',
                                ];
                            @endphp
                            <span class="badge bg-{{ $colorMap[$submission->status] ?? 'secondary' }} fs-6 px-3 py-2">
                                {{ $labelMap[$submission->status] ?? $submission->status }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Infos personnelles --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-id-card"></i> Informations Personnelles</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3 small">
                        <div class="col-md-4"><span class="text-muted ">Email
                                :</span><br><strong>{{ $submission->student->user->email }}</strong></div>
                        <div class="col-md-4"><span class="text-muted ">Sexe
                                :</span><br><strong>{{ $submission->student->sexe === 'M' ? 'Masculin' : 'Féminin' }}</strong>
                        </div>
                        <div class="col-md-4"><span class="text-muted ">Date de naissance
                                :</span><br><strong>{{ $submission->student->date_naissance?->format('d/m/Y') ?? '—' }}</strong>
                        </div>
                        <div class="col-md-4"><span class="text-muted ">Lieu de naissance
                                :</span><br><strong>{{ $submission->student->lieu_naissance ?? '—' }}</strong></div>
                        <div class="col-md-4"><span class="text-muted ">Version dossier
                                :</span><br><strong>#{{ $submission->version }}</strong></div>
                        @if ($submission->submitted_at)
                            <div class="col-md-4"><span class="text-muted ">Soumis le
                                    :</span><br><strong>{{ $submission->submitted_at->format('d/m/Y à H:i') }}</strong>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ═══ Documents ═══ --}}
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-paperclip"></i> Documents Soumis</h6>
                    @if ($submission->isSubmitted())
                        <small class="text-muted ">Cochez ou commentez chaque fichier avant de
                            valider/rejeter</small>
                    @endif
                </div>

                <div class="list-group list-group-flush">
                    @foreach ($documentTypes as $docType)
                        @php $file = $submission->fileForType($docType); @endphp
                        <div
                            class="list-group-item px-4 py-3
                        {{ $file && $file->is_file_valid === false ? 'bg-danger bg-opacity-10' : ($file && $file->is_file_valid ? 'bg-success bg-opacity-10' : '') }}">

                            <div class="d-flex align-items-start gap-3">
                                {{-- Statut icône --}}
                                <div class="mt-1">
                                    @if (!$file)
                                        <i class="fas fa-minus-circle text-muted fs-5"></i>
                                    @elseif($file->is_file_valid === true)
                                        <i class="fas fa-check-circle text-success fs-5"></i>
                                    @elseif($file->is_file_valid === false)
                                        <i class="fas fa-times-circle text-danger fs-5"></i>
                                    @else
                                        <i class="fas fa-question-circle text-warning fs-5"></i>
                                    @endif
                                </div>

                                <div class="flex-grow-1">
                                    <div class="fw-semibold mb-1">
                                        {{ $docType->label }}
                                        @if ($docType->is_required)
                                            <span class="badge bg-danger bg-opacity-75 small">Obligatoire</span>
                                        @endif
                                        @if ($docType->has_stamp)
                                            <span class="badge bg-info small"><i class="fas fa-stamp"></i> Sera
                                                tamponné</span>
                                        @endif
                                    </div>

                                    @if (!$file)
                                        <span class="text-muted  small"><i class="fas fa-ban"></i> Aucun fichier
                                            fourni</span>
                                    @else
                                        {{-- Infos fichier --}}
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <i
                                                class="fas {{ $file->isPdf() ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary' }}"></i>
                                            <span class="small">{{ $file->original_filename }}</span>
                                            <span class="text-muted  small">({{ $file->file_size_formatted }})</span>
                                            @if ($file->is_stamped)
                                                <span class="badge bg-success small">
                                                    <i class="fas fa-stamp"></i> Tamponné
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Boutons aperçu & téléchargement --}}
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#previewModal{{ $file->id }}">
                                                <i class="fas fa-eye"></i> Aperçu
                                            </button>
                                            <a href="{{ route('admin.file.download', $file) }}"
                                                class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-download"></i> Télécharger
                                            </a>
                                        </div>

                                        {{-- Commentaire admin existant --}}
                                        @if ($file->admin_comment)
                                            <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small">
                                                <i class="fas fa-comment-alt"></i> {{ $file->admin_comment }}
                                            </div>
                                        @endif
                                    @endif
                                </div>

                                {{-- Checkmark admin (uniquement si dossier soumis) --}}
                                @if ($submission->isSubmitted() && $file)
                                    <div class="ms-auto d-flex flex-column align-items-end gap-1">
                                        <div class="form-check form-switch" title="Fichier correct">
                                            <input class="form-check-input file-valid-toggle" type="checkbox"
                                                data-file="{{ $file->id }}" id="valid_{{ $file->id }}"
                                                {{ $file->is_file_valid ? 'checked' : '' }}>
                                            <label class="form-check-label small" for="valid_{{ $file->id }}">
                                                Correct
                                            </label>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Champ commentaire admin (soumis uniquement) --}}
                            @if ($submission->isSubmitted() && $file)
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

            {{-- Historique académique --}}
            @if ($submission->academicHistories->count())
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-university"></i> Parcours Académique</h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Période</th>
                                    <th>Université</th>
                                    <th>Parcours</th>
                                    <th>Crédits</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($submission->academicHistories as $h)
                                    <tr>
                                        <td>{{ $h->annee_debut }} – {{ $h->annee_fin }}</td>
                                        <td>{{ $h->universite }}</td>
                                        <td>{{ $h->parcours }}</td>
                                        <td>{{ $h->credits ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>{{-- /col gauche --}}

        {{-- ══════════════════ COLONNE DROITE : actions ══════════════════ --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm sticky-top" style="top:80px;">

                @if ($submission->isSubmitted())
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i class="fas fa-tasks"></i> Décision</h6>
                    </div>
                    <div class="card-body">

                        {{-- Bouton Valider --}}
                        <form method="POST" action="{{ route('admin.submission.validate', $submission) }}"
                            id="validateForm">
                            @csrf
                            <button type="button" class="btn btn-success w-100 mb-3" id="btnValidate">
                                <i class="fas fa-check-circle"></i> Valider le Dossier
                            </button>
                        </form>

                        <hr>

                        {{-- Formulaire de rejet --}}
                        <form method="POST" action="{{ route('admin.submission.reject', $submission) }}"
                            id="rejectForm">
                            @csrf
                            {{-- Champs cachés pour les commentaires et statuts de chaque fichier --}}
                            <div id="hiddenFileData"></div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-danger">
                                    <i class="fas fa-times-circle"></i> Motif Global du Rejet
                                </label>
                                <textarea class="form-control @error('rejection_message') is-invalid @enderror" name="rejection_message"
                                    rows="4" placeholder="Résumez les raisons du rejet…" minlength="10" required></textarea>
                                @error('rejection_message')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="button" class="btn btn-danger w-100" id="btnReject">
                                <i class="fas fa-ban"></i> Rejeter le Dossier
                            </button>
                        </form>
                    </div>
                @elseif($submission->isValidated())
                    <div class="card-body text-center py-4">
                        <i class="fas fa-check-circle text-success fs-1 mb-2"></i>
                        <h6 class="text-success fw-bold">Dossier Validé</h6>
                        @if ($submission->validated_at)
                            <small class="text-muted ">Le
                                {{ $submission->validated_at->format('d/m/Y à H:i') }}</small>
                        @endif
                        @if ($submission->validator)
                            <br><small class="text-muted ">Par
                                {{ $submission->validator->numero_identifiant }}</small>
                        @endif
                    </div>
                @elseif($submission->isRejected())
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <i class="fas fa-times-circle text-danger fs-1 mb-1"></i>
                            <h6 class="text-danger fw-bold">Dossier Rejeté</h6>
                        </div>
                        <div class="alert alert-danger small">
                            <strong>Motif :</strong><br>{{ $submission->rejection_message }}
                        </div>
                    </div>
                @endif

            </div>
        </div>{{-- /col droite --}}

    </div>{{-- /row --}}

    {{-- ═══════════════════ MODALS APERÇU FICHIERS ═══════════════════ --}}
    @foreach ($documentTypes as $docType)
        @php $file = $submission->fileForType($docType); @endphp
        @if ($file)
            <div class="modal fade" id="previewModal{{ $file->id }}" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2">
                            <h6 class="modal-title">
                                <i class="fas fa-eye"></i>
                                Aperçu — {{ $docType->label }}
                                <small class="text-muted  ms-2">{{ $file->original_filename }}</small>
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-0" style="height:80vh;">
                            @if ($file->isPdf())
                                <iframe src="{{ route('admin.file.preview', $file) }}"
                                    style="width:100%;height:100%;border:none;" title="Aperçu PDF"></iframe>
                            @else
                                <div class="d-flex justify-content-center align-items-center h-100 bg-light">
                                    <img src="{{ route('admin.file.preview', $file) }}"
                                        alt="{{ $file->original_filename }}"
                                        style="max-height:100%;max-width:100%;object-fit:contain;">
                                </div>
                            @endif
                        </div>
                        <div class="modal-footer py-2">
                            <a href="{{ route('admin.file.download', $file) }}" class="btn btn-sm btn-outline-primary">
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
            // ─── Collecte des commentaires et statuts fichiers avant soumission ───
            function collectFileData(form) {
                const container = document.getElementById('hiddenFileData');
                if (!container) return;
                container.innerHTML = '';

                document.querySelectorAll('.file-comment-input').forEach(input => {
                    const fid = input.dataset.file;
                    if (input.value.trim()) {
                        const h = document.createElement('input');
                        h.type = 'hidden';
                        h.name = `file_comments[${fid}]`;
                        h.value = input.value.trim();
                        container.appendChild(h);
                    }
                });

                document.querySelectorAll('.file-valid-toggle').forEach(chk => {
                    if (chk.checked) {
                        const fid = chk.dataset.file;
                        const h = document.createElement('input');
                        h.type = 'hidden';
                        h.name = `file_valid[${fid}]`;
                        h.value = '1';
                        container.appendChild(h);
                    }
                });
            }

            // ─── Bouton Valider ───────────────────────────────────────────────────
            document.getElementById('btnValidate')?.addEventListener('click', function() {
                if (confirm(
                        'Confirmer la validation de ce dossier ? Les fichiers éligibles seront tamponnés automatiquement.'
                    )) {
                    document.getElementById('validateForm').submit();
                }
            });

            // ─── Bouton Rejeter ───────────────────────────────────────────────────
            document.getElementById('btnReject')?.addEventListener('click', function() {
                const msg = document.querySelector('[name="rejection_message"]');
                if (!msg || msg.value.trim().length < 10) {
                    msg?.classList.add('is-invalid');
                    return;
                }
                msg.classList.remove('is-invalid');

                if (confirm('Rejeter ce dossier avec les remarques indiquées ?')) {
                    collectFileData(document.getElementById('rejectForm'));
                    document.getElementById('rejectForm').submit();
                }
            });

            // ─── Feedback visuel lors du toggle "Correct" ─────────────────────────
            document.querySelectorAll('.file-valid-toggle').forEach(chk => {
                chk.addEventListener('change', function() {
                    const row = this.closest('.list-group-item');
                    if (this.checked) {
                        row.classList.remove('bg-danger', 'bg-opacity-10', 'border-danger');
                        row.classList.add('bg-success', 'bg-opacity-10');
                    } else {
                        row.classList.remove('bg-success', 'bg-opacity-10');
                    }
                });
            });
        })();
    </script>
@endsection
