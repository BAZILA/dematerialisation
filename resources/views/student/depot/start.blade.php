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

        $gradeSemestres = collect($grades)->mapWithKeys(fn ($g, $k) => [$k => $g['semestres']]);
    @endphp
    @php /* $gradeAnnees fourni par le contrôleur : grade → groupes d'années */ @endphp

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

                    <div class="alert alert-info small d-flex align-items-start gap-2 mb-4">
                        <i class="fas fa-info-circle mt-1"></i>
                        <span>Indiquez d'abord votre <strong>faculté / institut</strong>, votre
                            <strong>parcours</strong> et votre <strong>semestre</strong> pour l'année
                            <strong>{{ $annee->libelle }}</strong>. Vous ajouterez ensuite vos pièces.</span>
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
                            <label class="form-label" style="color:var(--t2);">Faculté / Institut <span class="text-danger">*</span></label>
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

                        {{-- Année d'études & semestre --}}
                        <div class="mb-4">
                            <label class="form-label" style="color:var(--t2);">Année d'études & semestre <span class="text-danger">*</span></label>
                            <select id="sel-semestre" name="semestre" class="form-select" required disabled>
                                <option value="">— Choisir d'abord un parcours —</option>
                            </select>
                            <small id="saison-hint" style="color:var(--blue);"></small>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="{{ route('student.dashboard', ['tab' => 'depot']) }}" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg" data-loading-text="Création…">
                                Continuer <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        (function () {
            const TREE = @json($tree);
            const SEM  = @json($gradeSemestres);
            const ANNEES = @json($gradeAnnees);

            const selF = document.getElementById('sel-faculte');
            const selD = document.getElementById('sel-departement');
            const selP = document.getElementById('sel-parcours');
            const selS = document.getElementById('sel-semestre');
            const hint = document.getElementById('saison-hint');

            const reset = (sel, placeholder) => {
                sel.innerHTML = '<option value="">' + placeholder + '</option>';
                sel.disabled = true;
            };

            selF.addEventListener('change', function () {
                reset(selD, '— Choisir un département —');
                reset(selP, '— Choisir d\'abord un département —');
                reset(selS, '— Choisir d\'abord un parcours —');
                hint.textContent = '';
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
                hint.textContent = '';
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
                reset(selS, '— Choisir une année / un semestre —');
                hint.textContent = '';
                const grade = this.selectedOptions[0]?.dataset.grade;
                const groupes = ANNEES[grade] || [];
                if (!groupes.length) return;
                // Un optgroup par année d'études : « 1ère année (S1 & S2) »
                groupes.forEach(g => {
                    const sems = g.semestres || [];
                    if (sems.length > 1) {
                        let html = `<optgroup label="${g.label}">`;
                        sems.forEach(s => { html += `<option value="${s}">${s}</option>`; });
                        html += `</optgroup>`;
                        selS.insertAdjacentHTML('beforeend', html);
                    } else if (sems.length === 1) {
                        // Année à un seul palier (ex. doctorat) : option simple
                        selS.insertAdjacentHTML('beforeend', `<option value="${sems[0]}">${g.annee}</option>`);
                    }
                });
                selS.disabled = false;
            });

            selS.addEventListener('change', function () {
                const m = this.value.match(/(\d+)/);
                if (!m) { hint.textContent = ''; return; }
                const saison = (parseInt(m[1], 10) % 2 === 1) ? 'Harmattan' : 'Mousson';
                hint.innerHTML = '<i class="fas fa-leaf"></i> Session ' + saison;
            });
        })();
    </script>
@endsection
