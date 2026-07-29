@extends('layouts.app')
@section('page-title', 'Référentiel académique')
@section('page-subtitle', 'Facultés / Instituts / Écoles → Départements → Parcours')

@section('content')
    @php $grades = \App\Models\Parcours::$grades; @endphp

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="text-muted small">
            <i class="fas fa-circle-info"></i> Cliquez sur une composante pour la déplier.
            Données de classement — servent à filtrer les demandes et définir les périmètres des agents.
        </div>
        <button class="btn btn-primary" onclick="openFaculte('create')" data-bs-toggle="modal" data-bs-target="#modalFaculte">
            <i class="fas fa-plus"></i> Ajouter une composante
        </button>
    </div>

    {{-- Domaines --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="fas fa-shapes" style="color:var(--brand);"></i> <strong class="ms-1">Domaines</strong>
                <span class="text-muted small ms-2 d-none d-md-inline">Servent au remplissage du verso des fiches de dépôt.</span></span>
            <button class="btn btn-sm btn-outline-primary" onclick="openDomaine('create')" data-bs-toggle="modal" data-bs-target="#modalDomaine"><i class="fas fa-plus"></i> Domaine</button>
        </div>
        <div class="card-body d-flex flex-wrap gap-2">
            @forelse ($domaines as $d)
                <span class="badge bg-secondary d-inline-flex align-items-center gap-2" style="font-size:12.5px;padding:7px 11px !important;">
                    <span>{{ $d->nom }}@if ($d->sigle) · {{ $d->sigle }}@endif</span>
                    <button type="button" class="btn btn-link p-0" style="color:var(--t2);font-size:11px;line-height:1;"
                        onclick="openDomaine('edit', this)" data-bs-toggle="modal" data-bs-target="#modalDomaine"
                        data-url="{{ route('admin.academique.domaines.update', $d) }}" data-nom="{{ $d->nom }}" data-sigle="{{ $d->sigle }}" data-active="{{ $d->is_active ? 1 : 0 }}"><i class="fas fa-pen"></i></button>
                    <form method="POST" action="{{ route('admin.academique.domaines.destroy', $d) }}" onsubmit="return confirm('Supprimer ce domaine ?')" style="display:inline;">
                        @csrf @method('DELETE')
                        <button class="btn btn-link p-0" style="color:var(--red);font-size:11px;line-height:1;"><i class="fas fa-times"></i></button>
                    </form>
                </span>
            @empty
                <span class="text-muted small">Aucun domaine. Ajoutez-en pour les proposer au dépôt.</span>
            @endforelse
        </div>
    </div>

    @forelse ($facultes as $fac)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div data-bs-toggle="collapse" data-bs-target="#fac-{{ $fac->id }}" role="button" style="cursor:pointer;flex:1;min-width:0;">
                    <i class="fas fa-chevron-down me-1" style="font-size:11px;color:var(--t3);"></i>
                    @php
                        $facIcone = ['institut' => 'fa-building-columns', 'ecole' => 'fa-school'][$fac->type] ?? 'fa-landmark';
                    @endphp
                    <i class="fas {{ $facIcone }}" style="color:var(--brand);"></i>
                    <strong class="ms-1">{{ $fac->nom }}</strong>
                    @if ($fac->sigle)<span class="badge bg-secondary ms-1">{{ $fac->sigle }}</span>@endif
                    <span class="badge bg-primary ms-1">{{ $fac->type_label }}</span>
                    <span class="badge bg-secondary ms-1">{{ $fac->departements->count() }} dépt.</span>
                    @unless ($fac->is_active)<span class="badge bg-warning ms-1">Inactif</span>@endunless
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalDepartement"
                        onclick="openDepartement('create', this)" data-faculte="{{ $fac->id }}" title="Ajouter un département">
                        <i class="fas fa-plus"></i> Département
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalFaculte"
                        onclick="openFaculte('edit', this)" data-url="{{ route('admin.academique.facultes.update', $fac) }}"
                        data-nom="{{ $fac->nom }}" data-sigle="{{ $fac->sigle }}" data-type="{{ $fac->type }}" data-active="{{ $fac->is_active ? 1 : 0 }}">
                        <i class="fas fa-pen"></i>
                    </button>
                    <form method="POST" action="{{ route('admin.academique.facultes.destroy', $fac) }}" onsubmit="return confirm('Supprimer « {{ addslashes($fac->nom) }} » et tout son contenu ?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>

            <div class="collapse" id="fac-{{ $fac->id }}">
                <div class="card-body">
                    @forelse ($fac->departements as $dep)
                        <div class="mb-3 p-3 rounded" style="background:var(--surface-2);border:1px solid var(--line);">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                <div>
                                    <i class="fas fa-diagram-project" style="color:var(--brand);"></i>
                                    <strong class="ms-1">{{ $dep->nom }}</strong>
                                    @if ($dep->sigle)<span class="badge bg-secondary ms-1">{{ $dep->sigle }}</span>@endif
                                </div>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalParcours"
                                        onclick="openParcours('create', this)" data-departement="{{ $dep->id }}" title="Ajouter un parcours">
                                        <i class="fas fa-plus"></i> Parcours
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalDepartement"
                                        onclick="openDepartement('edit', this)" data-url="{{ route('admin.academique.departements.update', $dep) }}"
                                        data-faculte="{{ $dep->faculte_id }}" data-nom="{{ $dep->nom }}" data-sigle="{{ $dep->sigle }}" data-active="{{ $dep->is_active ? 1 : 0 }}">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <form method="POST" action="{{ route('admin.academique.departements.destroy', $dep) }}" onsubmit="return confirm('Supprimer ce département et ses parcours ?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>

                            @if ($dep->parcours->isEmpty())
                                <div class="small text-muted ps-4">Aucun parcours.</div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0" style="background:var(--surface);border-radius:8px;">
                                        <tbody>
                                            @foreach ($dep->parcours as $p)
                                                <tr>
                                                    <td style="width:24px;color:var(--t3);"><i class="fas fa-graduation-cap"></i></td>
                                                    <td><strong>{{ $p->nom }}</strong>
                                                        @if ($p->sigle)<span class="badge bg-secondary ms-1">{{ $p->sigle }}</span>@endif
                                                    </td>
                                                    <td><span class="badge bg-info">{{ $p->grade_label }}</span>
                                                        @if ($p->domaine)<span class="badge bg-secondary ms-1" title="Domaine">{{ $p->domaine->sigle ?? $p->domaine->nom }}</span>@endif
                                                    </td>
                                                    <td class="text-muted small">{{ implode(' · ', $p->semestres()) }}</td>
                                                    <td class="text-end" style="white-space:nowrap;">
                                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalParcours"
                                                            onclick="openParcours('edit', this)" data-url="{{ route('admin.academique.parcours.update', $p) }}"
                                                            data-departement="{{ $p->departement_id }}" data-nom="{{ $p->nom }}" data-sigle="{{ $p->sigle }}" data-grade="{{ $p->grade }}" data-domaine="{{ $p->domaine_id }}" data-active="{{ $p->is_active ? 1 : 0 }}">
                                                            <i class="fas fa-pen"></i>
                                                        </button>
                                                        <form method="POST" action="{{ route('admin.academique.parcours.destroy', $p) }}" class="d-inline" onsubmit="return confirm('Supprimer ce parcours ?')">
                                                            @csrf @method('DELETE')
                                                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="text-muted small">Aucun département. Cliquez sur « Département » pour en ajouter.</div>
                    @endforelse
                </div>
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <i class="fas fa-sitemap d-block mb-2" style="font-size:2rem;opacity:.35;"></i>
                Aucune composante. Cliquez sur « Ajouter une composante » pour commencer.
            </div>
        </div>
    @endforelse

    {{-- ═══ Modal composante (faculté / institut / école) ═══ --}}
    <div class="modal fade" id="modalFaculte" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formFaculte" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="fac_method" value="POST">
                    <div class="modal-header"><h6 class="modal-title" id="facTitle">Nouvelle composante</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nom" required></div>
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label">Sigle</label>
                                <input type="text" class="form-control" name="sigle"></div>
                            <div class="col-6"><label class="form-label">Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="type" required>
                                    @foreach (\App\Models\Faculte::$types as $val => $lbl)
                                        <option value="{{ $val }}">{{ $lbl }}</option>
                                    @endforeach
                                </select></div>
                        </div>
                        <div class="form-check form-switch mt-3" id="fac_active_wrap" style="display:none;">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="fac_active" checked>
                            <label class="form-check-label" for="fac_active">Actif</label>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary btn-sm" data-loading-text="Enregistrement…"><i class="fas fa-save"></i> Enregistrer</button></div>
                </form>
            </div>
        </div>
    </div>

    {{-- ═══ Modal Département ═══ --}}
    <div class="modal fade" id="modalDepartement" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formDepartement" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="dep_method" value="POST">
                    <input type="hidden" name="faculte_id" id="dep_faculte">
                    <div class="modal-header"><h6 class="modal-title" id="depTitle">Nouveau département</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nom" required></div>
                        <div><label class="form-label">Sigle</label>
                            <input type="text" class="form-control" name="sigle"></div>
                        <div class="form-check form-switch mt-3" id="dep_active_wrap" style="display:none;">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="dep_active" checked>
                            <label class="form-check-label" for="dep_active">Actif</label>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary btn-sm" data-loading-text="Enregistrement…"><i class="fas fa-save"></i> Enregistrer</button></div>
                </form>
            </div>
        </div>
    </div>

    {{-- ═══ Modal Parcours ═══ --}}
    <div class="modal fade" id="modalParcours" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formParcours" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="par_method" value="POST">
                    <input type="hidden" name="departement_id" id="par_departement">
                    <div class="modal-header"><h6 class="modal-title" id="parTitle">Nouveau parcours</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nom" required></div>
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label">Sigle</label>
                                <input type="text" class="form-control" name="sigle"></div>
                            <div class="col-6"><label class="form-label">Grade <span class="text-danger">*</span></label>
                                <select class="form-select" name="grade" required>
                                    @foreach ($grades as $key => $g)
                                        <option value="{{ $key }}">{{ $g['label'] }}</option>
                                    @endforeach
                                </select></div>
                        </div>
                        <div class="mt-3"><label class="form-label">Domaine <span class="text-muted small">(verso des dépôts)</span></label>
                            <select class="form-select" name="domaine_id" id="par_domaine">
                                <option value="">— Aucun —</option>
                                @foreach ($domaines as $d)
                                    <option value="{{ $d->id }}">{{ $d->nom }}</option>
                                @endforeach
                            </select></div>
                        <div class="form-check form-switch mt-3" id="par_active_wrap" style="display:none;">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="par_active" checked>
                            <label class="form-check-label" for="par_active">Actif</label>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary btn-sm" data-loading-text="Enregistrement…"><i class="fas fa-save"></i> Enregistrer</button></div>
                </form>
            </div>
        </div>
    </div>

    {{-- ═══ Modal Domaine ═══ --}}
    <div class="modal fade" id="modalDomaine" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formDomaine" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="dom_method" value="POST">
                    <div class="modal-header"><h6 class="modal-title" id="domTitle">Nouveau domaine</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nom" required></div>
                        <div><label class="form-label">Sigle</label>
                            <input type="text" class="form-control" name="sigle"></div>
                        <div class="form-check form-switch mt-3" id="dom_active_wrap" style="display:none;">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="dom_active" checked>
                            <label class="form-check-label" for="dom_active">Actif</label>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary btn-sm" data-loading-text="Enregistrement…"><i class="fas fa-save"></i> Enregistrer</button></div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        const ACAD = {
            facStore: "{{ route('admin.academique.facultes.store') }}",
            depStore: "{{ route('admin.academique.departements.store') }}",
            parStore: "{{ route('admin.academique.parcours.store') }}",
            domStore: "{{ route('admin.academique.domaines.store') }}",
        };

        function openFaculte(mode, el) {
            const f = document.getElementById('formFaculte');
            f.reset();
            document.getElementById('facTitle').textContent = mode === 'edit' ? 'Modifier la composante' : 'Nouvelle composante';
            document.getElementById('fac_active_wrap').style.display = mode === 'edit' ? '' : 'none';
            if (mode === 'edit') {
                f.action = el.dataset.url; document.getElementById('fac_method').value = 'PUT';
                f.nom.value = el.dataset.nom; f.sigle.value = el.dataset.sigle || ''; f.type.value = el.dataset.type;
                document.getElementById('fac_active').checked = el.dataset.active === '1';
            } else {
                f.action = ACAD.facStore; document.getElementById('fac_method').value = 'POST';
            }
        }

        function openDepartement(mode, el) {
            const f = document.getElementById('formDepartement');
            f.reset();
            document.getElementById('depTitle').textContent = mode === 'edit' ? 'Modifier le département' : 'Nouveau département';
            document.getElementById('dep_active_wrap').style.display = mode === 'edit' ? '' : 'none';
            document.getElementById('dep_faculte').value = el.dataset.faculte;
            if (mode === 'edit') {
                f.action = el.dataset.url; document.getElementById('dep_method').value = 'PUT';
                f.nom.value = el.dataset.nom; f.sigle.value = el.dataset.sigle || '';
                document.getElementById('dep_active').checked = el.dataset.active === '1';
            } else {
                f.action = ACAD.depStore; document.getElementById('dep_method').value = 'POST';
            }
        }

        function openParcours(mode, el) {
            const f = document.getElementById('formParcours');
            f.reset();
            document.getElementById('parTitle').textContent = mode === 'edit' ? 'Modifier le parcours' : 'Nouveau parcours';
            document.getElementById('par_active_wrap').style.display = mode === 'edit' ? '' : 'none';
            document.getElementById('par_departement').value = el.dataset.departement;
            if (mode === 'edit') {
                f.action = el.dataset.url; document.getElementById('par_method').value = 'PUT';
                f.nom.value = el.dataset.nom; f.sigle.value = el.dataset.sigle || ''; f.grade.value = el.dataset.grade;
                document.getElementById('par_domaine').value = el.dataset.domaine || '';
                document.getElementById('par_active').checked = el.dataset.active === '1';
            } else {
                f.action = ACAD.parStore; document.getElementById('par_method').value = 'POST';
            }
        }

        function openDomaine(mode, el) {
            const f = document.getElementById('formDomaine');
            f.reset();
            document.getElementById('domTitle').textContent = mode === 'edit' ? 'Modifier le domaine' : 'Nouveau domaine';
            document.getElementById('dom_active_wrap').style.display = mode === 'edit' ? '' : 'none';
            if (mode === 'edit') {
                f.action = el.dataset.url; document.getElementById('dom_method').value = 'PUT';
                f.nom.value = el.dataset.nom; f.sigle.value = el.dataset.sigle || '';
                document.getElementById('dom_active').checked = el.dataset.active === '1';
            } else {
                f.action = ACAD.domStore; document.getElementById('dom_method').value = 'POST';
            }
        }
    </script>
@endsection
