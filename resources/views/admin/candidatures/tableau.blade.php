@extends('layouts.app')
@section('page-title', 'Candidatures par parcours')
@section('page-subtitle', "Vue d'ensemble pour les commissions d'admission")

@section('content')
    @php
        $labels = \App\Models\CandidatureChoix::$statuts;
        $anneeChoisie = request('annee_id', $anneeContext?->id);
        $facParType = $facultes->groupBy(fn($f) => \App\Models\Faculte::$types[$f->type] ?? 'Autre');
        $nbFiltres = collect(['faculte_id', 'parcours_id'])->filter(fn($k) => request()->filled($k))->count();
        $qs = request()->getQueryString(); $qs = $qs ? '?' . $qs : '';
        $couleurs = [
            'soumise' => '#F0A202', 'en_instruction' => '#2C8DBF', 'en_correction' => '#7A8699',
            'admis' => '#2F9E33', 'refuse' => '#C7362F',
        ];
    @endphp

    {{-- ═══ Bascule liste / vue d'ensemble ═══ --}}
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <a href="{{ route('admin.candidatures.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-list"></i> Liste des candidatures
        </a>
        <span class="btn btn-sm btn-primary disabled">
            <i class="fas fa-chart-column"></i> Vue par parcours
        </span>
    </div>

    {{-- ═══ Filtres ═══ --}}
    <div class="card mb-4"><div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label class="form-label small mb-1" style="color:var(--t2);">Année académique</label>
                <select name="annee_id" class="form-select form-select-sm">
                    @foreach ($annees as $a)
                        <option value="{{ $a->id }}" @selected((string) $anneeChoisie === (string) $a->id)>
                            {{ $a->libelle }}{{ $a->is_active ? ' (en cours)' : '' }}
                        </option>
                    @endforeach
                    <option value="all" @selected(request('annee_id') === 'all')>Toutes les années</option>
                </select>
            </div>
            <div class="col-lg-4 col-md-6">
                <label class="form-label small mb-1" style="color:var(--t2);">
                    Faculté / Institut / École
                    @if ($restreint)<i class="fas fa-user-shield ms-1" title="Limité à votre périmètre"></i>@endif
                </label>
                <select name="faculte_id" class="form-select form-select-sm" onchange="this.form.parcours_id.value=''">
                    <option value="">Toutes</option>
                    @foreach ($facParType as $typeLabel => $liste)
                        <optgroup label="{{ $typeLabel }}">
                            @foreach ($liste as $f)
                                <option value="{{ $f->id }}" @selected((string) request('faculte_id') === (string) $f->id)>{{ $f->nom }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small mb-1" style="color:var(--t2);">Parcours</label>
                <select name="parcours_id" class="form-select form-select-sm">
                    <option value="">Tous</option>
                    @foreach ($parcoursList as $p)
                        <option value="{{ $p->id }}" @selected((string) request('parcours_id') === (string) $p->id)>{{ $p->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6 filtre-actions">
                <button class="btn btn-primary btn-filtrer"><i class="fas fa-search"></i> Filtrer</button>
                @if ($nbFiltres)
                    <a href="{{ route('admin.candidatures.tableau') }}" class="btn btn-outline-secondary btn-reset" title="Réinitialiser">
                        <i class="fas fa-times"></i> {{ $nbFiltres }}
                    </a>
                @endif
            </div>
        </form>
    </div></div>

    @if ($lignes->isEmpty())
        <div class="card"><div class="card-body text-center py-5">
            <i class="fas fa-chart-column fs-1 mb-3" style="color:var(--t3);"></i>
            <h6 class="fw-bold" style="color:var(--t1);">Aucune candidature sur cette période</h6>
            <p class="small mb-0" style="color:var(--t2);">
                Changez d'année académique ou élargissez les filtres.
            </p>
        </div></div>
    @else

    {{-- ═══ Chiffres de tête ═══ --}}
    <div class="row g-3 mb-4">
        @php
            $cartes = [
                ['Parcours concernés', $totaux['parcours'], 'fa-diagram-project', '#1A56A8'],
                ['Vœux déposés',       $totaux['voeux'],    'fa-file-signature', '#2C8DBF'],
                ['Reste à traiter',    $totaux['en_cours'], 'fa-hourglass-half', '#F0A202'],
                ['Admis',              $totaux['par_statut']['admis'], 'fa-circle-check', '#2F9E33'],
            ];
        @endphp
        @foreach ($cartes as [$lbl, $val, $ic, $col])
            <div class="col-6 col-lg-3">
                <div class="card h-100"><div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="d-flex align-items-center justify-content-center flex-shrink-0"
                        style="width:44px;height:44px;border-radius:12px;background:{{ $col }}1a;color:{{ $col }};">
                        <i class="fas {{ $ic }}"></i>
                    </div>
                    <div>
                        <div style="font-size:1.5rem;font-weight:700;line-height:1;color:var(--t1);">{{ $val }}</div>
                        <div class="small" style="color:var(--t2);">{{ $lbl }}</div>
                    </div>
                </div></div>
            </div>
        @endforeach
    </div>

    {{-- ═══ Répartition visuelle ═══ --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-chart-column"></i> Vœux par parcours et par statut</div>
                <div class="card-body">
                    <div style="position:relative;height:{{ max(240, $lignes->count() * 42) }}px;">
                        <canvas id="cbParcours"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-chart-pie"></i> Où en sont les dossiers</div>
                <div class="card-body">
                    <div style="position:relative;height:230px;"><canvas id="cbStatuts"></canvas></div>
                    @if ($totaux['taux_admis'] !== null)
                        <div class="text-center mt-3 pt-3" style="border-top:1px solid var(--line);">
                            <div style="font-size:1.6rem;font-weight:700;color:#2F9E33;line-height:1;">
                                {{ $totaux['taux_admis'] }} %
                            </div>
                            <div class="small" style="color:var(--t2);">
                                d'admission sur {{ $totaux['tranches'] }} dossier(s) tranché(s)
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Détail chiffré ═══ --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span style="font-weight:600;color:var(--t1);">
                <i class="fas fa-table"></i> Détail par parcours
            </span>
            <div class="btn-group btn-group-sm">
                <a href="{{ route('admin.candidatures.tableau.export', 'excel') }}{{ $qs }}"
                    class="btn btn-outline-success" title="Exporter ce tableau"><i class="fas fa-file-excel"></i> Excel</a>
                <a href="{{ route('admin.candidatures.tableau.export', 'csv') }}{{ $qs }}"
                    class="btn btn-outline-secondary" title="Exporter au format CSV"><i class="fas fa-file-csv"></i> CSV</a>
                <a href="{{ route('admin.candidatures.tableau.export', 'pdf') }}{{ $qs }}"
                    class="btn btn-outline-danger" title="Rapport PDF pour la commission"><i class="fas fa-file-pdf"></i> PDF</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Parcours</th>
                        <th class="text-center">Candidats</th>
                        <th class="text-center">Vœux</th>
                        @foreach ($labels as $k => $l)
                            <th class="text-center small">{{ $l }}</th>
                        @endforeach
                        <th class="text-center">Reste</th>
                        <th class="text-center">Taux d'admission</th>
                        <th class="text-center">Ouvrir</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lignes as $l)
                        <tr>
                            <td>
                                <strong>{{ $l->nom }}</strong>
                                <br><small class="text-muted">{{ $l->faculte }} · {{ $l->grade }}</small>
                            </td>
                            <td class="text-center">{{ $l->candidats }}</td>
                            <td class="text-center"><strong>{{ $l->voeux }}</strong></td>
                            @foreach ($labels as $k => $lib)
                                <td class="text-center">
                                    @if ($l->par_statut[$k] > 0)
                                        <span class="badge" style="background:{{ $couleurs[$k] }};">{{ $l->par_statut[$k] }}</span>
                                    @else
                                        <span style="color:var(--t3);">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-center">
                                @if ($l->en_cours > 0)
                                    <span class="badge bg-warning text-dark">{{ $l->en_cours }}</span>
                                @else
                                    <i class="fas fa-check text-success" title="Tout est tranché"></i>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($l->taux_admis === null)
                                    <small style="color:var(--t3);">—</small>
                                @else
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="flex-grow-1" style="height:6px;background:var(--line);border-radius:4px;overflow:hidden;">
                                            <div style="height:100%;width:{{ $l->taux_admis }}%;background:#2F9E33;"></div>
                                        </div>
                                        <small style="min-width:44px;color:var(--t2);">{{ $l->taux_admis }} %</small>
                                    </div>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.candidatures.index', ['parcours_id' => $l->parcours_id, 'annee_id' => request('annee_id', $anneeContext?->id)]) }}"
                                    class="btn btn-sm btn-outline-primary" title="Voir les candidats de ce parcours">
                                    <i class="fas fa-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="background:var(--bg2,#f6f7f9);font-weight:600;">
                        <td>Total</td>
                        <td class="text-center">{{ $totaux['candidats'] }}</td>
                        <td class="text-center">{{ $totaux['voeux'] }}</td>
                        @foreach ($labels as $k => $lib)
                            <td class="text-center">{{ $totaux['par_statut'][$k] }}</td>
                        @endforeach
                        <td class="text-center">{{ $totaux['en_cours'] }}</td>
                        <td class="text-center">{{ $totaux['taux_admis'] === null ? '—' : $totaux['taux_admis'] . ' %' }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-body py-2">
            <small style="color:var(--t3);">
                <i class="fas fa-circle-info"></i>
                Un candidat visant plusieurs parcours compte <strong>une fois dans chacun</strong> :
                le total des vœux dépasse donc le nombre de personnes.
                Le taux d'admission ne porte que sur les dossiers déjà tranchés.
            </small>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
        (function () {
            const parcours = @json($lignes->pluck('nom'));
            const statuts  = @json($labels);
            const couleurs = @json($couleurs);
            const parStatut = @json($lignes->pluck('par_statut'));
            const totaux    = @json($totaux['par_statut']);

            const AXIS = { grid: { color: 'rgba(0,0,0,.06)' }, ticks: { color: '#51607A' } };
            const LEG  = { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 12, color: '#51607A' } };

            // Barres empilées : une pile par parcours, un segment par statut
            const cb = document.getElementById('cbParcours');
            if (cb) new Chart(cb, {
                type: 'bar',
                data: {
                    labels: parcours,
                    datasets: Object.keys(statuts).map(k => ({
                        label: statuts[k],
                        data: parStatut.map(p => p[k] || 0),
                        backgroundColor: couleurs[k],
                        borderWidth: 0,
                    })),
                },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    plugins: { legend: LEG },
                    scales: {
                        x: { stacked: true, beginAtZero: true, ticks: { stepSize: 1, color: '#51607A' }, grid: AXIS.grid },
                        y: { stacked: true, grid: { display: false }, ticks: AXIS.ticks },
                    },
                },
            });

            const cs = document.getElementById('cbStatuts');
            if (cs) new Chart(cs, {
                type: 'doughnut',
                data: {
                    labels: Object.values(statuts),
                    datasets: [{
                        data: Object.keys(statuts).map(k => totaux[k] || 0),
                        backgroundColor: Object.keys(statuts).map(k => couleurs[k]),
                        borderWidth: 2, borderColor: '#fff',
                    }],
                },
                options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: LEG } },
            });
        })();
    </script>

    @endif
@endsection
