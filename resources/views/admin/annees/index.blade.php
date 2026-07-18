@extends('layouts.app')
@section('page-title', 'Années académiques')
@section('page-subtitle', "Statuts et fenêtres d'ouverture des opérations")

@section('content')
    @php
        $ops = \App\Models\Periode::$operations;
        $statuts = \App\Models\AnneeAcademique::$statuts;
        $badge = ['en_preparation' => 'secondary', 'ouverte' => 'success', 'cloturee' => 'warning', 'archivee' => 'secondary'];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="text-muted small">
            <i class="fas fa-circle-info"></i> Cliquez sur une année pour déplier ses fenêtres.
            L'année <strong>active</strong> définit le contexte ; les clôturées/archivées sont en lecture seule.
        </div>
        <button class="btn btn-primary" onclick="openAnnee('create')" data-bs-toggle="modal" data-bs-target="#modalAnnee">
            <i class="fas fa-plus"></i> Ajouter une année
        </button>
    </div>

    @forelse ($annees as $a)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div data-bs-toggle="collapse" data-bs-target="#an-{{ $a->id }}" role="button" style="cursor:pointer;flex:1;min-width:0;">
                    <i class="fas fa-chevron-down me-1" style="font-size:11px;color:var(--t3);transition:transform .2s;"></i>
                    <i class="fas fa-calendar-days" style="color:var(--brand);"></i>
                    <strong class="ms-1" style="font-size:15px;">{{ $a->libelle }}</strong>
                    <span class="badge bg-{{ $badge[$a->statut] ?? 'secondary' }} ms-1">{{ $a->statut_label }}</span>
                    @if ($a->is_active)<span class="badge bg-primary ms-1"><i class="fas fa-circle-check"></i> Active</span>@endif
                    @if ($a->date_debut)<span class="text-muted small ms-2 d-none d-md-inline">{{ $a->date_debut->format('d/m/Y') }} → {{ $a->date_fin?->format('d/m/Y') ?? '…' }}</span>@endif
                </div>
                <div class="d-flex gap-1">
                    @unless ($a->is_active)
                        <form method="POST" action="{{ route('admin.annees.activate', $a) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary" title="Définir comme année active" data-loading-text="…">
                                <i class="fas fa-toggle-on"></i> Activer
                            </button>
                        </form>
                    @endunless
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalAnnee"
                        onclick="openAnnee('edit', this)" data-url="{{ route('admin.annees.update', $a) }}"
                        data-libelle="{{ $a->libelle }}" data-statut="{{ $a->statut }}"
                        data-debut="{{ $a->date_debut?->format('Y-m-d') }}" data-fin="{{ $a->date_fin?->format('Y-m-d') }}">
                        <i class="fas fa-pen"></i>
                    </button>
                    @unless ($a->is_active)
                        <form method="POST" action="{{ route('admin.annees.destroy', $a) }}" onsubmit="return confirm('Supprimer cette année académique ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    @endunless
                </div>
            </div>
            <div class="collapse {{ $a->is_active ? 'show' : '' }}" id="an-{{ $a->id }}">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                                <tr><th>Opération</th><th>État</th><th>Ouverture</th><th>Fermeture</th><th class="text-end">Réglage</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($ops as $opKey => $opLabel)
                                    @php $p = $a->periodes->firstWhere('operation', $opKey); @endphp
                                    <tr>
                                        <td><strong>{{ $opLabel }}</strong></td>
                                        <td>
                                            @if ($p && $p->is_open)
                                                <span class="badge bg-success"><i class="fas fa-lock-open"></i> Ouverte</span>
                                            @else
                                                <span class="badge bg-secondary"><i class="fas fa-lock"></i> Fermée</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">{{ $p && $p->date_debut ? $p->date_debut->format('d/m/Y') : '—' }}</td>
                                        <td class="text-muted small">{{ $p && $p->date_fin ? $p->date_fin->format('d/m/Y') : '—' }}</td>
                                        <td class="text-end">
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalPeriode"
                                                onclick="openPeriode(this)" data-url="{{ route('admin.annees.periodes.save', $a) }}"
                                                data-operation="{{ $opKey }}" data-label="{{ $opLabel }}"
                                                data-open="{{ $p && $p->is_open ? 1 : 0 }}"
                                                data-debut="{{ $p && $p->date_debut ? $p->date_debut->format('Y-m-d') : '' }}"
                                                data-fin="{{ $p && $p->date_fin ? $p->date_fin->format('Y-m-d') : '' }}">
                                                <i class="fas fa-sliders"></i> Régler
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card">
            <div class="card-body text-center py-5 text-muted">
                <i class="fas fa-calendar-days d-block mb-2" style="font-size:2rem;opacity:.35;"></i>
                Aucune année académique. Cliquez sur « Ajouter une année » pour commencer.
            </div>
        </div>
    @endforelse

    {{-- ═══ Modal Année ═══ --}}
    <div class="modal fade" id="modalAnnee" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formAnnee" method="POST">
                    @csrf
                    <input type="hidden" name="_method" id="an_method" value="POST">
                    <div class="modal-header"><h6 class="modal-title" id="anTitle">Nouvelle année</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3"><label class="form-label">Libellé <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="libelle" placeholder="Ex : 2025-2026" required></div>
                        <div class="mb-3"><label class="form-label">Statut <span class="text-danger">*</span></label>
                            <select class="form-select" name="statut" required>
                                @foreach ($statuts as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select></div>
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label">Début</label><input type="date" class="form-control" name="date_debut"></div>
                            <div class="col-6"><label class="form-label">Fin</label><input type="date" class="form-control" name="date_fin"></div>
                        </div>

                        {{-- Clonage des paramètres (création uniquement) --}}
                        @if ($annees->isNotEmpty())
                            <div class="mt-3 p-3 rounded" id="an_clone_box"
                                style="background:var(--surface-2);border:1px solid var(--line);">
                                <label class="form-label mb-1"><i class="fas fa-copy"></i> Cloner les paramètres</label>
                                <select class="form-select" name="cloner_de" id="an_cloner">
                                    <option value="">— Ne rien cloner —</option>
                                    @foreach ($annees as $a)
                                        <option value="{{ $a->id }}">{{ $a->libelle }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted d-block mt-2">
                                    Reprend les <strong>fenêtres d'ouverture</strong> (dates + état) de l'année choisie.
                                    Les réglages globaux (cachet, signatures) restent partagés entre les années.
                                </small>
                            </div>
                        @endif
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary btn-sm" data-loading-text="Enregistrement…"><i class="fas fa-save"></i> Enregistrer</button></div>
                </form>
            </div>
        </div>
    </div>

    {{-- ═══ Modal Période ═══ --}}
    <div class="modal fade" id="modalPeriode" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="formPeriode" method="POST">
                    @csrf
                    <input type="hidden" name="operation" id="pe_operation">
                    <div class="modal-header"><h6 class="modal-title">Fenêtre — <span id="peLabel"></span></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="is_open" value="1" id="pe_open">
                            <label class="form-check-label" for="pe_open">Opération ouverte</label>
                        </div>
                        <div class="row g-3">
                            <div class="col-6"><label class="form-label">Ouverture</label><input type="date" class="form-control" name="date_debut" id="pe_debut"></div>
                            <div class="col-6"><label class="form-label">Fermeture</label><input type="date" class="form-control" name="date_fin" id="pe_fin"></div>
                        </div>
                        <small class="text-muted d-block mt-2">Dates vides = ouverture non bornée (tant que l'interrupteur est activé).</small>
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
        const AN_STORE = "{{ route('admin.annees.store') }}";

        function openAnnee(mode, el) {
            const f = document.getElementById('formAnnee');
            f.reset();
            document.getElementById('anTitle').textContent = mode === 'edit' ? "Modifier l'année" : 'Nouvelle année';
            // Le clonage n'a de sens qu'à la création
            const cloneBox = document.getElementById('an_clone_box');
            if (cloneBox) cloneBox.style.display = (mode === 'edit') ? 'none' : '';
            if (mode === 'edit') {
                f.action = el.dataset.url;
                document.getElementById('an_method').value = 'PUT';
                f.libelle.value = el.dataset.libelle;
                f.statut.value = el.dataset.statut;
                f.date_debut.value = el.dataset.debut || '';
                f.date_fin.value = el.dataset.fin || '';
            } else {
                f.action = AN_STORE;
                document.getElementById('an_method').value = 'POST';
            }
        }

        function openPeriode(el) {
            const f = document.getElementById('formPeriode');
            f.reset();
            f.action = el.dataset.url;
            document.getElementById('pe_operation').value = el.dataset.operation;
            document.getElementById('peLabel').textContent = el.dataset.label;
            document.getElementById('pe_open').checked = el.dataset.open === '1';
            document.getElementById('pe_debut').value = el.dataset.debut || '';
            document.getElementById('pe_fin').value = el.dataset.fin || '';
        }
    </script>
@endsection
