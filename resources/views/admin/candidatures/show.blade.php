@extends('layouts.app')
@section('page-title', 'Candidature — ' . $candidature->full_name)
@section('page-subtitle', $candidature->reference)

@section('content')
    <div class="mb-3"><a href="{{ route('admin.candidatures.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a></div>

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Identité --}}
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
                    <div class="col-md-4 mt-2"><span class="text-muted">Soumise le :</span> {{ $candidature->created_at->format('d/m/Y à H:i') }}</div>
                    <div class="col-md-4 mt-2"><span class="text-muted">Année :</span> {{ $candidature->anneeAcademique->libelle ?? '—' }}</div>
                </div>
            </div></div>

            {{-- Choix de parcours (ordre de préférence) --}}
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-graduation-cap"></i> Choix de parcours du candidat</div>
                <div class="card-body">
                    @forelse ($candidature->choix as $c)
                        @php $retenu = $candidature->status === 'admis' && $candidature->parcours_admis_id === $c->parcours_id; @endphp
                        <div class="d-flex align-items-center gap-3 p-2 px-3 rounded mb-2"
                            style="border:1px solid {{ $retenu ? 'rgba(47,158,51,.5)' : 'var(--line)' }};{{ $retenu ? 'background:rgba(47,158,51,.06);' : '' }}">
                            <span class="badge {{ $retenu ? 'bg-success' : 'bg-primary' }}">{{ $c->ordre_label }}</span>
                            <div class="flex-grow-1">
                                <strong style="color:var(--t1);">{{ $c->parcours->nom ?? '—' }}</strong>
                                <small class="text-muted d-block">{{ $c->parcours->departement->faculte->nom ?? '' }} · {{ $c->parcours->grade_label ?? '' }}</small>
                            </div>
                            @if ($retenu)<span class="badge bg-success"><i class="fas fa-check"></i> Parcours d'admission</span>@endif
                        </div>
                    @empty
                        <div class="text-muted small">1 seul choix : <strong>{{ $candidature->parcours->nom ?? '—' }}</strong></div>
                    @endforelse
                </div>
            </div>

            {{-- Complétude du dossier : pièces communes + celles propres aux vœux --}}
            @php
                $parType = $candidature->files->keyBy('request_type_id');
                $manquants = $attendus->where('is_required', true)->reject(fn($t) => $parType->has($t->id));
                $aCorriger = $candidature->files->where('statut', 'rejete');
            @endphp
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i class="fas fa-clipboard-check"></i> Pièces attendues &amp; revue</span>
                    <span>
                        @if ($aCorriger->count())
                            <span class="badge bg-danger">{{ $aCorriger->count() }} à corriger</span>
                        @endif
                        @if ($manquants->isEmpty())
                            <span class="badge bg-success">Toutes les pièces obligatoires sont là</span>
                        @else
                            <span class="badge bg-danger">{{ $manquants->count() }} manquante(s)</span>
                        @endif
                    </span>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($attendus as $t)
                        @php $f = $parType->get($t->id); @endphp
                        <div class="list-group-item py-2">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <i class="fas {{ !$f ? ($t->is_required ? 'fa-circle-xmark text-danger' : 'fa-circle-minus text-muted') : ($f->estValidee() ? 'fa-circle-check text-success' : ($f->estRejetee() ? 'fa-triangle-exclamation text-danger' : 'fa-circle-dot text-warning')) }}"></i>
                                <div class="flex-grow-1">
                                    <span style="color:var(--t1);">{{ $t->label }}</span>
                                    @if ($t->is_required)<span class="text-danger">*</span>@endif
                                    @if ($t->parcours->isEmpty())
                                        <span class="badge bg-info ms-1" style="font-size:10px;">Commune</span>
                                    @else
                                        <span class="badge bg-warning text-dark ms-1" style="font-size:10px;"
                                            title="Exigée par ce(s) parcours">{{ $t->parcours->pluck('nom')->implode(' · ') }}</span>
                                    @endif
                                    @if ($f)
                                        <br><small class="text-muted">{{ $f->original_filename }} ({{ $f->file_size_formatted }})</small>
                                    @endif
                                </div>

                                @if ($f)
                                    <span class="badge bg-{{ $f->statut_badge }}">
                                        {{ \App\Models\CandidatureFile::$statuts[$f->statut] ?? $f->statut }}
                                    </span>
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#revue{{ $f->id }}"
                                        title="Statuer sur cette pièce">
                                        <i class="fas fa-pen-to-square"></i>
                                    </button>
                                @else
                                    <small class="text-muted">{{ $t->is_required ? 'Manquante' : 'Non fournie' }}</small>
                                @endif
                            </div>

                            @if ($f && $f->estRejetee() && $f->motif_rejet)
                                <div class="alert alert-danger py-1 px-2 mt-2 mb-0 small">
                                    <i class="fas fa-comment-dots"></i> {{ $f->motif_rejet }}
                                </div>
                            @endif

                            @if ($f)
                                <div class="collapse mt-2" id="revue{{ $f->id }}">
                                    <form method="POST" action="{{ route('admin.candidatures.file.review', $f) }}"
                                        class="row g-2 align-items-end">
                                        @csrf
                                        <div class="col-sm-4">
                                            <label class="form-label small mb-1">Décision</label>
                                            <select name="statut" class="form-select form-select-sm"
                                                onchange="this.closest('form').querySelector('.motif-wrap').style.display = this.value === 'rejete' ? '' : 'none'">
                                                @foreach (\App\Models\CandidatureFile::$statuts as $k => $l)
                                                    <option value="{{ $k }}" @selected($f->statut === $k)>{{ $l }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-sm-6 motif-wrap" style="display: {{ $f->estRejetee() ? '' : 'none' }};">
                                            <label class="form-label small mb-1">Motif (vu par le candidat)</label>
                                            <input name="motif_rejet" class="form-control form-control-sm"
                                                value="{{ $f->motif_rejet }}" maxlength="500"
                                                placeholder="Ex : document illisible, page manquante…">
                                        </div>
                                        <div class="col-sm-2">
                                            <button class="btn btn-sm btn-primary w-100">Enregistrer</button>
                                        </div>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-muted">Aucune pièce configurée pour les candidatures.</div>
                    @endforelse
                </div>
                <div class="card-footer small text-muted">
                    Une pièce <strong>validée</strong> est verrouillée côté candidat ; seules les pièces
                    <strong>à corriger</strong> (et les manquantes) pourront être redéposées.
                </div>
            </div>

            {{-- Pièces (aperçu INLINE en modal, comme les dépôts) --}}
            <div class="card"><div class="card-header"><i class="fas fa-paperclip"></i> Pièces jointes</div>
                <div class="list-group list-group-flush">
                    @forelse ($candidature->files as $f)
                        <div class="list-group-item d-flex align-items-center gap-2">
                            <i class="fas {{ $f->isPdf() ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary' }}"></i>
                            <div class="flex-grow-1"><strong>{{ $f->requestType->label ?? 'Document' }}</strong><br><small class="text-muted">{{ $f->original_filename }} ({{ $f->file_size_formatted }})</small></div>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#capercu{{ $f->id }}">
                                <i class="fas fa-eye"></i> Aperçu
                            </button>
                            <a href="{{ route('admin.candidatures.file.download', $f) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download"></i></a>
                        </div>
                    @empty
                        <div class="list-group-item text-muted">Aucune pièce jointe.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ═══ Décision ═══ --}}
        <div class="col-lg-4">
            <div class="card sticky-top" style="top:80px;"><div class="card-header bg-primary text-white"><i class="fas fa-gavel"></i> Décision</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.candidatures.status', $candidature) }}">
                        @csrf
                        <label class="form-label small">Statut</label>
                        <select class="form-select mb-3" name="status" id="candStatus" required>
                            @foreach (['en_instruction' => 'En instruction', 'en_correction' => 'À compléter (le candidat corrige en ligne)', 'admis' => 'Admettre', 'refuse' => 'Refuser'] as $k => $l)
                                <option value="{{ $k }}" @selected($candidature->status == $k)>{{ $l }}</option>
                            @endforeach
                        </select>

                        {{-- Parcours d'admission : requis quand on admet --}}
                        <div id="admisBox" style="display:none;">
                            <label class="form-label small">Parcours d'admission <span class="text-danger">*</span></label>
                            <select class="form-select mb-3" name="parcours_admis_id">
                                <option value="">— Choisir parmi ses vœux —</option>
                                @foreach ($candidature->choix as $c)
                                    <option value="{{ $c->parcours_id }}" @selected($candidature->parcours_admis_id == $c->parcours_id)>
                                        {{ $c->ordre_label }} — {{ $c->parcours->nom ?? '' }}
                                    </option>
                                @endforeach
                                @if ($candidature->choix->isEmpty() && $candidature->parcours)
                                    <option value="{{ $candidature->parcours_id }}" @selected($candidature->parcours_admis_id == $candidature->parcours_id)>
                                        {{ $candidature->parcours->nom }}
                                    </option>
                                @endif
                            </select>
                        </div>

                        <label class="form-label small">Commentaire <span class="text-muted">(visible par le candidat au suivi)</span></label>
                        <textarea class="form-control mb-1" name="admin_comment" rows="3"
                            placeholder="Ex. : la copie du diplôme est illisible, rechargez un scan net…">{{ $candidature->admin_comment }}</textarea>
                        <small class="text-muted d-block mb-3" id="corrHint" style="display:none;">
                            <i class="fas fa-circle-info"></i> En « À compléter », précisez CE QUI doit être corrigé :
                            le candidat pourra recharger ses pièces depuis la page de suivi, puis le dossier
                            reviendra automatiquement « Soumise ».
                        </small>
                        <button class="btn btn-primary w-100" data-loading-text="Enregistrement…"><i class="fas fa-save"></i> Enregistrer la décision</button>
                    </form>
                    @if ($candidature->traiteur)<div class="small text-muted mt-3">Traité par : {{ $candidature->traiteur->staff_name }}</div>@endif
                    @if ($candidature->decision_at)<div class="small text-muted">Décision le {{ $candidature->decision_at->format('d/m/Y à H:i') }}</div>@endif
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Modals d'aperçu inline ═══ --}}
    @foreach ($candidature->files as $f)
        <div class="modal fade" id="capercu{{ $f->id }}" tabindex="-1">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title" style="color:var(--t1);">
                            <i class="fas {{ $f->isPdf() ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary' }}"></i>
                            {{ $f->requestType->label ?? 'Document' }} — {{ $f->original_filename }}
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0" style="height:80vh;">
                        @if ($f->isPdf())
                            <iframe src="{{ route('admin.candidatures.file.preview', $f) }}" style="width:100%;height:100%;border:none;"></iframe>
                        @else
                            <div class="d-flex align-items-center justify-content-center h-100" style="background:var(--surface-2);">
                                <img src="{{ route('admin.candidatures.file.preview', $f) }}" style="max-width:100%;max-height:100%;object-fit:contain;">
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer py-2">
                        <a href="{{ route('admin.candidatures.file.download', $f) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-download"></i> Télécharger</a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection

@section('js')
    <script>
        (function () {
            const sel = document.getElementById('candStatus');
            const box = document.getElementById('admisBox');
            const hint = document.getElementById('corrHint');
            const maj = () => {
                box.style.display = sel.value === 'admis' ? '' : 'none';
                hint.style.display = sel.value === 'en_correction' ? 'block' : 'none';
                const p = box.querySelector('select');
                if (p) p.required = sel.value === 'admis';
            };
            sel.addEventListener('change', maj);
            maj();
        })();
    </script>
@endsection
