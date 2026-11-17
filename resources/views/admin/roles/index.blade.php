@extends('layouts.app')
@section('page-title', 'Rôles & permissions')
@section('page-subtitle', 'Contrôlez ce que chaque rôle peut faire sur la plateforme')

@section('css')
    <style>
        /* ── Cartes de rôle ── */
        .role-card { background:var(--surface); border:1px solid var(--line); border-radius:16px;
            box-shadow:var(--shadow-sm); margin-bottom:16px; overflow:hidden; transition:box-shadow var(--dur) var(--ease); }
        .role-card:hover { box-shadow:var(--shadow); }
        .role-head { display:flex; align-items:center; gap:14px; padding:16px 18px; cursor:pointer; }
        .role-ic { width:46px; height:46px; border-radius:13px; flex-shrink:0;
            display:flex; align-items:center; justify-content:center; font-size:1.1rem;
            background:linear-gradient(135deg, var(--brand), #2F9E33); color:#fff; }
        .role-ic.locked { background:linear-gradient(135deg, #f59e0b, #d97706); }
        .role-ic.usager { background:var(--surface-2); color:var(--t2); border:1px solid var(--line); }
        .role-title { font-weight:700; color:var(--t1); font-size:15px; line-height:1.2; }
        .role-code { font-size:11.5px; color:var(--t3); font-family:monospace; }
        .role-chips { display:flex; gap:6px; flex-wrap:wrap; margin-left:auto; align-items:center; }
        .role-chip { font-size:11px; font-weight:600; padding:4px 10px; border-radius:100px;
            background:var(--surface-2); color:var(--t2); border:1px solid var(--line); white-space:nowrap; }
        .role-chip.brand { background:var(--em-dim); color:var(--brand); border-color:transparent; }
        .role-chip.warn { background:rgba(245,158,11,.12); color:#b45309; border-color:rgba(245,158,11,.3); }
        .role-caret { color:var(--t3); font-size:12px; transition:transform .2s; }
        .role-head[aria-expanded="true"] .role-caret { transform:rotate(180deg); }
        .role-body { border-top:1px solid var(--line); padding:20px 18px; background:var(--surface-2); }

        /* ── Pills de permission (cases à cocher habillées) ── */
        .perm-sect { font-size:11.5px; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
            color:var(--t3); margin:16px 0 9px; }
        .perm-sect:first-child { margin-top:0; }
        .perm-pills { display:flex; flex-wrap:wrap; gap:8px; }
        .perm-pill { position:relative; cursor:pointer; margin:0; }
        .perm-pill input { position:absolute; opacity:0; pointer-events:none; }
        .perm-pill span { display:inline-flex; align-items:center; gap:6px; padding:7px 13px;
            border-radius:100px; font-size:12.5px; font-weight:600; border:1px solid var(--line-mid);
            background:var(--surface); color:var(--t2); transition:all .15s var(--ease); user-select:none; }
        .perm-pill span::before { content:'\f067'; font-family:'Font Awesome 6 Free'; font-weight:900; font-size:9px; opacity:.55; }
        .perm-pill input:checked + span { background:var(--brand); border-color:var(--brand); color:#fff;
            box-shadow:0 2px 8px rgba(26,86,168,.3); }
        .perm-pill input:checked + span::before { content:'\f00c'; opacity:1; }
        .perm-pill:hover span { border-color:var(--brand); }

        /* ── Matrice Administration ── */
        .perm-matrix { background:var(--surface); border:1px solid var(--line); border-radius:14px; overflow:hidden; }
        .perm-matrix table { margin:0; }
        .perm-matrix thead th { background:var(--surface-2); font-size:11px; text-transform:uppercase;
            letter-spacing:.05em; color:var(--t3); border-bottom:1px solid var(--line) !important; padding:10px 12px; }
        .perm-matrix tbody td { padding:9px 12px; border-color:var(--line) !important; }
        .perm-matrix tbody tr:hover { background:var(--em-dim); }
        .perm-matrix .form-check-input { width:19px; height:19px; cursor:pointer; margin:0; }
        .perm-matrix .col-tout { background:rgba(47,158,51,.06); }
        .perm-matrix .form-check-input:checked { background-color:var(--brand); border-color:var(--brand); }
        .m-label { font-size:13px; color:var(--t1); font-weight:600; }

        .role-save { position:sticky; bottom:12px; display:flex; justify-content:flex-end; margin-top:16px; }
        .role-save .btn { box-shadow:var(--shadow); }
    </style>
@endsection

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
            'demandes.rouvrir'     => 'Annuler une validation / rouvrir un dossier',
            'pieces.commune_refuser' => 'Refuser une pièce commune (impacte tous les parcours)',
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
            'statistiques.consulter' => 'Consulter les statistiques',
        ];
        // Libellés automatiques des permissions fines (module.action)
        $actionLabels = ['voir' => 'Voir', 'creer' => 'Créer', 'editer' => 'Éditer', 'supprimer' => 'Supprimer'];
        foreach ($permissions as $perm) {
            if (!isset($permLabels[$perm->name]) && preg_match('/^(\w+)\.(voir|creer|editer|supprimer)$/', $perm->name, $m)) {
                $permLabels[$perm->name] = $actionLabels[$m[2]] . ' : ' . $m[1];
            }
        }

        /* ── Matrice Administration : module × action ───────────────────
           « Tout gérer » = l'ancienne permission globale du module ; chaque
           action fine suffit seule pour la route correspondante. */
        $matrix = [
            ['label' => 'Référentiel académique', 'voir' => 'academique.voir', 'creer' => 'academique.creer', 'editer' => 'academique.editer', 'supprimer' => 'academique.supprimer', 'tout' => 'academique.gerer'],
            ['label' => 'Années académiques',     'voir' => 'annees.voir', 'creer' => 'annees.creer', 'editer' => 'annees.editer', 'supprimer' => 'annees.supprimer', 'tout' => 'annees.gerer'],
            ['label' => 'Utilisateurs (personnel)', 'voir' => 'utilisateurs.voir', 'creer' => 'utilisateurs.creer', 'editer' => 'utilisateurs.editer', 'supprimer' => 'utilisateurs.supprimer', 'tout' => 'utilisateurs.gerer'],
            ['label' => 'Rôles & permissions',    'voir' => 'roles.voir', 'creer' => 'roles.creer', 'editer' => 'roles.editer', 'supprimer' => 'roles.supprimer', 'tout' => 'roles.gerer'],
            ['label' => 'Procédures',             'voir' => 'procedures.voir', 'creer' => 'procedures.creer', 'editer' => 'procedures.editer', 'supprimer' => 'procedures.supprimer', 'tout' => null],
            ['label' => 'Types de documents',     'voir' => 'types.voir', 'creer' => 'types.creer', 'editer' => 'types.editer', 'supprimer' => 'types.supprimer', 'tout' => null],
            ['label' => 'Périmètres agents',      'voir' => 'perimetres.voir', 'creer' => null, 'editer' => null, 'supprimer' => null, 'tout' => 'perimetres.affecter'],
            ['label' => 'Identités (référentiel)', 'voir' => 'identites.voir', 'creer' => null, 'editer' => null, 'supprimer' => null, 'tout' => 'identites.importer'],
            ['label' => 'Paramètres plateforme (couvre aussi Procédures & Types)', 'voir' => null, 'creer' => null, 'editer' => null, 'supprimer' => null, 'tout' => 'parametres.gerer'],
        ];
        $matrixPerms = collect($matrix)->flatMap(fn ($r) => [$r['voir'], $r['creer'], $r['editer'], $r['supprimer'], $r['tout']])->filter()->all();
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
            'Supervision'    => ['demandes.controler', 'demandes.rouvrir', 'documents.valider', 'documents.signer', 'paiements.confirmer', 'dossiers.consulter', 'audit.consulter', 'archives.consulter', 'statistiques.consulter'],
        ];
        $known = array_merge(collect($groups)->flatten()->all(), $matrixPerms);
        $autres = $permissions->pluck('name')->diff($known)->values()->all();
        if ($autres) { $groups['Autres'] = $autres; }

        $peutEditer = auth()->user()->canAny(['roles.editer', 'roles.gerer']);
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="text-muted small">
            <i class="fas fa-circle-info"></i> Cliquez sur un rôle pour déplier ses permissions.
            <strong>super_admin</strong> possède toujours tout ; <strong>etudiant</strong>/<strong>candidat</strong> sont des usagers (pas de back-office).
        </div>
        @canany(['roles.creer', 'roles.gerer'])
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalRole">
                <i class="fas fa-plus"></i> Nouveau rôle
            </button>
        @endcanany
    </div>

    @foreach ($roles as $role)
        @php
            $isSuper = $role->name === 'super_admin';
            $isUsager = in_array($role->name, ['etudiant', 'candidat']);
            $locked = $isSuper || $isUsager;
            $rolePerms = $role->permissions->pluck('name')->all();
        @endphp
        <div class="role-card">
            <div class="role-head" data-bs-toggle="collapse" data-bs-target="#role-{{ $role->id }}" aria-expanded="false">
                <div class="role-ic {{ $isSuper ? 'locked' : ($isUsager ? 'usager' : '') }}">
                    <i class="fas {{ $isSuper ? 'fa-crown' : ($isUsager ? 'fa-user' : 'fa-user-shield') }}"></i>
                </div>
                <div style="min-width:0;">
                    <div class="role-title">{{ $roleLabels[$role->name] ?? $role->name }}</div>
                    <div class="role-code">{{ $role->name }}</div>
                </div>
                <div class="role-chips">
                    <span class="role-chip"><i class="fas fa-users" style="font-size:10px;"></i> {{ $role->users_count }}</span>
                    <span class="role-chip brand"><i class="fas fa-key" style="font-size:10px;"></i> {{ $isSuper ? 'toutes' : count($rolePerms) }} permission(s)</span>
                    @if ($locked)<span class="role-chip warn"><i class="fas fa-lock" style="font-size:10px;"></i> Système</span>@endif
                    @if (!in_array($role->name, $systemRoles) && $role->users_count === 0 && auth()->user()->canAny(['roles.supprimer', 'roles.gerer']))
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}"
                            onsubmit="return confirm('Supprimer le rôle « {{ $role->name }} » ?')" onclick="event.stopPropagation()">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" title="Supprimer ce rôle"><i class="fas fa-trash"></i></button>
                        </form>
                    @endif
                    <i class="fas fa-chevron-down role-caret ms-1"></i>
                </div>
            </div>
            <div class="collapse" id="role-{{ $role->id }}">
                <div class="role-body">
                    @if ($isSuper)
                        <div class="alert alert-info mb-0"><i class="fas fa-crown"></i> Ce rôle possède automatiquement <strong>toutes les permissions</strong> — non modifiable.</div>
                    @else
                        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
                            @csrf @method('PUT')

                            @foreach ($groups as $gLabel => $gPerms)
                                <div class="perm-sect">{{ $gLabel }}</div>
                                <div class="perm-pills">
                                    @foreach ($gPerms as $p)
                                        <label class="perm-pill">
                                            <input type="checkbox" name="perms[{{ $p }}]"
                                                {{ in_array($p, $rolePerms) ? 'checked' : '' }} {{ $peutEditer ? '' : 'disabled' }}>
                                            <span>{{ $permLabels[$p] ?? $p }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endforeach

                            <div class="perm-sect">Administration — par action</div>
                            <p class="small mb-2" style="color:var(--t3);">
                                Cochez des actions précises, ou <strong>« Tout gérer »</strong> pour couvrir l'ensemble du module.
                            </p>
                            <div class="perm-matrix table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th style="min-width:220px;">Module</th>
                                            <th class="text-center">Voir</th>
                                            <th class="text-center">Créer</th>
                                            <th class="text-center">Éditer</th>
                                            <th class="text-center">Supprimer</th>
                                            <th class="text-center col-tout">Tout gérer</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($matrix as $row)
                                            <tr>
                                                <td class="m-label">{{ $row['label'] }}</td>
                                                @foreach (['voir', 'creer', 'editer', 'supprimer', 'tout'] as $col)
                                                    <td class="text-center {{ $col === 'tout' ? 'col-tout' : '' }}">
                                                        @if ($row[$col])
                                                            <input class="form-check-input" type="checkbox"
                                                                name="perms[{{ $row[$col] }}]" title="{{ $row[$col] }}"
                                                                {{ in_array($row[$col], $rolePerms) ? 'checked' : '' }} {{ $peutEditer ? '' : 'disabled' }}>
                                                        @else
                                                            <span style="color:var(--t3);opacity:.3;">—</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if ($peutEditer)
                                <div class="role-save">
                                    <button type="submit" class="btn btn-primary" data-loading-text="Enregistrement…">
                                        <i class="fas fa-save"></i> Enregistrer les permissions
                                    </button>
                                </div>
                            @endif
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
