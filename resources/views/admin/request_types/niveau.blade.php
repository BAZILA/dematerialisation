@extends('layouts.app')

@section('page-title', $info['label'])
@section('page-subtitle', 'Procédures et pièces attendues pour ce niveau')

@section('css')
    <style>
        .niv-tabs { border-bottom: 2px solid var(--line); gap: 2px; flex-wrap: wrap; }
        .niv-tabs .nav-link {
            border: 0; border-bottom: 3px solid transparent; border-radius: 0;
            padding: 10px 16px; font-size: 13.5px; font-weight: 600; color: var(--t2);
            display: flex; align-items: center; gap: 8px;
        }
        .niv-tabs .nav-link:hover { color: var(--t1); background: var(--bg2); }
        .niv-tabs .nav-link.active { color: var(--brand); border-bottom-color: var(--brand); background: transparent; }
        .pastille-etat { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .pe-ouverte { background: #2F9E33; }
        .pe-fermee { background: #B4BCC8; }

        .panneau-ouverture { background: var(--bg2); border: 1px solid var(--line); border-radius: 12px; padding: 16px; margin-bottom: 18px; }
        .portee-pastille { font-size: 10.5px; font-weight: 600; padding: 2px 8px; border-radius: 20px; white-space: nowrap; display: inline-block; margin: 1px 0; }
        .p-tous { background: #EEF2F7; color: #45556B; }
        .p-niveau { background: #E8F0FA; color: #1A56A8; }
        .p-parcours { background: #E7F5E8; color: #2F9E33; }
        .regle-ligne { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-top: 1px dashed var(--line); font-size: 13px; flex-wrap: wrap; }
    </style>
@endsection

@section('content')

    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <a href="{{ route('admin.request-types.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Tous les niveaux
        </a>
        <span class="text-muted small">
            {{ $parcoursDuNiveau->count() }} parcours actif{{ $parcoursDuNiveau->count() > 1 ? 's' : '' }}
            @if ($annee) · Année {{ $annee->libelle }} @endif
        </span>
    </div>

    @if ($parcoursDuNiveau->isEmpty())
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation"></i>
            Aucun parcours actif à ce niveau. Les états affichés reflètent la règle générale,
            mais aucune règle posée ici ne s'appliquera tant qu'un parcours n'y est pas rattaché.
        </div>
    @endif

    @php $premier = $procedures->first(); @endphp

    {{-- Onglets : une procédure par onglet --}}
    <ul class="nav niv-tabs mb-3" role="tablist">
        @foreach ($procedures as $proc)
            @php $etat = $ouvertures[$proc->code] ?? ['ouverte' => false]; @endphp
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $proc->id === $premier?->id ? 'active' : '' }}"
                    data-bs-toggle="tab" data-bs-target="#onglet-{{ $proc->code }}"
                    type="button" role="tab">
                    <span class="pastille-etat {{ $etat['ouverte'] ? 'pe-ouverte' : 'pe-fermee' }}"></span>
                    {{ $proc->nom }}
                    <span class="badge bg-light text-dark">{{ ($types[$proc->code] ?? collect())->count() }}</span>
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        @foreach ($procedures as $proc)
            @php
                $etat = $ouvertures[$proc->code] ?? ['ouverte' => false, 'generale' => null, 'ciblees' => collect(), 'heritee' => true];
                $liste = $types[$proc->code] ?? collect();
            @endphp

            <div class="tab-pane fade {{ $proc->id === $premier?->id ? 'show active' : '' }}"
                id="onglet-{{ $proc->code }}" role="tabpanel">

                {{-- ═══ Réglage d'ouverture, propre à cette procédure ═══ --}}
                <div class="panneau-ouverture">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div>
                            <div class="fw-semibold" style="color:var(--t1);">
                                <i class="fas fa-lock-open"></i> Ouverture pour {{ $info['label'] }}
                            </div>
                            <div class="small mt-1" style="color:var(--t2);">
                                @if ($etat['ouverte'])
                                    <span class="badge bg-success">Ouverte</span>
                                @else
                                    <span class="badge bg-secondary">Fermée</span>
                                @endif

                                @if ($etat['heritee'])
                                    <span class="ms-1">
                                        héritée de la règle générale
                                        @if ($etat['generale'])
                                            @if ($etat['generale']->date_debut || $etat['generale']->date_fin)
                                                ({{ $etat['generale']->date_debut?->format('d/m/Y') ?? '—' }}
                                                → {{ $etat['generale']->date_fin?->format('d/m/Y') ?? '—' }})
                                            @endif
                                        @else
                                            — aucune règle générale définie
                                        @endif
                                    </span>
                                @else
                                    <span class="ms-1">réglée spécifiquement ci-dessous</span>
                                @endif
                            </div>
                        </div>

                        @can('parametres.gerer')
                            <div class="ms-auto d-flex gap-2">
                                <button class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal" data-bs-target="#modalOuverture"
                                    onclick="reglerOuverture(this)"
                                    data-operation="{{ $proc->code }}" data-label="{{ $proc->nom }}">
                                    <i class="fas fa-sliders"></i> Régler l'ouverture
                                </button>
                                <button class="btn btn-sm btn-primary"
                                    data-bs-toggle="modal" data-bs-target="#modalAjouter"
                                    onclick="prefixerCategorie('{{ $proc->code }}')">
                                    <i class="fas fa-plus"></i> Ajouter une pièce
                                </button>
                            </div>
                        @endcan
                    </div>

                    @if ($etat['ciblees']->isNotEmpty())
                        <div class="mt-2">
                            @foreach ($etat['ciblees'] as $regle)
                                <div class="regle-ligne">
                                    <span class="portee-pastille {{ $regle->parcours_id ? 'p-parcours' : 'p-niveau' }}">
                                        {{ $regle->cible_label }}
                                    </span>
                                    @if ($regle->ouverteAujourdhui())
                                        <span class="badge bg-success">Ouverte</span>
                                    @else
                                        <span class="badge bg-secondary">Fermée</span>
                                    @endif
                                    <span class="text-muted small">
                                        {{ $regle->date_debut?->format('d/m/Y') ?? '—' }}
                                        → {{ $regle->date_fin?->format('d/m/Y') ?? '—' }}
                                    </span>
                                    @can('parametres.gerer')
                                        <form method="POST" class="ms-auto"
                                            action="{{ route('admin.request-types.ouverture.supprimer', $regle) }}"
                                            onsubmit="return confirm('Retirer cette règle ? La règle générale reprendra la main.');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Retirer cette règle">
                                                <i class="fas fa-xmark"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ═══ Pièces attendues ═══ --}}
                @if ($liste->isEmpty())
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fs-3 d-block mb-2" style="opacity:.4;color:var(--t3);"></i>
                        <div class="text-muted mb-3">
                            Aucune pièce définie pour « {{ $proc->nom }} » en {{ $info['label'] }}.
                        </div>
                        {{-- Un niveau sans pièce laisse passer des dossiers vides :
                             on le dit ici, à l'endroit exact où ça se corrige. --}}
                        <div class="alert alert-warning d-inline-block text-start mb-0"
                            style="font-size:12.5px;max-width:560px;">
                            <i class="fas fa-triangle-exclamation"></i>
                            Tant qu'aucune pièce n'est définie, un dossier « {{ $proc->nom }} »
                            de ce niveau <strong>peut être soumis vide</strong>.
                            Ajoutez-en une, ou recopiez celles d'un autre niveau ci-dessous.
                        </div>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Portée</th>
                                    <th class="text-center">Obligatoire</th>
                                    <th class="text-center">État</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($liste as $type)
                                    @php
                                        // La pièce appartient à ce niveau : reste à savoir
                                        // si tout le niveau la fournit, ou certains parcours.
                                        $estCommune  = $type->parcours->isEmpty();
                                        $parcoursIci = $type->parcours;
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $type->label }}</strong>
                                            @if ($type->description)
                                                <div class="small text-muted">{{ $type->description }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($estCommune)
                                                <span class="portee-pastille p-niveau">Tout {{ $info['label'] }}</span>
                                            @else
                                                @foreach ($parcoursIci as $p)
                                                    <span class="portee-pastille p-parcours">{{ $p->nom }}</span>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($type->is_required)
                                                <i class="fas fa-circle-check text-success" title="Obligatoire"></i>
                                            @else
                                                <i class="fas fa-circle-minus text-muted" title="Facultative"></i>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($type->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @can('parametres.gerer')
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary"
                                                        data-bs-toggle="modal" data-bs-target="#modalEdit{{ $type->id }}"
                                                        title="Modifier">
                                                        <i class="fas fa-pen"></i>
                                                    </button>

                                                    {{-- Masquer sans supprimer : le geste courant --}}
                                                    <form method="POST" class="d-inline"
                                                        action="{{ route('admin.request-types.toggle', $type) }}">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="niveau" value="{{ $grade }}">
                                                        <input type="hidden" name="tab" value="{{ $proc->code }}">
                                                        <button type="submit"
                                                            class="btn btn-outline-{{ $type->is_active ? 'secondary' : 'success' }}"
                                                            title="{{ $type->is_active ? 'Désactiver' : 'Activer' }}">
                                                            <i class="fas fa-{{ $type->is_active ? 'eye-slash' : 'eye' }}"></i>
                                                        </button>
                                                    </form>

                                                    {{-- Suppression définitive : refusée par le contrôleur dès
                                                         qu'une pièce a servi. Réservée aux cas extrêmes. --}}
                                                    <form method="POST" class="d-inline"
                                                        action="{{ route('admin.request-types.destroy', $type) }}"
                                                        onsubmit="return confirm('Supprimer définitivement « {{ addslashes($type->label) }} » ? Cette action est irréversible. Pour la retirer sans perdre l'historique, désactivez-la plutôt.');">
                                                        @csrf @method('DELETE')
                                                        <input type="hidden" name="niveau" value="{{ $grade }}">
                                                        <input type="hidden" name="tab" value="{{ $proc->code }}">
                                                        <button type="submit" class="btn btn-outline-danger"
                                                            title="Supprimer définitivement">
                                                            <i class="fas fa-trash-can"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- ═══ Recopier vers un autre niveau ═══
                     Les niveaux ne partagent plus de pièces : sans copie,
                     monter la liste du master reviendrait à ressaisir celle
                     de la licence à l'identique. --}}
                @can('parametres.gerer')
                    @if ($liste->isNotEmpty() && count($grades) > 1)
                        <form method="POST" class="mt-4 pt-3 border-top"
                            action="{{ route('admin.request-types.niveau.dupliquer', $grade) }}"
                            onsubmit="return confirm('Recopier ces pièces vers le niveau choisi ?');">
                            @csrf
                            <input type="hidden" name="category" value="{{ $proc->code }}">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="small" style="color:var(--t2);">
                                    <i class="fas fa-copy"></i>
                                    Recopier ces {{ $liste->count() }} pièce(s) vers
                                </span>
                                <select name="vers" class="form-select form-select-sm" style="width:auto;" required>
                                    @foreach ($grades as $code => $g)
                                        @if ($code !== $grade)
                                            <option value="{{ $code }}">{{ $g['label'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-outline-secondary">Recopier</button>
                            </div>
                            <small class="text-muted d-block mt-1">
                                Les copies naissent communes à leur niveau — la restriction par
                                parcours n'est pas reprise, les parcours n'étant pas les mêmes.
                                Une pièce portant déjà le même libellé là-bas est laissée de côté.
                            </small>
                        </form>
                    @endif
                @endcan
            </div>
        @endforeach
    </div>

    {{-- ═══ Modals d'édition, hors des onglets ═══ --}}
    @can('parametres.gerer')
        @foreach ($procedures as $proc)
            @foreach (($types[$proc->code] ?? collect()) as $type)
                <div class="modal fade" id="modalEdit{{ $type->id }}" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.request-types.update', $type) }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="niveau" value="{{ $grade }}">
                                <div class="modal-header">
                                    <h6 class="modal-title"><i class="fas fa-pen"></i> {{ $type->label }}</h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    @include('admin.request_types._form', [
                                        'item' => $type,
                                        'isDepot' => $type->category === 'depot',
                                        'niveauCourant' => $grade,
                                    ])
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <button class="btn btn-primary"><i class="fas fa-check"></i> Enregistrer</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        @endforeach

        {{-- Nouvelle pièce --}}
        <div class="modal fade" id="modalAjouter" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.request-types.store') }}">
                        @csrf
                        <input type="hidden" name="niveau" value="{{ $grade }}">
                        <div class="modal-header">
                            <h6 class="modal-title"><i class="fas fa-plus"></i> Nouvelle pièce — {{ $info['label'] }}</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            @include('admin.request_types._form', [
                                'item' => null,
                                'isDepot' => null,
                                'niveauCourant' => $grade,
                            ])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button class="btn btn-primary"><i class="fas fa-check"></i> Créer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Ouverture d'une procédure --}}
        <div class="modal fade" id="modalOuverture" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.request-types.ouverture', $grade) }}">
                        @csrf
                        <input type="hidden" name="operation" id="ouv-operation">
                        <div class="modal-header">
                            <h6 class="modal-title">
                                <i class="fas fa-sliders"></i> Ouverture — <span id="ouv-label"></span>
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Cette règle vise</label>
                                <div class="d-flex gap-3 flex-wrap">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="cible" value="niveau"
                                            id="ouv-cible-niveau" checked onchange="basculerCible(this)">
                                        <label class="form-check-label" for="ouv-cible-niveau">
                                            Tout {{ $info['label'] }}
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="cible" value="parcours"
                                            id="ouv-cible-parcours" onchange="basculerCible(this)"
                                            {{ $parcoursDuNiveau->isEmpty() ? 'disabled' : '' }}>
                                        <label class="form-check-label" for="ouv-cible-parcours">
                                            Un parcours précis
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3" id="ouv-parcours-bloc" style="display:none;">
                                <label class="form-label">Parcours de {{ $info['label'] }}</label>
                                <select name="parcours_id" class="form-select">
                                    @foreach ($parcoursParFaculte as $fac => $liste)
                                        <optgroup label="{{ $fac }}">
                                            @foreach ($liste as $p)
                                                <option value="{{ $p->id }}">{{ $p->nom }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="is_open" value="1" id="ouv-open" checked>
                                <label class="form-check-label" for="ouv-open">Procédure ouverte</label>
                            </div>

                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small">Du (facultatif)</label>
                                    <input type="date" name="date_debut" class="form-control">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Au (facultatif)</label>
                                    <input type="date" name="date_fin" class="form-control">
                                </div>
                            </div>

                            <div class="alert alert-info mt-3 mb-0" style="font-size:12.5px;">
                                <i class="fas fa-circle-info"></i>
                                Sans règle ici, ce niveau suit la règle générale de l'année.
                                Dès qu'une règle est posée, elle l'emporte — et un parcours
                                nommé l'emporte sur son niveau.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button class="btn btn-primary"><i class="fas fa-check"></i> Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

@endsection

@section('js')
    <script>
        function reglerOuverture(btn) {
            document.getElementById('ouv-operation').value = btn.dataset.operation;
            document.getElementById('ouv-label').textContent = btn.dataset.label;
        }

        function basculerCible(radio) {
            document.getElementById('ouv-parcours-bloc').style.display =
                radio.value === 'parcours' ? '' : 'none';
        }

        // Une pièce créée depuis un onglet hérite de sa procédure
        function prefixerCategorie(code) {
            const select = document.querySelector('#modalAjouter select[name="category"]');
            if (select) {
                select.value = code;
                select.dispatchEvent(new Event('change'));
            }
        }

        // L'onglet ouvert survit à un enregistrement
        (function () {
            const CLE = 'rtNiveauOnglet';
            document.querySelectorAll('.niv-tabs .nav-link').forEach(b => {
                b.addEventListener('shown.bs.tab', () => {
                    try { sessionStorage.setItem(CLE, b.dataset.bsTarget); } catch (e) {}
                });
            });
            try {
                const cible = sessionStorage.getItem(CLE);
                const bouton = cible && document.querySelector(`.niv-tabs [data-bs-target="${cible}"]`);
                if (bouton) new bootstrap.Tab(bouton).show();
            } catch (e) {}
        })();
    </script>
@endsection
