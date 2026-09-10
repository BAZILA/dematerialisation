@extends('layouts.app')
@section('page-title', 'Identités — Import / Export')
@section('page-subtitle', 'Référentiel des bacheliers et anciens étudiants (Excel / CSV)')

@section('content')
    @include('admin._mot_de_passe_provisoire')

    @if (session('import_errors'))
        <div class="alert alert-warning">
            <strong><i class="fas fa-triangle-exclamation"></i> Lignes ignorées ({{ count(session('import_errors')) }} affichées) :</strong>
            <ul class="mb-0 mt-2 small" style="max-height:200px;overflow:auto;">
                @foreach (session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        @php
            $blocks = [
                [
                    'titre' => 'Nouveaux bacheliers',
                    'icone' => 'fa-user-graduate',
                    'total' => $counts['bacheliers'],
                    'inscrits' => $counts['bacheliers_registered'],
                    'import' => route('admin.identites.bacheliers.import'),
                    'export' => route('admin.identites.bacheliers.export'),
                    'modele' => route('admin.identites.bacheliers.template'),
                    'colonnes' => 'nom · prenom · sexe (M/F) · date_naissance (AAAA-MM-JJ) · lieu_naissance · serie_bac · annee_bac · numero_releve · academie',
                ],
                [
                    'titre' => 'Anciens étudiants',
                    'icone' => 'fa-id-card',
                    'total' => $counts['anciens'],
                    'inscrits' => $counts['anciens_registered'],
                    'import' => route('admin.identites.anciens.import'),
                    'export' => route('admin.identites.anciens.export'),
                    'modele' => route('admin.identites.anciens.template'),
                    'colonnes' => 'numero_carte · nom · prenom · sexe (M/F) · date_naissance (AAAA-MM-JJ) · lieu_naissance · specialite · derniere_universite',
                ],
            ];
        @endphp

        @foreach ($blocks as $b)
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center gap-2">
                        <i class="fas {{ $b['icone'] }}" style="color:var(--brand);"></i>
                        <strong>{{ $b['titre'] }}</strong>
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-3 mb-3">
                            <div class="p-3 rounded flex-fill text-center" style="background:var(--surface-2);border:1px solid var(--line);">
                                <div class="fw-bold" style="font-size:1.6rem;color:var(--t1);">{{ $b['total'] }}</div>
                                <div class="small text-muted">Au total</div>
                            </div>
                            <div class="p-3 rounded flex-fill text-center" style="background:var(--surface-2);border:1px solid var(--line);">
                                <div class="fw-bold" style="font-size:1.6rem;color:var(--green);">{{ $b['inscrits'] }}</div>
                                <div class="small text-muted">Comptes créés</div>
                            </div>
                        </div>

                        {{-- Import --}}
                        <form method="POST" action="{{ $b['import'] }}" enctype="multipart/form-data">
                            @csrf
                            <label class="form-label">Importer un fichier Excel / CSV</label>
                            <div class="input-group">
                                <input type="file" class="form-control" name="file" accept=".xlsx,.xls,.csv" required>
                                <button type="submit" class="btn btn-primary" data-loading-text="Import…">
                                    <i class="fas fa-file-import"></i> Importer
                                </button>
                            </div>
                            <div class="small text-muted mt-2">
                                <i class="fas fa-table-columns"></i> Colonnes attendues (1ʳᵉ ligne = en-têtes) :
                                <code style="font-size:11px;">{{ $b['colonnes'] }}</code>
                            </div>
                        </form>

                        <hr>

                        {{-- Export & modèle --}}
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ $b['export'] }}" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-file-export"></i> Exporter (.xlsx)
                            </a>
                            <a href="{{ $b['modele'] }}" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-download"></i> Télécharger le modèle vide
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="alert alert-info mt-4" style="font-size:12.5px;">
        <i class="fas fa-circle-info"></i>
        <strong>Dédoublonnage.</strong> Une fiche déjà présente est <strong>mise à jour</strong>,
        une nouvelle est <strong>ajoutée</strong>.
        <ul class="mb-1 mt-1 ps-3">
            <li><strong>Bacheliers</strong> — reconnus par <code>numero_suivi</code>,
                <code>numero_releve</code> ou <code>numero_table</code> : le premier de ces
                numéros qui correspond à une fiche existante suffit. Sans aucun de ces
                numéros, la fiche est reconnue sur nom + prénom + date de naissance.</li>
            <li><strong>Anciens étudiants</strong> — reconnus par <code>numero_carte</code>,
                qui est <strong>obligatoire</strong> : une ligne sans ce numéro est ignorée.</li>
        </ul>
        Une colonne absente du fichier <strong>n'efface rien</strong> : les valeurs déjà
        enregistrées sont conservées. Les lignes incomplètes sont ignorées et listées.
    </div>

    {{-- ═══ Référentiel chargé : consultation + ajout manuel ═══ --}}
    @php $tab = request('tab', 'bacheliers'); @endphp

    <ul class="nav nav-tabs mt-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'bacheliers' ? 'active' : '' }}" data-bs-toggle="tab" href="#lst-bacheliers">
                <i class="fas fa-user-graduate"></i> Bacheliers chargés
                <span class="badge bg-primary ms-1">{{ $bacheliers->total() }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab === 'anciens' ? 'active' : '' }}" data-bs-toggle="tab" href="#lst-anciens">
                <i class="fas fa-id-card"></i> Anciens étudiants chargés
                <span class="badge bg-secondary ms-1">{{ $anciens->total() }}</span>
            </a>
        </li>
    </ul>

    <div class="tab-content">
        @php
            $listes = [
                [
                    'id' => 'lst-bacheliers', 'actif' => $tab === 'bacheliers', 'items' => $bacheliers,
                    'q' => 'qb', 'etat' => 'etat_b', 'tab' => 'bacheliers',
                    'modal' => 'modalAjoutBachelier', 'titre' => 'un bachelier',
                    'cleLabel' => 'N° relevé', 'cle' => 'numero_releve',
                    'infoLabel' => 'Série / Année bac',
                    'destroy' => 'admin.identites.bacheliers.destroy',
                ],
                [
                    'id' => 'lst-anciens', 'actif' => $tab === 'anciens', 'items' => $anciens,
                    'q' => 'qa', 'etat' => 'etat_a', 'tab' => 'anciens',
                    'modal' => 'modalAjoutAncien', 'titre' => 'un ancien étudiant',
                    'cleLabel' => 'N° carte', 'cle' => 'numero_carte',
                    'infoLabel' => 'Spécialité',
                    'destroy' => 'admin.identites.anciens.destroy',
                ],
            ];
        @endphp

        @foreach ($listes as $l)
            <div class="tab-pane fade {{ $l['actif'] ? 'show active' : '' }}" id="{{ $l['id'] }}">
                <div class="card" style="border-top-left-radius:0;">
                    <div class="card-body py-3">
                        <form method="GET" class="row g-2 align-items-end">
                            <input type="hidden" name="tab" value="{{ $l['tab'] }}">
                            <div class="col-md-5">
                                <input name="{{ $l['q'] }}" class="form-control form-control-sm"
                                    placeholder="Nom, prénom, n° de suivi, {{ strtolower($l['cleLabel']) }}…"
                                    value="{{ request($l['q']) }}">
                            </div>
                            <div class="col-md-3">
                                <select name="{{ $l['etat'] }}" class="form-select form-select-sm">
                                    <option value="">Tous</option>
                                    <option value="inscrits" @selected(request($l['etat']) === 'inscrits')>Compte créé</option>
                                    <option value="sans_compte" @selected(request($l['etat']) === 'sans_compte')>Sans compte</option>
                                </select>
                            </div>
                            <div class="col-md-4 filtre-actions">
                                <button class="btn btn-primary btn-filtrer"><i class="fas fa-search"></i> Filtrer</button>
                                @can('identites.importer')
                                    <button type="button" class="btn btn-success btn-sm ms-auto"
                                        data-bs-toggle="modal" data-bs-target="#{{ $l['modal'] }}">
                                        <i class="fas fa-user-plus"></i> Ajouter manuellement
                                    </button>
                                @endcan
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>Identité</th>
                                    <th>N° de suivi</th>
                                    <th>{{ $l['cleLabel'] }}</th>
                                    <th>{{ $l['infoLabel'] }}</th>
                                    <th>Compte</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($l['items'] as $it)
                                    <tr>
                                        <td>
                                            <strong style="color:var(--t1);">{{ $it->full_name }}</strong><br>
                                            <small class="text-muted">
                                                {{ $it->sexe === 'F' ? 'Féminin' : 'Masculin' }} ·
                                                né(e) le {{ $it->date_naissance?->format('d/m/Y') ?? '—' }}
                                                à {{ $it->lieu_naissance }}
                                            </small>
                                        </td>
                                        <td>
                                            @if ($it->numero_suivi)
                                                <code class="small">{{ $it->numero_suivi }}</code>
                                            @else
                                                <span class="text-muted small">—</span>
                                            @endif
                                        </td>
                                        <td><small>{{ $it->{$l['cle']} ?? '—' }}</small></td>
                                        <td>
                                            <small>
                                                @if ($l['tab'] === 'bacheliers')
                                                    {{ $it->serie_bac ?? '—' }}{{ $it->annee_bac ? ' · ' . $it->annee_bac : '' }}
                                                @else
                                                    {{ $it->specialite ?? '—' }}
                                                @endif
                                            </small>
                                        </td>
                                        <td>
                                            @if ($it->is_registered || $it->student)
                                                <span class="badge bg-success"><i class="fas fa-user-check"></i> Créé</span>
                                                @if ($it->student?->user)
                                                    <br><code class="small">{{ $it->student->user->numero_identifiant }}</code>
                                                @endif
                                            @else
                                                <span class="badge bg-secondary">Pas encore</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @can('identites.importer')
                                                {{-- Corriger reste possible meme avec un compte : une faute
                                                     de frappe sur l'identite empeche justement d'entrer. --}}
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalFiche{{ $l['tab'] }}{{ $it->id }}"
                                                    title="Modifier la fiche">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                            @endcan

                                            {{-- Le compte d'un usager n'apparait PAS dans l'ecran
                                                 Utilisateurs, qui ne liste que le personnel. C'est
                                                 donc ici, et nulle part ailleurs, qu'on peut lui
                                                 redonner un mot de passe utilisable — un compte cree
                                                 par la connexion par code n'en a aucun. --}}
                                            @can('utilisateurs.gerer')
                                                @if ($it->student?->user)
                                                    <form method="POST" class="d-inline"
                                                        action="{{ route('admin.utilisateurs.mot-de-passe', $it->student->user) }}"
                                                        onsubmit="return confirm('Donner un mot de passe provisoire a {{ addslashes($it->full_name) }} ?');">
                                                        @csrf
                                                        <button class="btn btn-sm btn-outline-secondary"
                                                            title="Reinitialiser son mot de passe">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endcan

                                            @can('identites.importer')
                                                @if (!$it->is_registered && !$it->student)
                                                    <form method="POST" action="{{ route($l['destroy'], $it) }}" class="d-inline"
                                                        onsubmit="return confirm('Retirer {{ addslashes($it->full_name) }} du référentiel ?');">
                                                        @csrf @method('DELETE')
                                                        <button class="btn btn-sm btn-outline-danger" title="Retirer">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-muted small" title="Compte déjà créé : suppression impossible">
                                                        <i class="fas fa-lock"></i>
                                                    </span>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="fas fa-inbox d-block fs-2 mb-2"></i>
                                            Aucune identité chargée pour ce filtre.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="d-flex justify-content-center mt-3">{{ $l['items']->links('pagination::bootstrap-5') }}</div>

                @can('identites.importer')
                    @foreach ($l['items'] as $it)
                        <div class="modal fade" id="modalFiche{{ $l['tab'] }}{{ $it->id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <form method="POST"
                                        action="{{ route('admin.identites.' . $l['tab'] . '.update', $it) }}">
                                        @csrf @method('PUT')
                                        <div class="modal-header">
                                            <h6 class="modal-title">
                                                <i class="fas fa-pen"></i> {{ $it->full_name }}
                                            </h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            @include('admin.identities._fiche', [
                                                'item' => $it,
                                                'type' => $l['tab'],
                                            ])
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary"
                                                data-bs-dismiss="modal">Annuler</button>
                                            <button class="btn btn-primary">
                                                <i class="fas fa-check"></i> Enregistrer
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endcan
            </div>
        @endforeach
    </div>

    {{-- ═══ Modals d'ajout manuel ═══ --}}
    @can('identites.importer')
        {{-- Bachelier --}}
        <div class="modal fade" id="modalAjoutBachelier" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.identites.bacheliers.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h6 class="modal-title"><i class="fas fa-user-plus"></i> Ajouter un bachelier</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">
                                À utiliser quand un étudiant ne figure pas dans le fichier chargé.
                                Une fois ajouté, il pourra créer son compte et faire son dépôt.
                            </p>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input name="nom" class="form-control" value="{{ old('nom') }}" required></div>
                                <div class="col-md-6"><label class="form-label">Prénom(s) <span class="text-danger">*</span></label>
                                    <input name="prenom" class="form-control" value="{{ old('prenom') }}" required></div>
                                <div class="col-md-4"><label class="form-label">Sexe <span class="text-danger">*</span></label>
                                    <select name="sexe" class="form-select" required>
                                        <option value="">—</option>
                                        <option value="M" @selected(old('sexe') === 'M')>Masculin</option>
                                        <option value="F" @selected(old('sexe') === 'F')>Féminin</option>
                                    </select></div>
                                <div class="col-md-4"><label class="form-label">Date de naissance <span class="text-danger">*</span></label>
                                    <input type="date" name="date_naissance" class="form-control" value="{{ old('date_naissance') }}" required></div>
                                <div class="col-md-4"><label class="form-label">Lieu de naissance <span class="text-danger">*</span></label>
                                    <input name="lieu_naissance" class="form-control" value="{{ old('lieu_naissance') }}" required></div>

                                <div class="col-12"><hr class="my-1"></div>
                                <div class="col-md-6"><label class="form-label">N° de suivi</label>
                                    <input name="numero_suivi" class="form-control" value="{{ old('numero_suivi') }}"
                                        placeholder="Clé de rattachement d'une année à l'autre">
                                    <small class="text-muted">Numéro de suivi ou N° de relevé : au moins l'un des deux.</small></div>
                                <div class="col-md-6"><label class="form-label">N° de relevé</label>
                                    <input name="numero_releve" class="form-control" value="{{ old('numero_releve') }}"></div>
                                <div class="col-md-4"><label class="form-label">Série bac</label>
                                    <input name="serie_bac" class="form-control" value="{{ old('serie_bac') }}"></div>
                                <div class="col-md-4"><label class="form-label">Année bac</label>
                                    <input name="annee_bac" class="form-control" value="{{ old('annee_bac') }}"></div>
                                <div class="col-md-4"><label class="form-label">N° de table</label>
                                    <input name="numero_table" class="form-control" value="{{ old('numero_table') }}"></div>
                                <div class="col-md-4"><label class="form-label">Académie</label>
                                    <input name="academie" class="form-control" value="{{ old('academie') }}"></div>
                                <div class="col-md-4"><label class="form-label">Téléphone</label>
                                    <input name="telephone" class="form-control" value="{{ old('telephone') }}"></div>
                                <div class="col-md-4"><label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="{{ old('email') }}"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button class="btn btn-primary"><i class="fas fa-check"></i> Ajouter au référentiel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Ancien étudiant --}}
        <div class="modal fade" id="modalAjoutAncien" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.identites.anciens.store') }}">
                        @csrf
                        <div class="modal-header">
                            <h6 class="modal-title"><i class="fas fa-user-plus"></i> Ajouter un ancien étudiant</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">
                                À utiliser quand un ancien étudiant ne figure pas dans le fichier chargé.
                            </p>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Nom <span class="text-danger">*</span></label>
                                    <input name="nom" class="form-control" value="{{ old('nom') }}" required></div>
                                <div class="col-md-6"><label class="form-label">Prénom(s) <span class="text-danger">*</span></label>
                                    <input name="prenom" class="form-control" value="{{ old('prenom') }}" required></div>
                                <div class="col-md-4"><label class="form-label">Sexe <span class="text-danger">*</span></label>
                                    <select name="sexe" class="form-select" required>
                                        <option value="">—</option>
                                        <option value="M" @selected(old('sexe') === 'M')>Masculin</option>
                                        <option value="F" @selected(old('sexe') === 'F')>Féminin</option>
                                    </select></div>
                                <div class="col-md-4"><label class="form-label">Date de naissance <span class="text-danger">*</span></label>
                                    <input type="date" name="date_naissance" class="form-control" value="{{ old('date_naissance') }}" required></div>
                                <div class="col-md-4"><label class="form-label">Lieu de naissance <span class="text-danger">*</span></label>
                                    <input name="lieu_naissance" class="form-control" value="{{ old('lieu_naissance') }}" required></div>

                                <div class="col-12"><hr class="my-1"></div>
                                <div class="col-md-6"><label class="form-label">N° de carte <span class="text-danger">*</span></label>
                                    <input name="numero_carte" class="form-control" value="{{ old('numero_carte') }}" required></div>
                                <div class="col-md-6"><label class="form-label">N° de suivi</label>
                                    <input name="numero_suivi" class="form-control" value="{{ old('numero_suivi') }}"
                                        placeholder="Reprendre celui du bachelier s'il en avait un"></div>
                                <div class="col-md-6"><label class="form-label">Spécialité</label>
                                    <input name="specialite" class="form-control" value="{{ old('specialite') }}"></div>
                                <div class="col-md-6"><label class="form-label">Dernière université</label>
                                    <input name="derniere_universite" class="form-control" value="{{ old('derniere_universite') }}"></div>
                                <div class="col-md-4"><label class="form-label">Téléphone</label>
                                    <input name="telephone" class="form-control" value="{{ old('telephone') }}"></div>
                                <div class="col-md-4"><label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="{{ old('email') }}"></div>
                                <div class="col-md-4"><label class="form-label">Adresse</label>
                                    <input name="adresse" class="form-control" value="{{ old('adresse') }}"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button class="btn btn-primary"><i class="fas fa-check"></i> Ajouter au référentiel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        {{-- Une saisie refusée rouvre son formulaire avec les valeurs et l'erreur --}}
        @if ($errors->any() && (old('nom') || old('numero_carte')))
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const id = @json(old('numero_carte') !== null ? 'modalAjoutAncien' : 'modalAjoutBachelier');
                    const el = document.getElementById(id);
                    if (el && window.bootstrap) new bootstrap.Modal(el).show();
                });
            </script>
        @endif
    @endcan
@endsection
