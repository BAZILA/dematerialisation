@extends('layouts.app')
@section('page-title', 'Procédures')
@section('page-subtitle', 'Opérations dématérialisées — créez et paramétrez les démarches')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="text-muted small">
            <i class="fas fa-circle-info"></i> Chaque procédure est une démarche (dépôt, attestation, candidature…).
            Après création, réglez ses <strong>types de documents</strong> et ses <strong>fenêtres d'ouverture</strong>.
        </div>
        <button class="btn btn-primary" onclick="openProc('create')" data-bs-toggle="modal" data-bs-target="#modalProc">
            <i class="fas fa-plus"></i> Ajouter une procédure
        </button>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr><th style="width:50px;">#</th><th>Procédure</th><th>Accès</th><th>Public cible</th><th>Types doc.</th><th>Statut</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($procedures as $p)
                        <tr class="{{ $p->is_active ? '' : 'opacity-50' }}">
                            <td class="text-muted small">{{ $p->ordre }}</td>
                            <td>
                                <strong>{{ $p->nom }}</strong> <code class="small">{{ $p->code }}</code>
                                @if ($p->description)<br><small class="text-muted">{{ $p->description }}</small>@endif
                            </td>
                            <td>
                                @if ($p->requires_auth)
                                    <span class="badge bg-secondary"><i class="fas fa-lock"></i> Authentifié</span>
                                @else
                                    <span class="badge bg-info"><i class="fas fa-globe"></i> Anonyme (public)</span>
                                @endif
                            </td>
                            <td class="small">
                                @if ($p->pour_nouveaux)<span class="badge bg-primary">Nouveaux</span>@endif
                                @if ($p->pour_anciens)<span class="badge bg-primary">Anciens</span>@endif
                                @if (!$p->pour_nouveaux && !$p->pour_anciens)<span class="text-muted">—</span>@endif
                            </td>
                            <td><span class="badge bg-secondary">{{ $p->request_types_count }}</span></td>
                            <td>
                                @if ($p->is_active)<span class="badge bg-success"><i class="fas fa-eye"></i> Active</span>
                                @else<span class="badge bg-secondary"><i class="fas fa-eye-slash"></i> Inactive</span>@endif
                            </td>
                            <td class="text-end" style="white-space:nowrap;">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalProc"
                                    onclick="openProc('edit', this)" data-url="{{ route('admin.procedures.update', $p) }}"
                                    data-nom="{{ $p->nom }}" data-code="{{ $p->code }}" data-desc="{{ $p->description }}"
                                    data-auth="{{ $p->requires_auth ? 1 : 0 }}" data-nouv="{{ $p->pour_nouveaux ? 1 : 0 }}"
                                    data-anc="{{ $p->pour_anciens ? 1 : 0 }}" data-active="{{ $p->is_active ? 1 : 0 }}" data-ordre="{{ $p->ordre }}">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('admin.procedures.destroy', $p) }}" class="d-inline" onsubmit="return confirm('Supprimer cette procédure ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">Aucune procédure.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ═══ Modal Procédure ═══ --}}
    <div class="modal fade" id="modalProc" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formProc" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="pr_method" value="POST">
                    <div class="modal-header"><h6 class="modal-title" id="prTitle">Nouvelle procédure</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-8"><label class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nom" required></div>
                            <div class="col-4"><label class="form-label">Ordre</label>
                                <input type="number" class="form-control" name="ordre" min="0" value="99"></div>
                        </div>
                        <div class="mt-3" id="pr_code_wrap"><label class="form-label">Code <span class="text-danger">*</span> <small class="text-muted">(identifiant technique, non modifiable ensuite)</small></label>
                            <input type="text" class="form-control" name="code" placeholder="ex : bourse, transfert"></div>
                        <div class="mt-3"><label class="form-label">Description</label>
                            <input type="text" class="form-control" name="description" maxlength="500"></div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" name="requires_auth" value="1" id="pr_auth" checked>
                            <label class="form-check-label" for="pr_auth"><strong>Authentification requise</strong><br><small class="text-muted">Décocher = soumission <strong>anonyme</strong> (ex : candidatures)</small></label>
                        </div>
                        <div class="d-flex gap-4 flex-wrap mt-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="pour_nouveaux" value="1" id="pr_nouv">
                                <label class="form-check-label" for="pr_nouv">Nouveaux bacheliers</label></div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="pour_anciens" value="1" id="pr_anc" checked>
                                <label class="form-check-label" for="pr_anc">Anciens étudiants</label></div>
                        </div>
                        <div class="form-check form-switch mt-3" id="pr_active_wrap" style="display:none;">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="pr_active" checked>
                            <label class="form-check-label" for="pr_active">Active</label></div>
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
        const PR_STORE = "{{ route('admin.procedures.store') }}";

        function openProc(mode, el) {
            const f = document.getElementById('formProc');
            f.reset();
            const isEdit = mode === 'edit';
            document.getElementById('prTitle').textContent = isEdit ? 'Modifier la procédure' : 'Nouvelle procédure';
            document.getElementById('pr_method').value = isEdit ? 'PUT' : 'POST';
            document.getElementById('pr_code_wrap').style.display = isEdit ? 'none' : '';
            document.getElementById('pr_active_wrap').style.display = isEdit ? '' : 'none';
            f.code.required = !isEdit;
            if (isEdit) {
                f.action = el.dataset.url;
                f.nom.value = el.dataset.nom || '';
                f.description.value = el.dataset.desc || '';
                f.ordre.value = el.dataset.ordre || 99;
                document.getElementById('pr_auth').checked = el.dataset.auth === '1';
                document.getElementById('pr_nouv').checked = el.dataset.nouv === '1';
                document.getElementById('pr_anc').checked = el.dataset.anc === '1';
                document.getElementById('pr_active').checked = el.dataset.active === '1';
            } else {
                f.action = PR_STORE;
            }
        }
    </script>
@endsection
