@extends('layouts.app')
@section('page-title', 'Périmètres des agents')
@section('page-subtitle', "Restreindre un agent à des parcours × semestres (défaut : tout)")

@section('content')
    @php
        $tree = $facultes->map(fn ($f) => [
            'id'  => $f->id,
            'nom' => $f->type_label . ' — ' . $f->nom,
            'departements' => $f->departements->map(fn ($d) => [
                'id'  => $d->id,
                'nom' => $d->nom,
                'parcours' => $d->parcours->map(fn ($p) => [
                    'id' => $p->id, 'nom' => $p->nom . ($p->sigle ? " ({$p->sigle})" : ''), 'grade' => $p->grade,
                ])->values(),
            ])->values(),
        ])->values();
        // Niveaux par grade — AVEC les années dérogatoires (un agent peut être
        // affecté aux dépôts 4e/5e année, redoublants et retardataires)
        $gradeNiveaux = collect($grades)->mapWithKeys(
            fn ($g, $k) => [$k => collect(\App\Models\Parcours::niveauxParGrade($k, avecDerogatoires: true))->map(fn ($n) => $n['label'])]
        );
    @endphp

    <div class="alert alert-info small d-flex align-items-start gap-2">
        <i class="fas fa-info-circle mt-1"></i>
        <span>
            Un agent <strong>sans aucune affectation couvre TOUT</strong>. Dès qu'on lui affecte un
            ou plusieurs parcours, il ne voit et ne traite plus que ces parcours.
            <br>
            <strong>Le périmètre s'applique à toutes les procédures</strong>, pas seulement aux dépôts :
            <ul class="mb-0 mt-1">
                <li><strong>Dépôts et autres demandes étudiantes</strong> : filtrage par
                    <em>parcours × année d'études</em> (années dérogatoires incluses).</li>
                <li><strong>Candidatures</strong> : filtrage <em>par parcours uniquement</em> — un candidat
                    n'a pas encore d'année d'études, les niveaux cochés ci-dessous y sont donc ignorés.
                    Une candidature reste visible dès qu'<em>un de ses vœux</em> tombe dans le périmètre.</li>
            </ul>
        </span>
    </div>

    <div class="accordion" id="accPerim">
        @foreach ($agents as $agent)
            @php $nb = $agent->perimetres->count(); @endphp
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#ag-{{ $agent->id }}">
                        <span class="fw-bold" style="color:var(--t1);">{{ $agent->staff_name }}</span>
                        <span class="badge bg-secondary ms-2" style="font-size:10px;">{{ $agent->role }}</span>
                        @if ($nb === 0)
                            <span class="badge bg-success ms-2"><i class="fas fa-globe"></i> Couvre tout</span>
                        @else
                            <span class="badge bg-primary ms-2"><i class="fas fa-location-crosshairs"></i> {{ $nb }} périmètre(s)</span>
                        @endif
                    </button>
                </h2>
                <div id="ag-{{ $agent->id }}" class="accordion-collapse collapse" data-bs-parent="#accPerim">
                    <div class="accordion-body">

                        {{-- Affectations actuelles --}}
                        @if ($agent->perimetres->isNotEmpty())
                            <div class="table-responsive mb-3">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr style="font-size:12px;color:var(--t2);"><th>Faculté</th><th>Parcours</th><th>Année d'études</th><th></th></tr></thead>
                                    <tbody>
                                        @foreach ($agent->perimetres as $p)
                                            <tr>
                                                <td class="small">{{ $p->parcours->departement->faculte->nom ?? '—' }}</td>
                                                <td class="small"><strong>{{ $p->parcours->nom ?? '—' }}</strong></td>
                                                <td><span class="badge bg-light text-dark">{{ $p->semestre_label }}</span></td>
                                                <td class="text-end">
                                                    <form method="POST" action="{{ route('admin.perimetres.destroy', $p) }}" class="d-inline"
                                                        onsubmit="return confirm('Retirer cette affectation ?')">
                                                        @csrf @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-times"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="small text-muted"><i class="fas fa-globe"></i> Aucune restriction — cet agent couvre l'ensemble.</p>
                        @endif

                        {{-- Ajouter une affectation --}}
                        <form method="POST" action="{{ route('admin.perimetres.store') }}" class="perim-form">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $agent->id }}">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small">Faculté</label>
                                    <select class="form-select form-select-sm sel-fac" required>
                                        <option value="">— Choisir —</option>
                                        @foreach ($tree as $f)<option value="{{ $f['id'] }}">{{ $f['nom'] }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Département</label>
                                    <select class="form-select form-select-sm sel-dep" required disabled><option value="">—</option></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Parcours</label>
                                    <select class="form-select form-select-sm sel-par" name="parcours_id" required disabled><option value="">—</option></select>
                                </div>
                            </div>

                            {{-- Semestres : sélection multiple --}}
                            <div class="mt-3 p-3 rounded sem-box" style="background:var(--surface-2);border:1px solid var(--line);display:none;">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                    <label class="form-label small mb-0">
                                        Années d'études <span class="text-muted">(plusieurs possibles — aucune cochée = toutes)</span>
                                    </label>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary sem-all">Tout cocher</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary sem-none">Tout décocher</button>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-3 sem-list"></div>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-sm btn-primary" data-loading-text="Affectation…">
                                    <i class="fas fa-plus"></i> Affecter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex justify-content-center mt-4">{{ $agents->links('pagination::bootstrap-5') }}</div>
@endsection

@section('js')
    <script>
        (function () {
            const TREE = @json($tree);
            const NIV  = @json($gradeNiveaux);

            document.querySelectorAll('.perim-form').forEach(form => {
                const selF = form.querySelector('.sel-fac');
                const selD = form.querySelector('.sel-dep');
                const selP = form.querySelector('.sel-par');
                const box  = form.querySelector('.sem-box');
                const list = form.querySelector('.sem-list');
                const reset = (s, ph) => { s.innerHTML = '<option value="">' + ph + '</option>'; s.disabled = true; };
                const hideSem = () => { box.style.display = 'none'; list.innerHTML = ''; };

                selF.addEventListener('change', function () {
                    reset(selD, '— Choisir —'); reset(selP, '—'); hideSem();
                    const fac = TREE.find(f => f.id == this.value); if (!fac) return;
                    fac.departements.forEach(d => selD.insertAdjacentHTML('beforeend', `<option value="${d.id}">${d.nom}</option>`));
                    selD.disabled = false;
                });
                selD.addEventListener('change', function () {
                    reset(selP, '—'); hideSem();
                    const fac = TREE.find(f => f.id == selF.value);
                    const dep = fac?.departements.find(d => d.id == this.value); if (!dep) return;
                    dep.parcours.forEach(p => selP.insertAdjacentHTML('beforeend', `<option value="${p.id}" data-grade="${p.grade}">${p.nom}</option>`));
                    selP.disabled = dep.parcours.length === 0;
                });
                selP.addEventListener('change', function () {
                    list.innerHTML = '';
                    const grade = this.selectedOptions[0]?.dataset.grade;
                    const niveaux = NIV[grade] || {};
                    const codes = Object.keys(niveaux);
                    if (!codes.length) { hideSem(); return; }
                    codes.forEach((code, i) => {
                        const id = `niv-${form.querySelector('[name="user_id"]').value}-${i}`;
                        list.insertAdjacentHTML('beforeend',
                            `<div class="form-check">
                                <input class="form-check-input" type="checkbox" name="semestres[]" value="${code}" id="${id}">
                                <label class="form-check-label small" for="${id}">${niveaux[code]}</label>
                             </div>`);
                    });
                    box.style.display = '';
                });

                form.querySelector('.sem-all')?.addEventListener('click', () =>
                    list.querySelectorAll('input').forEach(c => c.checked = true));
                form.querySelector('.sem-none')?.addEventListener('click', () =>
                    list.querySelectorAll('input').forEach(c => c.checked = false));
            });
        })();
    </script>
@endsection
