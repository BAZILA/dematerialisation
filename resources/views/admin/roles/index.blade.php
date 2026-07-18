@extends('layouts.app')
@section('page-title', 'Rôles & permissions')
@section('page-subtitle', 'Contrôlez ce que chaque rôle peut faire sur la plateforme')

@section('content')
    @php
        $roleLabels = [
            'super_admin' => 'Super administrateur', 'admin' => 'Administrateur',
            'gestionnaire_academique' => 'Gestionnaire académique', 'chef_scolarite' => 'Chef de section scolarité',
            'directeur_daas' => 'Directeur DAAS', 'directeur_adjoint_daas' => 'Directeur adjoint DAAS',
            'agent_depot' => 'Agent de dépôt', 'agent_carte' => 'Agent traitement carte',
            'agent_attestation' => 'Agent attestation', 'agent_releve' => 'Agent relevé de notes',
            'agent_homologation' => 'Agent homologation', 'agent_diplome' => 'Agent diplômes',
            'agent_candidature' => 'Agent candidatures', 'agent_financier' => 'Agent financier',
            'auditeur' => 'Auditeur', 'archiviste' => 'Archiviste',
            'etudiant' => 'Étudiant', 'candidat' => 'Candidat',
        ];
        $permLabels = [
            'acceder-backoffice'   => 'Accéder au back-office',
            'depot.traiter'        => 'Traiter les dépôts',
            'attestation.traiter'  => 'Traiter les attestations',
            'releve.traiter'       => 'Traiter les relevés de notes',
            'diplome.traiter'      => 'Traiter les diplômes',
            'duplicat.traiter'     => 'Traiter les duplicatas de carte',
            'homologation.traiter' => 'Traiter les homologations',
            'candidature.traiter'  => 'Traiter les candidatures',
            'demandes.controler'   => 'Contrôler / superviser les demandes',
            'documents.valider'    => 'Valider les documents',
            'documents.signer'     => 'Signer officiellement',
            'paiements.confirmer'  => 'Confirmer les paiements',
            'dossiers.consulter'   => 'Consulter les dossiers étudiants',
            'identites.importer'   => 'Importer / exporter les identités',
            'academique.gerer'     => 'Gérer le référentiel académique',
            'annees.gerer'         => 'Gérer les années académiques',
            'parametres.gerer'     => 'Gérer les paramètres plateforme',
            'utilisateurs.gerer'   => 'Gérer les utilisateurs',
            'roles.gerer'          => 'Gérer les rôles & permissions',
            'perimetres.affecter'  => 'Affecter les périmètres agents',
            'audit.consulter'      => "Consulter le journal d'audit",
            'archives.consulter'   => 'Consulter les archives numériques',
        ];
        // Libellé automatique pour les procédures créées ensuite (ex. bourse.traiter)
        $procNoms = \App\Models\Procedure::pluck('nom', 'code');
        foreach ($permissions as $perm) {
            if (!isset($permLabels[$perm->name]) && str_ends_with($perm->name, '.traiter')) {
                $code = str_replace('.traiter', '', $perm->name);
                $permLabels[$perm->name] = 'Traiter : ' . ($procNoms[$code] ?? $code);
            }
        }
        // Toutes les permissions « .traiter » (dont les procédures custom) vont dans « Traitement »
        $traitement = $permissions->pluck('name')->filter(fn ($n) => str_ends_with($n, '.traiter'))->values()->all();
        $groups = [
            'Accès'          => ['acceder-backoffice'],
            'Traitement des demandes' => $traitement,
            'Supervision'    => ['demandes.controler', 'documents.valider', 'documents.signer', 'paiements.confirmer', 'dossiers.consulter', 'audit.consulter', 'archives.consulter'],
            'Administration' => ['identites.importer', 'academique.gerer', 'annees.gerer', 'utilisateurs.gerer', 'perimetres.affecter', 'roles.gerer', 'parametres.gerer'],
        ];
        $known = collect($groups)->flatten()->all();
        $autres = $permissions->pluck('name')->diff($known)->values()->all();
        if ($autres) { $groups['Autres'] = $autres; }
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="text-muted small">
            <i class="fas fa-circle-info"></i> Cliquez sur un rôle pour déplier ses permissions.
            <strong>super_admin</strong> possède toujours tout ; <strong>etudiant</strong>/<strong>candidat</strong> sont des usagers (pas de back-office).
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRole">
            <i class="fas fa-plus"></i> Nouveau rôle
        </button>
    </div>

    @foreach ($roles as $role)
        @php
            $isSuper = $role->name === 'super_admin';
            $isUsager = in_array($role->name, ['etudiant', 'candidat']);
            $locked = $isSuper || $isUsager;
            $rolePerms = $role->permissions->pluck('name')->all();
        @endphp
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div data-bs-toggle="collapse" data-bs-target="#role-{{ $role->id }}" role="button" style="cursor:pointer;flex:1;min-width:0;">
                    <i class="fas fa-chevron-down me-1" style="font-size:11px;color:var(--t3);"></i>
                    <i class="fas {{ $isSuper ? 'fa-crown' : ($isUsager ? 'fa-user' : 'fa-user-shield') }}" style="color:var(--brand);"></i>
                    <strong class="ms-1">{{ $roleLabels[$role->name] ?? $role->name }}</strong>
                    <code class="ms-1">{{ $role->name }}</code>
                    <span class="badge bg-secondary ms-1">{{ $role->users_count }} utilisateur(s)</span>
                    <span class="badge bg-info ms-1">{{ $isSuper ? 'toutes' : count($rolePerms) }} permission(s)</span>
                    @if ($locked)<span class="badge bg-warning ms-1"><i class="fas fa-lock"></i> Système</span>@endif
                </div>
                @if (!in_array($role->name, $systemRoles) && $role->users_count === 0)
                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Supprimer le rôle « {{ $role->name }} » ?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                    </form>
                @endif
            </div>
            <div class="collapse" id="role-{{ $role->id }}">
                <div class="card-body">
                    @if ($isSuper)
                        <div class="alert alert-info mb-0"><i class="fas fa-crown"></i> Ce rôle possède automatiquement <strong>toutes les permissions</strong> — non modifiable.</div>
                    @else
                        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                            @csrf @method('PUT')
                            <div class="row g-4">
                                @foreach ($groups as $gLabel => $gPerms)
                                    <div class="col-md-6 col-lg-3">
                                        <div class="fw-bold small text-uppercase mb-2" style="color:var(--t3);letter-spacing:.05em;">{{ $gLabel }}</div>
                                        @foreach ($gPerms as $p)
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="perms[{{ $p }}]"
                                                    id="p-{{ $role->id }}-{{ $loop->parent->index }}-{{ $loop->index }}"
                                                    {{ in_array($p, $rolePerms) ? 'checked' : '' }}>
                                                <label class="form-check-label small" for="p-{{ $role->id }}-{{ $loop->parent->index }}-{{ $loop->index }}">
                                                    {{ $permLabels[$p] ?? $p }}
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-3 pt-3 d-flex justify-content-end" style="border-top:1px solid var(--line);">
                                <button type="submit" class="btn btn-primary btn-sm" data-loading-text="Enregistrement…">
                                    <i class="fas fa-save"></i> Enregistrer les permissions
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    {{-- ═══ Modal nouveau rôle ═══ --}}
    <div class="modal fade" id="modalRole" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.roles.store') }}">
                    @csrf
                    <div class="modal-header"><h6 class="modal-title">Nouveau rôle</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <label class="form-label">Nom technique <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" placeholder="ex : agent_archives" pattern="[a-z0-9_]+" required>
                        <small class="text-muted d-block mt-1">Minuscules et underscores uniquement. Vous cocherez ensuite ses permissions.</small>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary btn-sm" data-loading-text="Création…"><i class="fas fa-plus"></i> Créer</button></div>
                </form>
            </div>
        </div>
    </div>
@endsection
