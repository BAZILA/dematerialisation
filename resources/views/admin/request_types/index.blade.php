@extends('layouts.app')
@section('page-title', 'Types de documents')
@section('page-subtitle', 'Gérez les pièces à fournir pour chaque catégorie de demande')

@section('content')

    @php
        $catColors = [
            'depot' => ['bg' => '185FA5', 'badge' => 'primary', 'icon' => 'fa-folder-open'],
            'attestation' => ['bg' => '0F6E56', 'badge' => 'success', 'icon' => 'fa-certificate'],
            'diplome' => ['bg' => 'B8860B', 'badge' => 'warning', 'icon' => 'fa-graduation-cap'],
            'releve' => ['bg' => '1A6FA8', 'badge' => 'info', 'icon' => 'fa-list-alt'],
            'duplicat' => ['bg' => '993C1D', 'badge' => 'danger', 'icon' => 'fa-id-card'],
        ];
        $profileLabels = [
            'nouveau' => ['label' => 'Nouveau seulement', 'badge' => 'primary'],
            'ancien' => ['label' => 'Ancien seulement', 'badge' => 'secondary'],
            'both' => ['label' => 'Nouveau & Ancien', 'badge' => 'info'],
        ];
        // Onglet actif depuis URL (pour restauration après action)
        $activeTab = request('tab', 'depot');
    @endphp

    <div class="d-flex justify-content-end mb-4">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAjouter">
            <i class="fas fa-plus"></i> Ajouter un type de document
        </button>
    </div>

    {{-- Onglets --}}
    <ul class="nav nav-tabs mb-0" id="catTabs">
        @foreach ($categories as $key => $label)
            @php
                $total = ($types->get($key) ?? collect())->count();
                $actifs = ($types->get($key) ?? collect())->where('is_active', true)->count();
                $c = $catColors[$key] ?? ['bg' => '64748B', 'badge' => 'secondary', 'icon' => 'fa-folder-open'];
            @endphp
            <li class="nav-item">
                <a class="nav-link {{ $key === $activeTab ? 'active' : '' }}" data-bs-toggle="tab"
                    href="#tab-{{ $key }}" data-cat="{{ $key }}">
                    <i class="fas {{ $c['icon'] }}"></i>
                    <span class="ms-1 d-none d-md-inline">{{ $label }}</span>
                    <span class="badge bg-{{ $c['badge'] }} ms-1">{{ $actifs }}/{{ $total }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        @foreach ($categories as $key => $label)
            @php
                $catTypes = ($types->get($key) ?? collect())->sortBy('order');
                $c = $catColors[$key] ?? ['bg' => '64748B', 'badge' => 'secondary', 'icon' => 'fa-folder-open'];
                $isDepot = $key === 'depot';
            @endphp

            <div class="tab-pane fade {{ $key === $activeTab ? 'show active' : '' }}" id="tab-{{ $key }}">
                <div class="card" style="border-top-left-radius:0;border-top-right-radius:0;">
                    <div class="card-header d-flex justify-content-between align-items-center"
                        style="border-left:4px solid #{{ $c['bg'] }};">
                        <div>
                            <i class="fas {{ $c['icon'] }}"></i>
                            <strong class="ms-1">{{ $label }}</strong>
                            @if (!$isDepot)
                                <span class="badge bg-secondary ms-2 small">Anciens étudiants uniquement</span>
                            @else
                                <span class="badge bg-info ms-2 small">Nouveau & Ancien (séparés)</span>
                            @endif
                        </div>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAjouter"
                            onclick="preselectCategory('{{ $key }}')">
                            <i class="fas fa-plus"></i> Ajouter ici
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width:50px;">#</th>
                                    <th>Intitulé du document</th>
                                    @if ($isDepot)
                                        <th>Profil</th>
                                    @endif
                                    <th>Obligatoire</th>
                                    <th>Tamponné</th>
                                    <th>Statut</th>
                                    <th class="text-center" style="width:150px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($catTypes as $type)
                                    <tr class="{{ !$type->is_active ? 'opacity-50' : '' }}">
                                        <td class="text-muted  small">{{ $type->order }}</td>
                                        <td>
                                            <strong class="text-white">{{ $type->label }}</strong>
                                            @if ($key === 'candidature')
                                                @if ($type->parcours->isEmpty())
                                                    <span class="badge bg-info ms-1" title="Demandée à tous les candidats">
                                                        <i class="fas fa-globe"></i> Commune
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning text-dark ms-1"
                                                        title="{{ $type->parcours->pluck('nom')->implode(', ') }}">
                                                        <i class="fas fa-code-branch"></i>
                                                        {{ $type->parcours->count() }} parcours
                                                    </span>
                                                @endif
                                            @endif
                                            @if ($type->description)
                                                <br><small class="text-muted ">{{ $type->description }}</small>
                                            @endif
                                            @if ($key === 'candidature' && $type->parcours->isNotEmpty())
                                                <br><small class="text-muted ">
                                                    {{ $type->parcours->pluck('nom')->implode(' · ') }}
                                                </small>
                                            @endif
                                        </td>
                                        @if ($isDepot)
                                            <td>
                                                @php $pl = $profileLabels[$type->type_etudiant] ?? ['label'=>$type->type_etudiant,'badge'=>'secondary']; @endphp
                                                <span class="badge bg-{{ $pl['badge'] }}">{{ $pl['label'] }}</span>
                                            </td>
                                        @endif
                                        <td>
                                            @if ($type->is_required)
                                                <span class="badge bg-danger">Obligatoire</span>
                                            @else
                                                <span class="badge bg-secondary">Optionnel</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($type->has_stamp)
                                                <span class="badge bg-info"><i class="fas fa-stamp"></i> Oui</span>
                                            @else
                                                <span class="text-muted  small">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($type->is_active)
                                                <span class="badge bg-success"><i class="fas fa-eye"></i> Actif</span>
                                            @else
                                                <span class="badge bg-secondary"><i class="fas fa-eye-slash"></i>
                                                    Masqué</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1 justify-content-center">

                                                {{-- Modifier --}}
                                                <button class="btn btn-sm btn-outline-primary" title="Modifier"
                                                    data-bs-toggle="modal" data-bs-target="#modalEdit{{ $type->id }}">
                                                    <i class="fas fa-pen"></i>
                                                </button>

                                                {{-- Activer / Désactiver --}}
                                                <form method="POST"
                                                    action="{{ route('admin.request-types.toggle', $type) }}?tab={{ $key }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit"
                                                        class="btn btn-sm {{ $type->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                                        title="{{ $type->is_active ? 'Désactiver' : 'Activer' }}">
                                                        <i
                                                            class="fas {{ $type->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                                    </button>
                                                </form>

                                                {{-- Supprimer --}}
                                                <form method="POST"
                                                    action="{{ route('admin.request-types.destroy', $type) }}?tab={{ $key }}"
                                                    onsubmit="return confirm('Supprimer « {{ addslashes($type->label) }} » ?\nCette action est irréversible.')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        title="Supprimer">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>

                                            </div>
                                        </td>
                                    </tr>

                                    {{-- Modal Modifier --}}
                                    <div class="modal fade" id="modalEdit{{ $type->id }}" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h6 class="modal-title">
                                                        <i class="fas fa-pen"></i> Modifier — {{ $type->label }}
                                                    </h6>
                                                    <button type="button" class="btn-close"
                                                        data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST"
                                                    action="{{ route('admin.request-types.update', $type) }}?tab={{ $key }}">
                                                    @csrf @method('PUT')
                                                    <div class="modal-body">
                                                        @include('admin.request_types._form', [
                                                            'item' => $type,
                                                            'isDepot' => $isDepot,
                                                        ])
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm"
                                                            data-bs-dismiss="modal">Annuler</button>
                                                        <button type="submit" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-save"></i> Enregistrer
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                @empty
                                    <tr>
                                        <td colspan="{{ $isDepot ? 7 : 6 }}" class="text-center text-muted py-5">
                                            <i class="fas fa-inbox d-block mb-2" style="font-size:1.8rem;opacity:.3;"></i>
                                            Aucun type de document pour cette catégorie.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($catTypes->isNotEmpty())
                        <div class="card-body py-2 border-top">
                            <small class="text-muted ">
                                <i class="fas fa-info-circle"></i>
                                <strong>Obligatoire</strong> = bloque la soumission si absent.
                                <strong>Tamponné</strong> = cachet automatique à la validation.
                                <strong>Masqué</strong> = retiré des formulaires sans suppression.
                            </small>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Modal Ajouter --}}
    <div class="modal fade" id="modalAjouter" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title"><i class="fas fa-plus"></i> Nouveau type de document</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('admin.request-types.store') }}?tab={{ $activeTab }}"
                    id="formAjouter">
                    @csrf
                    <div class="modal-body">
                        @include('admin.request_types._form', ['item' => null, 'isDepot' => null])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary btn-sm"
                            data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection

@section('js')
    <script>
        (function() {
            // ── Mémoriser le tab actif dans l'URL à chaque clic ──
            document.querySelectorAll('#catTabs .nav-link').forEach(link => {
                link.addEventListener('shown.bs.tab', function() {
                    const key = this.dataset.cat;
                    const url = new URL(window.location);
                    url.searchParams.set('tab', key);
                    history.replaceState(null, '', url);

                    // Mettre à jour l'action du formulaire d'ajout
                    const form = document.getElementById('formAjouter');
                    if (form) {
                        const u = new URL(form.action);
                        u.searchParams.set('tab', key);
                        form.action = u.toString();
                    }
                });
            });

            // ── Pré-sélectionner la catégorie dans le modal Ajouter ──
            window.preselectCategory = function(cat) {
                const sel = document.querySelector('#formAjouter select[name="category"]');
                if (sel) {
                    sel.value = cat;
                    sel.dispatchEvent(new Event('change'));
                }
            };

            // ── Afficher/masquer profil selon catégorie ──
            const addCat = document.querySelector('#formAjouter select[name="category"]');
            const addProfileRow = document.querySelector('#formAjouter [id^="profile-row"]');

            function toggleAddProfile() {
                if (addProfileRow && addCat) {
                    addProfileRow.style.display = addCat.value === 'depot' ? '' : 'none';
                }
            }
            addCat?.addEventListener('change', toggleAddProfile);
            toggleAddProfile();
        })();
    </script>
@endsection
