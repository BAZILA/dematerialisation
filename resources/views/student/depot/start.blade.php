@extends('layouts.student')
@section('page-title', 'Nouveau dépôt')
@section('page-subtitle', 'Étape 1 — Votre contexte académique')

@section('content')
    @php
        // Arbre pour la cascade côté client
        $tree = $facultes->map(fn ($f) => [
            'id'   => $f->id,
            'nom'  => $f->type_label . ' — ' . $f->nom,
            'departements' => $f->departements->map(fn ($d) => [
                'id'  => $d->id,
                'nom' => $d->nom,
                'parcours' => $d->parcours->map(fn ($p) => [
                    'id'    => $p->id,
                    'nom'   => $p->nom . ($p->sigle ? " ({$p->sigle})" : ''),
                    'grade' => $p->grade,
                ])->values(),
            ])->values(),
        ])->values();

        /* $gradeNiveaux fourni par le contrôleur : grade → niveaux (années d'études) */
    @endphp

    <div class="row justify-content-center">
        <div class="col-lg-8">

            {{-- Fil d'étapes --}}
            <div class="d-flex align-items-center gap-2 mb-4 small" style="color:var(--t2);">
                <span class="badge bg-primary rounded-pill px-3 py-2">1. Contexte</span>
                <i class="fas fa-chevron-right" style="font-size:10px;opacity:.5;"></i>
                <span class="badge bg-light text-muted rounded-pill px-3 py-2">2. Pièces & études</span>
                <i class="fas fa-chevron-right" style="font-size:10px;opacity:.5;"></i>
                <span class="badge bg-light text-muted rounded-pill px-3 py-2">3. Engagement & envoi</span>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
                </div>
            @endif

            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="fas fa-folder-plus text-primary"></i>
                    <span style="font-weight:600;color:var(--t1);">Démarrer mon dépôt</span>
                </div>
                <div class="card-body">

                    @if ($sit['mode'] === 'progression')
                        {{-- ═══ PROGRESSION AUTOMATIQUE : parcours et année imposés ═══ --}}
                        <div class="alert alert-info small d-flex align-items-start gap-2 mb-4">
                            <i class="fas fa-forward mt-1"></i>
                            <span>Votre <strong>{{ $sit['precedent'] }}</strong> a été validée —
                                votre dépôt <strong>{{ $annee->libelle }}</strong> continue automatiquement
                                dans le même parcours, en année suivante.</span>
                        </div>

                        <div class="p-3 rounded mb-4" style="background:var(--surface-2);border:1px solid var(--line);">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="small" style="color:var(--t3);">Parcours (inchangé)</div>
                                    <div class="fw-bold" style="color:var(--t1);">{{ $sit['parcours']->nom }}
                                        <span class="badge bg-secondary ms-1" style="font-size:10px;">{{ $sit['parcours']->grade_label }}</span></div>
                                    <div class="small" style="color:var(--t2);">{{ $sit['parcours']->departement->faculte->nom ?? '' }}</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small" style="color:var(--t3);">Année académique</div>
                                    <div class="fw-bold" style="color:var(--t1);">{{ $annee->libelle }}</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="small" style="color:var(--t3);">Année d'études</div>
                                    <div class="fw-bold" style="color:var(--t1);">{{ $sit['label'] }}</div>
                                    @if ($sit['derogatoire'])
                                        <span class="badge mt-1" style="background:rgba(245,158,11,.15);color:#B45309;border:1px solid rgba(245,158,11,.35);">
                                            <i class="fas fa-triangle-exclamation"></i> Année dérogatoire
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @if ($sit['derogatoire'])
                            <div class="alert alert-warning small d-flex align-items-start gap-2 mb-4">
                                <i class="fas fa-circle-info mt-1"></i>
                                <span>Cette année d'études dépasse la durée normale du grade : votre dépôt sera
                                    marqué <strong>dérogatoire</strong> et examiné comme tel par l'administration.</span>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('student.depot.store') }}">
                            @csrf
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ route('student.dashboard', ['tab' => 'depot']) }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Annuler
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg" data-loading-text="Création…">
                                    Continuer en {{ $sit['label'] }} <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </form>

                    @else
                        {{-- ═══ PREMIER DÉPÔT : cascade de choix ═══ --}}
                        <div class="alert alert-info small d-flex align-items-start gap-2 mb-4">
                            <i class="fas fa-info-circle mt-1"></i>
                            <span>
                                Indiquez votre <strong>faculté / institut / école</strong> et votre
                                <strong>parcours</strong> pour l'année <strong>{{ $annee->libelle }}</strong>.
                                @if ($sit['mode'] === 'auto_a1')
                                    En tant que <strong>nouveau bachelier</strong>, la
                                    <strong>1ère année</strong> vous est attribuée automatiquement.
                                @else
                                    Choisissez aussi votre <strong>année d'études actuelle</strong> —
                                    ce choix (unique) servira de base : les années suivantes
                                    avanceront automatiquement.
                                @endif
                            </span>
                        </div>

                        <form method="POST" action="{{ route('student.depot.store') }}">
                            @csrf

                            {{-- Année (fixée à l'année ouverte aux dépôts) --}}
                            <div class="mb-3">
                                <label class="form-label" style="color:var(--t2);">Année académique</label>
                                <input type="text" class="form-control" value="{{ $annee->libelle }}" disabled>
                                <small style="color:var(--t3);">Période de dépôt actuellement ouverte.</small>
                            </div>

                            {{-- Faculté --}}
                            <div class="mb-3">
                                <label class="form-label" style="color:var(--t2);">Faculté / Institut / École <span class="text-danger">*</span></label>
                                <select id="sel-faculte" class="form-select" required>
                                    <option value="">— Choisir —</option>
                                    @foreach ($tree as $f)
                                        <option value="{{ $f['id'] }}">{{ $f['nom'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Département --}}
                            <div class="mb-3">
                                <label class="form-label" style="color:var(--t2);">Département <span class="text-danger">*</span></label>
                                <select id="sel-departement" class="form-select" required disabled>
                                    <option value="">— Choisir d'abord une faculté —</option>
                                </select>
                            </div>

                            {{-- Parcours --}}
                            <div class="mb-3">
                                <label class="form-label" style="color:var(--t2);">Parcours <span class="text-danger">*</span></label>
                                <select id="sel-parcours" name="parcours_id" class="form-select" required disabled>
                                    <option value="">— Choisir d'abord un département —</option>
                                </select>
                            </div>

                            @if ($sit['mode'] === 'auto_a1')
                                {{-- Nouveau bachelier : 1ère année imposée --}}
                                <div class="mb-4">
                                    <label class="form-label" style="color:var(--t2);">Année d'études</label>
                                    <input type="text" class="form-control" id="niveau-fixe"
                                        value="1ère année — attribuée automatiquement" disabled>
                                    <small style="color:var(--t3);"><i class="fas fa-lock"></i>
                                        Les nouveaux bacheliers commencent en 1ère année.</small>
                                </div>
                            @else
                                {{-- Ancien : choix UNIQUE (dérogatoires incluses) --}}
                                <div class="mb-4">
                                    <label class="form-label" style="color:var(--t2);">Année d'études actuelle <span class="text-danger">*</span></label>
                                    <select id="sel-semestre" name="semestre" class="form-select" required disabled>
                                        <option value="">— Choisir d'abord un parcours —</option>
                                    </select>
                                    <small id="saison-hint" style="color:var(--t3);"></small>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between align-items-center">
                                <a href="{{ route('student.dashboard', ['tab' => 'depot']) }}" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Annuler
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg" data-loading-text="Création…">
                                    Continuer <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        (function () {
            const TREE = @json($tree);
            const NIVEAUX = @json($gradeNiveaux);

            const selF = document.getElementById('sel-faculte');
            const selD = document.getElementById('sel-departement');
            const selP = document.getElementById('sel-parcours');
            const selS = document.getElementById('sel-semestre'); // absent en mode auto/progression
            const hint = document.getElementById('saison-hint');

            if (!selF) return; // mode progression : aucun choix à faire

            const reset = (sel, placeholder) => {
                if (!sel) return;
                sel.innerHTML = '<option value="">' + placeholder + '</option>';
                sel.disabled = true;
            };

            selF.addEventListener('change', function () {
                reset(selD, '— Choisir un département —');
                reset(selP, '— Choisir d\'abord un département —');
                reset(selS, '— Choisir d\'abord un parcours —');
                if (hint) hint.textContent = '';
                const fac = TREE.find(f => f.id == this.value);
                if (!fac) return;
                fac.departements.forEach(d => {
                    selD.insertAdjacentHTML('beforeend', `<option value="${d.id}">${d.nom}</option>`);
                });
                selD.disabled = false;
            });

            selD.addEventListener('change', function () {
                reset(selP, '— Choisir un parcours —');
                reset(selS, '— Choisir d\'abord un parcours —');
                if (hint) hint.textContent = '';
                const fac = TREE.find(f => f.id == selF.value);
                const dep = fac?.departements.find(d => d.id == this.value);
                if (!dep) return;
                if (!dep.parcours.length) {
                    selP.innerHTML = '<option value="">Aucun parcours disponible</option>';
                    return;
                }
                dep.parcours.forEach(p => {
                    selP.insertAdjacentHTML('beforeend', `<option value="${p.id}" data-grade="${p.grade}">${p.nom}</option>`);
                });
                selP.disabled = false;
            });

            selP.addEventListener('change', function () {
                if (!selS) return; // nouveau bachelier : A1 imposé, rien à choisir
                reset(selS, '— Choisir votre année d\'études —');
                if (hint) hint.textContent = '';
                const grade = this.selectedOptions[0]?.dataset.grade;
                const niveaux = NIVEAUX[grade] || {};
                const codes = Object.keys(niveaux);
                if (!codes.length) return;
                // Une option par année d'études — les dérogatoires sont signalées
                codes.forEach(code => {
                    const n = niveaux[code];
                    selS.insertAdjacentHTML('beforeend',
                        `<option value="${code}">${n.label}${n.derogatoire ? ' ⚠' : ''}</option>`);
                });
                selS.disabled = false;
            });

            selS?.addEventListener('change', function () {
                const g = NIVEAUX[selP.selectedOptions[0]?.dataset.grade]?.[this.value];
                if (!hint || !g) { if (hint) hint.textContent = ''; return; }
                hint.innerHTML = g.derogatoire
                    ? '<i class="fas fa-triangle-exclamation" style="color:#B45309;"></i> Année dérogatoire : au-delà de la durée normale du grade — ce choix servira de base pour les années suivantes.'
                    : (g.semestres.length > 1
                        ? '<i class="fas fa-circle-info"></i> Le dépôt couvre les deux semestres (' + g.semestres.join(' et ') + '). Ce choix (unique) servira de base pour la suite.'
                        : '');
            });
        })();
    </script>
@endsection
