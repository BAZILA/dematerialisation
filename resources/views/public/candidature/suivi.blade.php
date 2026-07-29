@extends('layouts.app')
@section('title', 'Suivi de candidature')

@section('css')
    <style>
        body.guest-mode .content { align-items: flex-start !important; padding-top: 28px; padding-bottom: 44px; }
        .suivi-wrap { width: 100%; max-width: 1280px; position: relative; z-index: 1; }

        /* Deux colonnes sur PC : le dossier à gauche, la synthèse à droite */
        .suivi-grid { display: grid; grid-template-columns: 1fr; gap: 20px; align-items: start; }
        @media (min-width: 992px)  { .suivi-grid { grid-template-columns: minmax(0, 1fr) 340px; } }
        @media (min-width: 1400px) { .suivi-grid { grid-template-columns: minmax(0, 1fr) 400px; } }
        .suivi-recap { position: sticky; top: 16px; }
        /* Sur mobile la synthèse passe EN PREMIER : reléguée en bas, elle
           n'était jamais vue. */
        @media (max-width: 991.98px) {
            .suivi-recap { position: static; order: -1; }
        }
        @media (min-width: 992px) { .champs-serres { max-width: 720px; } }
        .recap-l { display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px dashed var(--line); font-size: 12.5px; }
        .recap-l:last-child { border-bottom: none; }
        .recap-v { font-weight: 700; color: var(--t1); }
        .aide-e { display: flex; gap: 10px; padding: 9px 0; border-bottom: 1px dashed var(--line); }
        .aide-e:last-child { border-bottom: none; }
        .aide-n { width: 22px; height: 22px; border-radius: 7px; background: var(--em-dim);
            color: var(--brand); font-weight: 800; font-size: 11px; display: flex;
            align-items: center; justify-content: center; flex-shrink: 0; }
        .aide-t { font-size: 12px; font-weight: 600; color: var(--t1); line-height: 1.3; }
        .aide-d { font-size: 11px; color: var(--t3); line-height: 1.35; }

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

            <div class="suivi-grid">
            <div>

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

            {{-- ═══════════════════════════════════════════════════════
                 UNE SECTION PAR PARCOURS
                 Le dossier commun est constitué une fois ; chaque parcours
                 visé a sa propre section, ses pièces spécifiques et sa
                 propre quittance.
            ═══════════════════════════════════════════════════════ --}}

            @php
                $paiementActif = \App\Models\Paiement::moduleActif();
                $communes = $candidature->files->whereNull('candidature_choix_id');
            @endphp

            {{-- Dossier commun --}}
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i class="fas fa-folder-open"></i> Dossier commun</span>
                    <span class="badge bg-info">{{ $communes->count() }} pièce(s) partagée(s)</span>
                </div>
                <div class="card-body">
                    <p class="small mb-2" style="color:var(--t2);">
                        Ces pièces valent pour <strong>tous les parcours</strong> auxquels vous postulez.
                        Vous ne les fournissez qu'une seule fois.
                    </p>
                    @forelse ($communes as $f)
                        <div class="d-flex align-items-center gap-2 py-1">
                            <i class="fas fa-circle-check text-success"></i>
                            <span class="small fw-semibold" style="color:var(--t1);">{{ $f->requestType->label ?? 'Document' }}</span>
                            <small class="text-muted">— {{ $f->original_filename }}</small>
                            @if ($f->statut === 'rejete')
                                <span class="badge bg-danger ms-auto"><i class="fas fa-rotate-left"></i> À refaire</span>
                            @elseif ($f->statut === 'valide')
                                <span class="badge bg-success ms-auto"><i class="fas fa-lock"></i> Validée</span>
                            @endif
                        </div>
                    @empty
                        <div class="small text-muted">Aucune pièce commune enregistrée.</div>
                    @endforelse
                </div>
            </div>

            {{-- Sections : un bloc par parcours --}}
            @foreach ($candidature->choix->sortBy('ordre') as $section)
                @php
                    $retenu   = $section->estAdmis();
                    $pSection = $paiementActif ? $section->paiement : null;
                    $specifiques = $section->files;
                @endphp
                <div class="card mb-4" style="border-left:4px solid {{ $retenu ? '#2F9E33' : 'var(--brand)' }};">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span>
                            <span class="choix-num d-inline-flex me-2">{{ $section->ordre }}</span>
                            <strong style="color:var(--t1);">{{ $section->parcours->nom ?? '—' }}</strong>
                            <small class="text-muted d-block ms-5">
                                {{ $section->parcours->departement->faculte->nom ?? '' }}
                                @if ($section->soumis_at) · postulé le {{ $section->soumis_at->format('d/m/Y') }} @endif
                            </small>
                        </span>
                        <span class="badge bg-{{ $section->statut_badge }}">
                            @if ($retenu)<i class="fas fa-check"></i> @endif{{ $section->statut_label }}
                        </span>
                    </div>
                    <div class="card-body">

                        @if ($section->motif)
                            <div class="alert alert-{{ $section->statut === 'refuse' ? 'danger' : 'warning' }} small">
                                <i class="fas fa-comment-dots"></i> {{ $section->motif }}
                            </div>
                        @endif

                        {{-- Pièces propres à ce parcours --}}
                        <div class="fw-semibold small mb-2" style="color:var(--t1);">
                            <i class="fas fa-code-branch"></i> Pièces propres à ce parcours
                        </div>
                        @forelse ($specifiques as $f)
                            <div class="d-flex align-items-center gap-2 py-1">
                                <i class="fas {{ $f->statut === 'rejete' ? 'fa-triangle-exclamation text-danger' : 'fa-circle-check text-success' }}"></i>
                                <span class="small fw-semibold" style="color:var(--t1);">{{ $f->requestType->label ?? 'Document' }}</span>
                                <small class="text-muted">— {{ $f->original_filename }}</small>
                            </div>
                            @if ($f->statut === 'rejete' && $f->motif_rejet)
                                <div class="alert alert-danger py-1 px-2 my-1 small">
                                    <i class="fas fa-comment-dots"></i> {{ $f->motif_rejet }}
                                </div>
                            @endif
                        @empty
                            <div class="small text-muted mb-2">
                                Aucune pièce spécifique — le dossier commun suffit pour ce parcours.
                            </div>
                        @endforelse

                        {{-- Quittance propre à cette section --}}
                        @if ($pSection)
                            <hr>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                <span class="fw-semibold small" style="color:var(--t1);">
                                    <i class="fas fa-money-bill-transfer"></i> Frais pour ce parcours
                                </span>
                                <span class="badge bg-{{ $pSection->statut_badge }}">{{ $pSection->statut_label }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span class="fw-bold" style="font-size:1.2rem;color:var(--brand);">{{ $pSection->montant_formate }}</span>
                                <code class="small">{{ $pSection->reference }}</code>
                            </div>

                            @if ($pSection->estPaye())
                                <div class="alert alert-success small mt-2 mb-0">
                                    <i class="fas fa-circle-check"></i> Paiement confirmé pour ce parcours.
                                </div>
                            @else
                                @if ($pSection->statut === 'soumis')
                                    <div class="alert alert-warning small mt-2">
                                        <i class="fas fa-hourglass-half"></i>
                                        Transaction <code>{{ $pSection->transaction_id }}</code> en cours de vérification.
                                    </div>
                                @endif
                                @if ($pSection->statut === 'rejete' && $pSection->motif_rejet)
                                    <div class="alert alert-danger small mt-2">
                                        <i class="fas fa-circle-exclamation"></i>
                                        <strong>Versement non confirmé :</strong> {{ $pSection->motif_rejet }}
                                    </div>
                                @endif

                                @if ($pSection->peutDeclarer())
                                    @php $consignes = app(\App\Services\Paiement\PasserelleOperateur::class)->instructions($pSection); @endphp
                                    <ol class="small ps-3 mt-2" style="color:var(--t2);">
                                        @foreach ($consignes['etapes'] as $etape)
                                            <li class="mb-1">{{ $etape }}</li>
                                        @endforeach
                                    </ol>
                                    <form method="POST" action="{{ route('candidature.paiement') }}" class="row g-2">
                                        @csrf
                                        <input type="hidden" name="reference" value="{{ $candidature->reference }}">
                                        <input type="hidden" name="section_id" value="{{ $section->id }}">
                                        <div class="col-md-6">
                                            <label class="form-label small">Votre date de naissance <span class="text-danger">*</span></label>
                                            <input type="date" name="date_naissance" class="form-control form-control-sm" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">Opérateur</label>
                                            <select name="operateur" class="form-select form-select-sm">
                                                @foreach (\App\Models\Paiement::$operateurs as $k => $l)
                                                    <option value="{{ $k }}" @selected($pSection->operateur === $k)>{{ $l }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">Identifiant de transaction <span class="text-danger">*</span></label>
                                            <input name="transaction_id" class="form-control form-control-sm"
                                                placeholder="Reçu par SMS" required maxlength="80">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small">Numéro utilisé</label>
                                            <input name="numero_payeur" class="form-control form-control-sm"
                                                placeholder="90 00 00 00" maxlength="30">
                                        </div>
                                        <div class="col-12">
                                            <button class="btn btn-primary btn-sm w-100 mt-1" data-loading-text="Envoi…">
                                                <i class="fas fa-paper-plane"></i> Déclarer le paiement de ce parcours
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach

            @php $admisTous = $candidature->parcoursAdmisTous(); @endphp
            @if ($admisTous->isNotEmpty())
                <div class="alert alert-success">
                    <i class="fas fa-champagne-glasses"></i>
                    <strong>Félicitations !</strong> Vous êtes admis(e) sur
                    <strong>{{ $admisTous->count() }} parcours</strong> :
                    @foreach ($admisTous as $a)
                        <div>· {{ $a->parcours->nom ?? '—' }}</div>
                    @endforeach
                    <div class="mt-2">
                        Vous choisirez celui où vous vous inscrivez lors de votre inscription définitive.
                        Présentez-vous à la scolarité avec votre numéro de suivi.
                    </div>
                </div>
            @endif

            {{-- ═══ Postuler à un parcours supplémentaire ═══ --}}
            @php
                $aDesParcours = collect($parcoursDispo ?? [])
                    ->flatMap(fn ($f) => $f->departements->flatMap->parcours)->isNotEmpty();
            @endphp
            @if ($aDesParcours && !in_array($st, ['admis', 'refuse'], true))
                <div class="card mb-4" style="border-style:dashed;">
                    <div class="card-header">
                        <i class="fas fa-plus-circle"></i> Postuler à un autre parcours
                    </div>
                    <div class="card-body">
                        <p class="small" style="color:var(--t2);">
                            Votre <strong>dossier commun est déjà constitué</strong> : vous n'aurez à fournir
                            que les pièces propres au nouveau parcours, plus sa quittance.
                        </p>

                        <form method="POST" action="{{ route('candidature.parcours.ajouter') }}"
                            enctype="multipart/form-data" id="formNouveauParcours">
                            @csrf
                            <input type="hidden" name="reference" value="{{ $candidature->reference }}">
                            <div class="row g-2 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small">Votre date de naissance <span class="text-danger">*</span></label>
                                    <input type="date" name="date_naissance" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">Nouveau parcours <span class="text-danger">*</span></label>
                                    <select name="parcours_id" class="form-select form-select-sm" id="selNouveauParcours" required>
                                        <option value="">— Choisissez —</option>
                                        @foreach ($parcoursDispo as $fac)
                                            @foreach ($fac->departements as $dep)
                                                @if ($dep->parcours->count())
                                                    <optgroup label="{{ $fac->sigle ?? $fac->nom }} · {{ $dep->nom }}">
                                                        @foreach ($dep->parcours as $p)
                                                            <option value="{{ $p->id }}">{{ $p->nom }} ({{ $p->grade_label }})</option>
                                                        @endforeach
                                                    </optgroup>
                                                @endif
                                            @endforeach
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- Pièces communes : reprises automatiquement, affichées cochées --}}
                            @php
                                $tousTypes = \App\Models\RequestType::with('parcours')
                                    ->where('category', 'candidature')->where('is_active', true)
                                    ->orderBy('order')->orderBy('id')->get();
                                $fournies = $candidature->files->keyBy('request_type_id');
                                $typesCommuns = $tousTypes->filter(fn ($t) => $t->parcours->isEmpty());
                                $typesSpec = $tousTypes->filter(fn ($t) => $t->parcours->isNotEmpty());
                            @endphp

                            @if ($typesCommuns->count())
                                <div class="p-2 rounded mb-3" style="background:rgba(47,158,51,.07);border:1px solid rgba(47,158,51,.35);">
                                    <div class="small fw-semibold mb-1" style="color:#1f7a24;">
                                        <i class="fas fa-circle-check"></i> Repris de votre dossier — rien à refaire
                                    </div>
                                    @foreach ($typesCommuns as $t)
                                        <div class="small d-flex align-items-center gap-2">
                                            <i class="fas fa-check text-success"></i>
                                            <span>{{ $t->label }}</span>
                                            @if ($fournies->has($t->id))
                                                <small class="text-muted">— {{ $fournies[$t->id]->original_filename }}</small>
                                            @else
                                                <span class="badge bg-warning text-dark">non fournie</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            {{-- Pièces spécifiques : révélées selon le parcours choisi --}}
                            <div id="specNouveau">
                                @foreach ($typesSpec as $t)
                                    <div class="mb-2 spec-nouveau" style="display:none;"
                                        data-parcours="{{ $t->parcours->pluck('id')->implode(',') }}"
                                        data-required="{{ $t->is_required ? 1 : 0 }}">
                                        <label class="form-label small fw-semibold">
                                            {{ $t->label }}
                                            @if ($t->is_required)<span class="text-danger">*</span>@endif
                                            <span class="badge bg-warning text-dark ms-1">propre à ce parcours</span>
                                        </label>
                                        <input type="file" class="form-control form-control-sm"
                                            name="files[{{ $t->id }}]" accept=".pdf,.jpg,.jpeg,.png">
                                        @if ($t->description)<small class="text-muted">{{ $t->description }}</small>@endif
                                    </div>
                                @endforeach
                            </div>

                            <button class="btn btn-primary w-100 mt-2" data-loading-text="Envoi…">
                                <i class="fas fa-paper-plane"></i> Ajouter ce parcours à ma candidature
                            </button>
                        </form>
                    </div>
                </div>

                <script>
                    // Ne montrer que les pièces réclamées par le parcours choisi
                    (function () {
                        const sel = document.getElementById('selNouveauParcours');
                        const blocs = document.querySelectorAll('.spec-nouveau');
                        if (!sel) return;
                        sel.addEventListener('change', function () {
                            blocs.forEach(b => {
                                const cibles = (b.dataset.parcours || '').split(',').filter(Boolean);
                                const actif = cibles.includes(sel.value);
                                b.style.display = actif ? '' : 'none';
                                const input = b.querySelector('input[type=file]');
                                if (input) {
                                    input.required = actif && b.dataset.required === '1';
                                    if (!actif) input.value = '';
                                }
                            });
                        });
                    })();
                </script>
            @endif


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
            </div>{{-- /colonne principale --}}

            {{-- ══ Synthèse permanente ══ --}}
            <aside class="suivi-recap">
                <div class="card">
                    <div class="card-header"><i class="fas fa-clipboard-list"></i> Votre dossier</div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <span class="badge bg-{{ $candidature->status_badge }} px-3 py-2">{{ $candidature->statut_label }}</span>
                            <div class="mt-2"><code class="small">{{ $candidature->reference }}</code></div>
                        </div>
                        @php
                            $nbSections = $candidature->choix->count();
                            $nbCommunes = $candidature->files->whereNull('candidature_choix_id')->count();
                            $nbSpec     = $candidature->files->whereNotNull('candidature_choix_id')->count();
                            $paieDues   = \App\Models\Paiement::moduleActif()
                                ? $candidature->choix->filter(fn ($x) => $x->paiement && !$x->paiement->estPaye())->count() : 0;
                        @endphp
                        <div class="recap-l"><span style="color:var(--t2);">Parcours visés</span>
                            <span class="recap-v">{{ $nbSections }}</span></div>
                        <div class="recap-l"><span style="color:var(--t2);">Pièces communes</span>
                            <span class="recap-v">{{ $nbCommunes }}</span></div>
                        <div class="recap-l"><span style="color:var(--t2);">Pièces spécifiques</span>
                            <span class="recap-v">{{ $nbSpec }}</span></div>
                        @if (\App\Models\Paiement::moduleActif())
                            <div class="recap-l"><span style="color:var(--t2);">Frais à régler</span>
                                <span class="recap-v {{ $paieDues ? 'text-danger' : 'text-success' }}">
                                    {{ $paieDues ?: 'à jour' }}</span></div>
                        @endif

                        <div class="mt-2">
                            @foreach ($candidature->choix->sortBy('ordre') as $c)
                                <div class="aide-d"><i class="fas fa-circle-check text-success"></i>
                                    {{ $c->parcours->nom ?? '—' }}</div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="card mt-3 d-none d-lg-block">
                    <div class="card-header"><i class="fas fa-circle-question"></i> Où en êtes-vous ?</div>
                    <div class="card-body py-2">
                        <div class="aide-e"><div class="aide-n">1</div>
                            <div><div class="aide-t">Dossier reçu</div>
                                <div class="aide-d">Votre candidature est enregistrée sous sa référence.</div></div></div>
                        <div class="aide-e"><div class="aide-n">2</div>
                            <div><div class="aide-t">Vérification des pièces</div>
                                <div class="aide-d">Un agent contrôle chaque pièce. Une pièce refusée s'affiche en rouge avec son motif.</div></div></div>
                        <div class="aide-e"><div class="aide-n">3</div>
                            <div><div class="aide-t">Frais éventuels</div>
                                <div class="aide-d">Chaque parcours a sa propre quittance à déclarer.</div></div></div>
                        <div class="aide-e"><div class="aide-n">4</div>
                            <div><div class="aide-t">Décision</div>
                                <div class="aide-d">Elle vous est communiquée ici et par e-mail.</div></div></div>
                        <div class="aide-e"><div class="aide-n"><i class="fas fa-plus"></i></div>
                            <div><div class="aide-t">Ajouter un parcours</div>
                                <div class="aide-d">Possible tant qu'aucune décision n'est rendue — vos pièces communes sont reprises.</div></div></div>
                    </div>
                </div>
            </aside>
            </div>{{-- /suivi-grid --}}
        @endif

        <p class="text-center mt-4" style="font-size:13px;">
            <a href="{{ route('candidature.create') }}" style="color:var(--brand);text-decoration:none;"><i class="fas fa-file-signature"></i> Déposer une candidature</a>
            &nbsp;·&nbsp;
            <a href="{{ route('login') }}" style="color:var(--t2);text-decoration:none;">Retour à la connexion</a>
        </p>
    </div>
@endsection
