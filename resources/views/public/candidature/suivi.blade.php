@extends('layouts.app')
@section('title', 'Suivi de candidature')

@section('css')
    <style>
        body.guest-mode .content { align-items: flex-start !important; padding-top: 28px; padding-bottom: 44px; }
        .suivi-wrap { width: 100%; max-width: 700px; position: relative; z-index: 1; }

        /* ── Timeline horizontale ── */
        .tl { display:flex; align-items:flex-start; margin:6px 0 4px; }
        .tl-step { flex:1; text-align:center; position:relative; }
        .tl-step::before { content:''; position:absolute; top:15px; left:-50%; width:100%; height:3px;
            background:var(--line-mid); z-index:0; }
        .tl-step:first-child::before { display:none; }
        .tl-dot { width:32px; height:32px; border-radius:50%; margin:0 auto; position:relative; z-index:1;
            background:var(--surface); border:3px solid var(--line-mid); display:flex; align-items:center;
            justify-content:center; font-size:12px; color:var(--t3); }
        .tl-lbl { font-size:11.5px; font-weight:600; color:var(--t3); margin-top:7px; line-height:1.25; }
        .tl-step.done .tl-dot { background:#2F9E33; border-color:#2F9E33; color:#fff; }
        .tl-step.done::before { background:#2F9E33; }
        .tl-step.done .tl-lbl { color:#1f7a24; }
        .tl-step.current .tl-dot { background:var(--brand); border-color:var(--brand); color:#fff;
            box-shadow:0 0 0 5px rgba(26,86,168,.15); }
        .tl-step.current::before { background:var(--brand); }
        .tl-step.current .tl-lbl { color:var(--brand); }
        .tl-step.danger .tl-dot { background:#DC2626; border-color:#DC2626; color:#fff; }
        .tl-step.danger::before { background:#DC2626; }
        .tl-step.danger .tl-lbl { color:#DC2626; }
        .tl-step.warn .tl-dot { background:#f59e0b; border-color:#f59e0b; color:#fff; box-shadow:0 0 0 5px rgba(245,158,11,.15); }
        .tl-step.warn::before { background:#f59e0b; }
        .tl-step.warn .tl-lbl { color:#b45309; }

        .choix-row { display:flex; align-items:center; gap:10px; padding:10px 13px; border:1px solid var(--line);
            border-radius:12px; margin-bottom:8px; background:var(--surface); }
        .choix-num { width:30px; height:30px; border-radius:9px; background:var(--em-dim); color:var(--brand);
            font-weight:800; font-size:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .choix-row.retenu { border-color:#2F9E33; background:rgba(47,158,51,.06); }
        .choix-row.retenu .choix-num { background:#2F9E33; color:#fff; }
    </style>
@endsection

@section('content')
    <div class="suivi-wrap">
        <div class="text-center mb-4">
            <img src="{{ asset('assets/uk/logo-sigle-bg.png') }}" style="height:52px;" alt="Université de Kara">
            <h1 style="font-family:var(--font-head);font-size:22px;font-weight:800;margin-top:14px;color:var(--t1);">Suivi de candidature</h1>
            <p style="color:var(--t2);font-size:13.5px;">Entrez votre numéro de suivi (ex. CAND-2026-XXXXXX) pour connaître l'état de votre dossier.</p>
        </div>

        {{-- Recherche --}}
        <div class="card mb-4"><div class="card-body">
            <form method="POST" action="{{ route('candidature.suivi.post') }}" class="d-flex gap-2">
                @csrf
                <input class="form-control" name="reference" placeholder="CAND-2026-XXXXXX"
                    value="{{ old('reference', $candidature->reference ?? request('reference')) }}" required>
                <button class="btn btn-primary flex-shrink-0" data-loading-text="Recherche…"><i class="fas fa-magnifying-glass"></i> Rechercher</button>
            </form>
            @php
                // Les erreurs liées à une pièce précise sont affichées en face du champ concerné
                $errGeneraux = collect($errors->keys())->reject(fn($k) => str_starts_with($k, 'files.'));
            @endphp
            @if ($errGeneraux->isNotEmpty())
                <div class="alert alert-danger py-2 small mt-3 mb-0"><i class="fas fa-circle-exclamation"></i>
                    {{ $errors->first($errGeneraux->first()) }}</div>
            @endif
        </div></div>

        @if (!empty($complete))
            <div class="alert alert-success"><i class="fas fa-circle-check"></i>
                <strong>Dossier complété !</strong> Vos nouvelles pièces ont été transmises : votre candidature
                est repassée <strong>en instruction</strong>.</div>
        @endif

        @if ($cherche && !$candidature)
            <div class="card"><div class="card-body text-center py-5">
                <i class="fas fa-file-circle-question" style="font-size:2.4rem;color:var(--t3);"></i>
                <h6 class="mt-3" style="color:var(--t1);">Aucune candidature trouvée</h6>
                <p class="text-muted small mb-0">Vérifiez le numéro de suivi remis lors de votre soumission.</p>
            </div></div>
        @elseif ($candidature)
            @php
                $st = $candidature->status;
                // Timeline : Soumise → Instruction → Décision
                $steps = [
                    ['lbl' => 'Soumise', 'icon' => 'fa-paper-plane'],
                    ['lbl' => $st === 'en_correction' ? 'À compléter' : 'En instruction', 'icon' => $st === 'en_correction' ? 'fa-triangle-exclamation' : 'fa-magnifying-glass'],
                    ['lbl' => $st === 'refuse' ? 'Refusée' : ($st === 'admis' ? 'Admise' : 'Décision'), 'icon' => $st === 'refuse' ? 'fa-xmark' : ($st === 'admis' ? 'fa-check' : 'fa-gavel')],
                ];
                $cls = ['', '', ''];
                if ($st === 'soumise')            { $cls = ['done', 'current', '']; $steps[1]['lbl'] = 'En instruction'; }
                elseif ($st === 'en_instruction') { $cls = ['done', 'current', '']; }
                elseif ($st === 'en_correction')  { $cls = ['done', 'warn', '']; }
                elseif ($st === 'admis')          { $cls = ['done', 'done', 'done']; }
                elseif ($st === 'refuse')         { $cls = ['done', 'done', 'danger']; }
            @endphp

            <div class="card mb-4"><div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <div class="fw-bold" style="color:var(--t1);font-size:16px;">{{ $candidature->full_name }}</div>
                        <code class="small">{{ $candidature->reference }}</code>
                    </div>
                    <span class="badge bg-{{ $candidature->status_badge }} px-3 py-2">{{ $candidature->statut_label }}</span>
                </div>

                {{-- Timeline --}}
                <div class="tl">
                    @foreach ($steps as $i => $s)
                        <div class="tl-step {{ $cls[$i] }}">
                            <div class="tl-dot"><i class="fas {{ $s['icon'] }}"></i></div>
                            <div class="tl-lbl">{{ $s['lbl'] }}</div>
                        </div>
                    @endforeach
                </div>

                @if ($candidature->admin_comment)
                    <div class="alert {{ $st === 'refuse' ? 'alert-danger' : ($st === 'en_correction' ? 'alert-warning' : 'alert-info') }} small mt-3 mb-0">
                        <i class="fas fa-comment-dots"></i>
                        <strong>Message de l'administration :</strong> {{ $candidature->admin_comment }}
                    </div>
                @endif
            </div></div>

            {{-- Choix de parcours --}}
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-graduation-cap"></i> Vos choix de parcours</div>
                <div class="card-body">
                    @forelse ($candidature->choix as $c)
                        @php $retenu = $st === 'admis' && $candidature->parcours_admis_id === $c->parcours_id; @endphp
                        <div class="choix-row {{ $retenu ? 'retenu' : '' }}">
                            <div class="choix-num">{{ $c->ordre }}</div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold" style="color:var(--t1);font-size:13.5px;">{{ $c->parcours->nom ?? '—' }}</div>
                                <small class="text-muted">{{ $c->parcours->departement->faculte->nom ?? '' }}</small>
                            </div>
                            @if ($retenu)
                                <span class="badge bg-success"><i class="fas fa-check"></i> Admis(e) sur ce parcours</span>
                            @endif
                        </div>
                    @empty
                        <div class="choix-row">
                            <div class="choix-num">1</div>
                            <div class="fw-semibold" style="color:var(--t1);">{{ $candidature->parcours->nom ?? '—' }}</div>
                        </div>
                    @endforelse

                    @if ($st === 'admis' && $candidature->parcoursAdmis)
                        <div class="alert alert-success small mb-0 mt-2">
                            <i class="fas fa-champagne-glasses"></i>
                            <strong>Félicitations !</strong> Vous êtes admis(e) en
                            <strong>{{ $candidature->parcoursAdmis->nom }}</strong>.
                            Présentez-vous à la scolarité avec votre numéro de suivi pour finaliser votre inscription.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Pièces du dossier --}}
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-paperclip"></i> Pièces de votre dossier</div>
                <div class="list-group list-group-flush">
                    @forelse ($candidature->files as $f)
                        <div class="list-group-item">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas {{ $f->isPdf() ? 'fa-file-pdf text-danger' : 'fa-file-image text-primary' }}"></i>
                                <div class="flex-grow-1">
                                    <span class="fw-semibold small" style="color:var(--t1);">{{ $f->requestType->label ?? 'Document' }}</span><br>
                                    <small class="text-muted">{{ $f->original_filename }}</small>
                                </div>
                                @if ($f->statut === 'valide')
                                    <span class="badge bg-success"><i class="fas fa-lock"></i> Validée</span>
                                @elseif ($f->statut === 'rejete')
                                    <span class="badge bg-danger"><i class="fas fa-rotate-left"></i> À refaire</span>
                                @else
                                    <span class="badge bg-secondary">En vérification</span>
                                @endif
                            </div>
                            @if ($f->statut === 'rejete' && $f->motif_rejet)
                                <div class="alert alert-danger py-1 px-2 mt-2 mb-0 small">
                                    <i class="fas fa-comment-dots"></i> {{ $f->motif_rejet }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="list-group-item text-muted small">Aucune pièce.</div>
                    @endforelse
                </div>
            </div>

            {{-- ═══ Complétion du dossier (statut « À compléter ») ═══ --}}
            @if ($st === 'en_correction')
                <div class="card" style="border-color:rgba(245,158,11,.5);">
                    <div class="card-header" style="color:#b45309;">
                        <i class="fas fa-arrows-rotate"></i> Compléter mon dossier
                    </div>
                    <div class="card-body">
                        @php
                            $aRefaire = $candidature->files->where('statut', 'rejete')->count();
                            $verrouillees = $candidature->files->where('statut', 'valide')->count();
                        @endphp
                        <p class="small" style="color:var(--t2);">
                            L'administration vous demande de corriger votre dossier.
                            @if ($aRefaire)
                                <strong>{{ $aRefaire }} pièce(s)</strong> sont à refaire — le motif est indiqué
                                juste au-dessus du champ concerné.
                            @endif
                            @if ($verrouillees)
                                Les <strong>{{ $verrouillees }} pièce(s) déjà validées</strong> sont verrouillées :
                                inutile de les renvoyer.
                            @endif
                            Vos nouvelles pièces <strong>remplaceront</strong> les anciennes, puis votre
                            candidature repartira automatiquement en instruction.
                        </p>
                        <form method="POST" action="{{ route('candidature.completer') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="reference" value="{{ $candidature->reference }}">
                            <div class="mb-3" style="max-width:280px;">
                                <label class="form-label small">Votre date de naissance <span class="text-danger">*</span>
                                    <span class="text-muted">(vérification)</span></label>
                                <input type="date" name="date_naissance" class="form-control form-control-sm" required>
                            </div>
                            @foreach ($types as $t)
                                @php
                                    $actuel = $candidature->files->firstWhere('request_type_id', $t->id);
                                    $verrouille = $actuel && $actuel->statut === 'valide';
                                    $rejete = $actuel && $actuel->statut === 'rejete';
                                @endphp
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold d-flex align-items-center gap-2 flex-wrap">
                                        <span>{{ $t->label }}</span>
                                        @if ($verrouille)
                                            <span class="badge bg-success"><i class="fas fa-lock"></i> Validée</span>
                                        @elseif ($rejete)
                                            <span class="badge bg-danger"><i class="fas fa-rotate-left"></i> À refaire</span>
                                        @elseif (!$actuel)
                                            <span class="badge bg-warning text-dark">Manquante</span>
                                        @endif
                                    </label>

                                    @if ($rejete && $actuel->motif_rejet)
                                        <div class="alert alert-danger py-1 px-2 mb-2 small">
                                            <i class="fas fa-comment-dots"></i> {{ $actuel->motif_rejet }}
                                        </div>
                                    @endif

                                    @if ($verrouille)
                                        {{-- Champ verrouillé : cette pièce est acceptée, il n'y touche plus --}}
                                        <input type="text" class="form-control form-control-sm" disabled
                                            value="{{ $actuel->original_filename }}">
                                        <small class="text-muted">
                                            <i class="fas fa-lock"></i> Pièce déjà validée — elle ne peut plus être modifiée.
                                        </small>
                                    @else
                                        <input type="file"
                                            class="form-control form-control-sm @error('files.' . $t->id) is-invalid @enderror"
                                            name="files[{{ $t->id }}]" accept=".pdf,.jpg,.jpeg,.png">
                                        @error('files.' . $t->id)
                                            <div class="invalid-feedback d-block">
                                                <i class="fas fa-circle-exclamation"></i> {{ $message }}
                                            </div>
                                        @enderror
                                        @if ($actuel)
                                            <small class="text-muted">Remplacera « {{ $actuel->original_filename }} ».</small>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                            <button class="btn btn-warning w-100" data-loading-text="Envoi…">
                                <i class="fas fa-paper-plane"></i> Renvoyer mon dossier complété
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        @endif

        <p class="text-center mt-4" style="font-size:13px;">
            <a href="{{ route('candidature.create') }}" style="color:var(--brand);text-decoration:none;"><i class="fas fa-file-signature"></i> Déposer une candidature</a>
            &nbsp;·&nbsp;
            <a href="{{ route('login') }}" style="color:var(--t2);text-decoration:none;">Retour à la connexion</a>
        </p>
    </div>
@endsection
