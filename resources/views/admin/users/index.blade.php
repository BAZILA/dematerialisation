@extends('layouts.app')
@section('page-title', 'Utilisateurs')
@section('page-subtitle', 'Comptes du personnel — rôles, noms réels et signatures')

@section('content')
    @php
        $roleLabels = [
            'super_admin' => 'Super administrateur', 'admin' => 'Administrateur',
            'gestionnaire_academique' => 'Gestionnaire académique', 'chef_scolarite' => 'Chef de section scolarité',
            'directeur_daas' => 'Directeur DAAS', 'directeur_adjoint_daas' => 'Directeur adjoint DAAS',
            'agent_depot' => 'Agent de dépôt', 'agent_carte' => 'Agent traitement carte',
            'agent_attestation' => 'Agent attestation', 'agent_releve' => 'Agent relevé de notes',
            'agent_homologation' => 'Agent homologation', 'agent_diplome' => 'Agent diplômes',
            'agent_candidature' => 'Agent candidatures', 'agent_financier' => 'Agent financier', 'auditeur' => 'Auditeur',
        ];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="text-muted small"><i class="fas fa-circle-info"></i> Les <strong>noms réels</strong> et <strong>signatures</strong> apparaissent sur les fiches validées (verso).</div>
        @canany(['utilisateurs.creer', 'utilisateurs.gerer'])
            <button class="btn btn-primary" onclick="openUser('create')" data-bs-toggle="modal" data-bs-target="#modalUser">
                <i class="fas fa-user-plus"></i> Ajouter un utilisateur
            </button>
        @endcanany
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr><th>Personnel</th><th>Identifiant</th><th>Rôle</th><th>Signature</th><th>Statut</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse ($users as $u)
                        <tr>
                            <td><strong>{{ $u->staff_name }}</strong><br><small class="text-muted">{{ $u->email }}</small></td>
                            <td><code>{{ $u->numero_identifiant }}</code></td>
                            <td><span class="badge bg-primary">{{ $roleLabels[$u->role] ?? $u->role }}</span></td>
                            <td>
                                @if ($u->signature_path)
                                    <img src="{{ route('admin.utilisateurs.signature', $u) }}" alt="signature"
                                        style="height:34px;max-width:120px;object-fit:contain;background:#fff;border:1px solid var(--line);border-radius:6px;padding:2px;">
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($u->is_active)<span class="badge bg-success">Actif</span>@else<span class="badge bg-secondary">Inactif</span>@endif
                                @if ($u->hasTwoFactor())
                                    <span class="badge" style="background:rgba(47,158,51,.12);color:#1f7a24;border:1px solid rgba(47,158,51,.3);"
                                        title="Double authentification active"><i class="fas fa-shield-halved"></i> 2FA</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap;">
                                @canany(['utilisateurs.editer', 'utilisateurs.gerer'])
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalUser"
                                        onclick="openUser('edit', this)" data-url="{{ route('admin.utilisateurs.update', $u) }}"
                                        data-nom="{{ $u->nom }}" data-prenom="{{ $u->prenom }}" data-email="{{ $u->email }}"
                                        data-role="{{ $u->role }}" data-roles="{{ $u->getRoleNames()->implode(',') }}"
                                        data-active="{{ $u->is_active ? 1 : 0 }}">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                @endcanany
                                @if ($u->hasTwoFactor() && $u->id !== auth()->id() && auth()->user()->canAny(['utilisateurs.editer', 'utilisateurs.gerer']))
                                    <form method="POST" action="{{ route('admin.utilisateurs.2fa-reset', $u) }}" class="d-inline"
                                        onsubmit="return confirm('Réinitialiser la 2FA de {{ $u->staff_name }} ? Le compte se connectera à nouveau sans code.')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning" title="Réinitialiser la 2FA (téléphone perdu)">
                                            <i class="fas fa-shield-halved"></i>
                                        </button>
                                    </form>
                                @endif
                                @if ($u->id !== auth()->id() && auth()->user()->canAny(['utilisateurs.supprimer', 'utilisateurs.gerer']))
                                    <form method="POST" action="{{ route('admin.utilisateurs.destroy', $u) }}" class="d-inline" onsubmit="return confirm('Supprimer ce compte ?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5"><i class="fas fa-users-slash d-block mb-2" style="font-size:1.8rem;opacity:.3;"></i>Aucun compte personnel.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">{{ $users->links('pagination::bootstrap-5') }}</div>

    {{-- ═══ Modal Utilisateur ═══ --}}
    <div class="modal fade" id="modalUser" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formUser" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="_method" id="u_method" value="POST">
                    {{-- L'attribut action ne se poste pas : sans ce témoin, une
                         modification refusée rouvrirait la fenêtre en création. --}}
                    <input type="hidden" name="_cible" id="u_cible" value="{{ old('_cible') }}">
                    <div class="modal-header"><h6 class="modal-title" id="uTitle">Nouvel utilisateur</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        @if ($errors->any())
                            <div class="alert alert-danger py-2 small">
                                <i class="fas fa-circle-exclamation"></i>
                                <strong>Enregistrement refusé.</strong>
                                <ul class="mb-0 mt-1 ps-3">
                                    @foreach ($errors->all() as $erreur)
                                        <li>{{ $erreur }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label">Prénom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="prenom" value="{{ old('prenom') }}" required></div>
                            <div class="col-6"><label class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="nom" value="{{ old('nom') }}" required></div>
                        </div>
                        <div class="mt-3" id="u_ident_wrap"><label class="form-label">Identifiant de connexion <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="numero_identifiant" placeholder="Ex : DAAS01"
                                value="{{ old('numero_identifiant') }}"></div>
                        <div class="mt-3"><label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" value="{{ old('email') }}" required></div>
                        <div class="mt-3">
                            <label class="form-label">Rôles <span class="text-danger">*</span>
                                <small class="text-muted">(plusieurs possibles — le premier coché sert de rôle principal)</small>
                            </label>
                            <div class="border rounded p-2" style="max-height:210px;overflow-y:auto;">
                                @foreach ($roles as $r)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="roles[]"
                                            value="{{ $r->name }}" id="ur_{{ $r->id }}" data-role-case
                                            @checked(in_array($r->name, old('roles', [])))>
                                        <label class="form-check-label small" for="ur_{{ $r->id }}">
                                            {{ $roleLabels[$r->name] ?? $r->name }}
                                            <code class="text-muted" style="font-size:10.5px;">{{ $r->name }}</code>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <small class="text-muted">
                                Cumuler les rôles additionne leurs permissions — utile pour un agent
                                qui traite plusieurs procédures.
                            </small>
                        </div>
                        <div class="mt-3"><label class="form-label">Mot de passe <span id="u_pwd_req" class="text-danger">*</span>
                                <small class="text-muted" id="u_pwd_hint" style="display:none;">(laisser vide = inchangé)</small></label>
                            <input type="password" class="form-control" name="password" minlength="8"></div>
                        <div class="mt-3"><label class="form-label">Signature <small class="text-muted">(image PNG/JPG, fond transparent conseillé)</small></label>
                            <input type="file" class="form-control" name="signature" accept=".png,.jpg,.jpeg"></div>
                        <div class="form-check form-switch mt-3" id="u_active_wrap" style="display:none;">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="u_active"
                                {{ $errors->any() ? (old('is_active') ? 'checked' : '') : 'checked' }}>
                            <label class="form-check-label" for="u_active">Compte actif</label></div>
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
        const U_STORE = "{{ route('admin.utilisateurs.store') }}";

        /* Bascule création / modification. Ne touche PAS aux valeurs : une
           réouverture après refus doit conserver ce que l'usager avait tapé. */
        function configurerFormUser(isEdit, action) {
            const f = document.getElementById('formUser');
            document.getElementById('uTitle').textContent = isEdit ? "Modifier l'utilisateur" : 'Nouvel utilisateur';
            document.getElementById('u_method').value = isEdit ? 'PUT' : 'POST';
            document.getElementById('u_active_wrap').style.display = isEdit ? '' : 'none';
            document.getElementById('u_ident_wrap').style.display = isEdit ? 'none' : '';
            document.getElementById('u_pwd_req').style.display = isEdit ? 'none' : '';
            document.getElementById('u_pwd_hint').style.display = isEdit ? '' : 'none';
            f.numero_identifiant.required = !isEdit;
            f.password.required = !isEdit;
            f.action = action;
            document.getElementById('u_cible').value = isEdit ? action : '';
        }

        function openUser(mode, el) {
            const f = document.getElementById('formUser');
            f.reset();
            const isEdit = mode === 'edit';
            configurerFormUser(isEdit, isEdit ? el.dataset.url : U_STORE);
            if (isEdit) {
                f.prenom.value = el.dataset.prenom || '';
                f.nom.value = el.dataset.nom || '';
                f.email.value = el.dataset.email || '';
                const actuels = (el.dataset.roles || el.dataset.role || '').split(',').filter(Boolean);
                f.querySelectorAll('[data-role-case]').forEach(c => { c.checked = actuels.includes(c.value); });
                document.getElementById('u_active').checked = el.dataset.active === '1';
            } else {
                f.querySelectorAll('[data-role-case]').forEach(c => { c.checked = false; });
            }
        }

        @if ($errors->any())
            /* Refus de validation : la fenêtre s'était refermée et la saisie
               partait avec elle. On la rouvre sur les valeurs conservées.
               Le mot de passe, lui, n'est jamais réinjecté dans la page. */
            document.addEventListener('DOMContentLoaded', function() {
                var edition = @js(old('_method') === 'PUT');
                configurerFormUser(edition, @js(old('_cible')) || U_STORE);
                new bootstrap.Modal(document.getElementById('modalUser')).show();
            });
        @endif
    </script>
@endsection
