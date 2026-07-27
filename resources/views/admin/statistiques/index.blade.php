@extends('layouts.app')
@section('page-title', 'Statistiques')
@section('page-subtitle', $annee ? "Année {$annee->libelle}" : 'Toutes années')

@section('css')
    <style>
        .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:14px; }
        .kpi { background:var(--surface); border:1px solid var(--line); border-radius:14px; padding:16px 18px; }
        .kpi-n { font-size:26px; font-weight:800; line-height:1; color:var(--t1); }
        .kpi-l { font-size:12px; color:var(--t2); margin-top:5px; }
        .kpi-ic { float:right; opacity:.5; font-size:1.1rem; }
        .bar-row { margin-bottom:11px; }
        .bar-head { display:flex; justify-content:space-between; font-size:12.5px; margin-bottom:4px; }
        .bar-head span:first-child { color:var(--t1); font-weight:600; }
        .bar-head span:last-child { color:var(--t2); }
        .bar-track { height:9px; background:var(--surface-3); border-radius:6px; overflow:hidden; }
        .bar-fill { height:100%; border-radius:6px; }
        .empty-hint { color:var(--t3); font-size:13px; padding:8px 0; }
    </style>
@endsection

@section('content')
    @php
        $pal = ['#1A56A8','#2F9E33','#B45309','#2563EB','#DC2626','#7C3AED','#0891B2','#DB2777'];
    @endphp

    {{-- ═══ Filtres combinables + exports en un clic ═══ --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="fas fa-sliders"></i> Filtres du rapport</span>
            <div class="d-flex gap-2 flex-wrap">
                @php $qs = request()->getQueryString() ? '?' . request()->getQueryString() : ''; @endphp
                <a href="{{ route('admin.statistiques.export', 'excel') }}{{ $qs }}" class="btn btn-sm btn-success"
                    title="Excel : feuille Synthèse (agrégats) + feuille Détail (liste complète)">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="{{ route('admin.statistiques.export', 'csv') }}{{ $qs }}" class="btn btn-sm btn-outline-secondary"
                    title="CSV : liste détaillée des demandes filtrées">
                    <i class="fas fa-file-csv"></i> CSV
                </a>
                <a href="{{ route('admin.statistiques.export', 'pdf') }}{{ $qs }}" class="btn btn-sm btn-danger"
                    title="PDF : rapport de synthèse prêt à transmettre">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </div>
        <div class="card-body py-3">
            <form method="GET" id="statFilters">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small">Année académique</label>
                        <select name="annee_id" class="form-select form-select-sm">
                            <option value="" @selected(!request('annee_id'))>{{ $annee?->libelle ?? '—' }} (contexte)</option>
                            <option value="all" @selected(request('annee_id') === 'all')>Toutes (cumul)</option>
                            @foreach ($annees as $a)
                                <option value="{{ $a->id }}" @selected(request('annee_id') == $a->id)>{{ $a->libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Soumis du</label>
                        <input type="date" name="date_du" class="form-control form-control-sm" value="{{ request('date_du') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">au</label>
                        <input type="date" name="date_au" class="form-control form-control-sm" value="{{ request('date_au') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Procédure</label>
                        <select name="category" class="form-select form-select-sm">
                            <option value="">Toutes</option>
                            @foreach ($categories as $code => $lbl)
                                <option value="{{ $code }}" @selected(request('category') === $code)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Statut</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">Tous</option>
                            @foreach ($statutsLabels as $code => $lbl)
                                <option value="{{ $code }}" @selected(request('status') === $code)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row g-2 align-items-end mt-1">
                    <div class="col-md-3">
                        <label class="form-label small">Faculté / Institut</label>
                        <select name="faculte_id" class="form-select form-select-sm" onchange="document.getElementById('statFilters').submit()">
                            <option value="">Toutes</option>
                            @foreach ($facultes as $f)
                                <option value="{{ $f->id }}" @selected(request('faculte_id') == $f->id)>{{ $f->nom }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Département</label>
                        <select name="departement_id" class="form-select form-select-sm">
                            <option value="">Tous</option>
                            @foreach ($departements as $d)
                                <option value="{{ $d->id }}" @selected(request('departement_id') == $d->id)>{{ $d->nom }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Parcours</label>
                        <select name="parcours_id" class="form-select form-select-sm">
                            <option value="">Tous</option>
                            @foreach ($parcoursList as $p)
                                <option value="{{ $p->id }}" @selected(request('parcours_id') == $p->id)>{{ $p->nom }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">Grade</label>
                        <select name="grade" class="form-select form-select-sm">
                            <option value="">Tous</option>
                            @foreach ($grades as $k => $g)
                                <option value="{{ $k }}" @selected(request('grade') === $k)>{{ $g['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">Niveau</label>
                        <select name="semestre" class="form-select form-select-sm">
                            <option value="">Tous</option>
                            @foreach (\App\Models\Parcours::tousLesNiveaux() as $code => $lbl)
                                <option value="{{ $code }}" @selected(request('semestre') === $code)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">Profil</label>
                        <select name="type_etudiant" class="form-select form-select-sm">
                            <option value="">Tous</option>
                            <option value="nouveau" @selected(request('type_etudiant') === 'nouveau')>Nouveaux</option>
                            <option value="ancien" @selected(request('type_etudiant') === 'ancien')>Anciens</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label small">Sexe</label>
                        <select name="sexe" class="form-select form-select-sm">
                            <option value="">Tous</option>
                            <option value="M" @selected(request('sexe') === 'M')>M</option>
                            <option value="F" @selected(request('sexe') === 'F')>F</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <button class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Appliquer les filtres</button>
                    <a href="{{ route('admin.statistiques.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Réinitialiser</a>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══ KPI ═══ --}}
    <div class="kpi-grid mb-4">
        @php
            $kpis = [
                ['n'=>$total,     'l'=>'Demandes au total', 'i'=>'fa-folder-open',    'c'=>'#1A56A8'],
                ['n'=>$enAttente, 'l'=>'En attente',        'i'=>'fa-hourglass-half', 'c'=>'#B45309'],
                ['n'=>$aCorriger, 'l'=>'À corriger',        'i'=>'fa-rotate-left',    'c'=>'#2563EB'],
                ['n'=>$validees,  'l'=>'Validées',          'i'=>'fa-circle-check',   'c'=>'#2F9E33'],
                ['n'=>$rejetees,  'l'=>'Rejetées',          'i'=>'fa-ban',            'c'=>'#DC2626'],
                ['n'=>$brouillon, 'l'=>'Brouillons',        'i'=>'fa-pen',            'c'=>'#8A98AC'],
            ];
        @endphp
        @foreach ($kpis as $k)
            <div class="kpi">
                <i class="fas {{ $k['i'] }} kpi-ic" style="color:{{ $k['c'] }};"></i>
                <div class="kpi-n" style="color:{{ $k['c'] }};">{{ $k['n'] }}</div>
                <div class="kpi-l">{{ $k['l'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- ═══ Volumétrie & délais ═══ --}}
    <div class="kpi-grid mb-4">
        <div class="kpi">
            <i class="fas fa-paperclip kpi-ic"></i>
            <div class="kpi-n">{{ $nbPieces }}</div><div class="kpi-l">Pièces déposées</div>
        </div>
        <div class="kpi">
            <i class="fas fa-stamp kpi-ic" style="color:#1A56A8;"></i>
            <div class="kpi-n" style="color:#1A56A8;">{{ $nbTamponne }}</div><div class="kpi-l">Pièces tamponnées</div>
        </div>
        <div class="kpi">
            <i class="fas fa-clock kpi-ic"></i>
            <div class="kpi-n">{{ $delaiJours !== null ? $delaiJours : '—' }}</div>
            <div class="kpi-l">Délai moyen de traitement (jours)</div>
        </div>
        <div class="kpi">
            <i class="fas fa-file-signature kpi-ic" style="color:#7C3AED;"></i>
            <div class="kpi-n" style="color:#7C3AED;">{{ $candTotal }}</div><div class="kpi-l">Candidatures</div>
        </div>
    </div>

    {{-- ═══ Courbe : évolution des soumissions ═══ --}}
    <div class="card mb-4">
        <div class="card-header"><i class="fas fa-chart-line"></i> Évolution des soumissions par mois</div>
        <div class="card-body">
            @if (empty($parMois))
                <div class="empty-hint">Aucune soumission sur la période.</div>
            @else
                <div style="position:relative;height:250px;"><canvas id="stMois"></canvas></div>
            @endif
        </div>
    </div>

    <div class="row g-4">
        {{-- Donut : répartition par statut --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-chart-pie"></i> Répartition par statut</div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    @if (empty($parStatut))
                        <div class="empty-hint">Aucune donnée.</div>
                    @else
                        <div style="position:relative;height:240px;width:100%;"><canvas id="stStatut"></canvas></div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Barres empilées : procédure × statut --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-layer-group"></i> Par procédure (détail par statut)</div>
                <div class="card-body">
                    @if (empty($parCategorie))
                        <div class="empty-hint">Aucune donnée.</div>
                    @else
                        <div style="position:relative;height:240px;"><canvas id="stProc"></canvas></div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Camembert : facultés --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-building-columns"></i> Par faculté / institut</div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    @if (empty($parFaculte))
                        <div class="empty-hint">Aucune donnée.</div>
                    @else
                        <div style="position:relative;height:240px;width:100%;"><canvas id="stFac"></canvas></div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Barres horizontales : top parcours --}}
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-graduation-cap"></i> Top parcours</div>
                <div class="card-body">
                    @if (empty($parParcours))
                        <div class="empty-hint">Aucune donnée.</div>
                    @else
                        <div style="position:relative;height:240px;"><canvas id="stParc"></canvas></div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Donut : année d'études --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-calendar-week"></i> Par année d'études</div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    @if (empty($parSemestre))
                        <div class="empty-hint">Aucune donnée.</div>
                    @else
                        <div style="position:relative;height:220px;width:100%;"><canvas id="stNiv"></canvas></div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Deux donuts : sexe + profil --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-users"></i> Profil des demandeurs</div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="small fw-bold mb-1" style="color:var(--t3);">SEXE</div>
                            @if (empty($parSexe))
                                <div class="empty-hint">—</div>
                            @else
                                <div style="position:relative;height:170px;"><canvas id="stSexe"></canvas></div>
                            @endif
                        </div>
                        <div class="col-6">
                            <div class="small fw-bold mb-1" style="color:var(--t3);">PROFIL</div>
                            @if (empty($parType))
                                <div class="empty-hint">—</div>
                            @else
                                <div style="position:relative;height:170px;"><canvas id="stType"></canvas></div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Donut : candidatures --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-file-signature"></i> Candidatures par statut</div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    @if (empty($candParStatut))
                        <div class="empty-hint">Aucune candidature.</div>
                    @else
                        <div style="position:relative;height:220px;width:100%;"><canvas id="stCand"></canvas></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
        (function () {
            const PAL = @json($pal);
            const AXIS = { ticks: { color: '#51607A' }, grid: { color: 'rgba(16,32,64,.07)' } };
            const LEG = { position: 'bottom', labels: { font: { size: 11 }, color: '#51607A', boxWidth: 12, padding: 10 } };
            const el = id => document.getElementById(id);

            /* ── Courbe : soumissions par mois ── */
            const mois = @json($parMois);
            if (el('stMois')) {
                const noms = ['Janv.','Févr.','Mars','Avr.','Mai','Juin','Juil.','Août','Sept.','Oct.','Nov.','Déc.'];
                const fmt = m => { const [y, mm] = m.split('-'); return (noms[parseInt(mm, 10) - 1] || m) + ' ' + y; };
                const ctx = el('stMois').getContext('2d');
                const grad = ctx.createLinearGradient(0, 0, 0, 250);
                grad.addColorStop(0, 'rgba(26,86,168,.28)'); grad.addColorStop(1, 'rgba(26,86,168,0)');
                new Chart(el('stMois'), {
                    type: 'line',
                    data: { labels: Object.keys(mois).map(fmt), datasets: [{
                        label: 'Soumissions', data: Object.values(mois),
                        borderColor: '#1A56A8', backgroundColor: grad, fill: true, tension: .4,
                        borderWidth: 2.5, pointRadius: 4, pointBackgroundColor: '#fff',
                        pointBorderColor: '#1A56A8', pointBorderWidth: 2, pointHoverRadius: 6 }] },
                    options: { responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1, color: '#51607A' }, grid: AXIS.grid }, x: { grid: { display: false }, ticks: AXIS.ticks } } }
                });
            }

            const donut = (id, labels, values, colors) => {
                if (!el(id) || !labels.length) return;
                new Chart(el(id), {
                    type: 'doughnut',
                    data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
                    options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: LEG } }
                });
            };

            /* ── Donut statuts (couleurs sémantiques) ── */
            const stColors = { 'brouillon': '#8A95A8', 'soumis': '#B6791C', 'en_correction': '#2563EB', 'validé': '#15885A', 'rejeté': '#D23B4E' };
            const statuts = @json($parStatut);
            const stLbl = @json($statutsLabels);
            donut('stStatut', Object.keys(statuts).map(s => stLbl[s] || s), Object.values(statuts),
                Object.keys(statuts).map(s => stColors[s] || '#8A95A8'));

            /* ── Barres EMPILÉES : procédure × statut ── */
            const catStat = @json($parCatStatut);
            const catLbl = @json($categories);
            if (el('stProc') && Object.keys(catStat).length) {
                const cats = Object.keys(catStat);
                const datasets = Object.keys(stColors).map(s => ({
                    label: stLbl[s] || s,
                    data: cats.map(c => catStat[c][s] || 0),
                    backgroundColor: stColors[s], borderRadius: 4,
                })).filter(d => d.data.some(v => v > 0));
                new Chart(el('stProc'), {
                    type: 'bar',
                    data: { labels: cats.map(c => catLbl[c] || c), datasets },
                    options: { responsive: true, maintainAspectRatio: false,
                        plugins: { legend: LEG },
                        scales: { x: { stacked: true, grid: { display: false }, ticks: AXIS.ticks },
                                  y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1, color: '#51607A' }, grid: AXIS.grid } } }
                });
            }

            /* ── Camembert facultés ── */
            const fac = @json($parFaculte);
            if (el('stFac')) new Chart(el('stFac'), {
                type: 'pie',
                data: { labels: Object.keys(fac), datasets: [{ data: Object.values(fac),
                    backgroundColor: Object.keys(fac).map((_, i) => PAL[i % PAL.length]), borderWidth: 2, borderColor: '#fff' }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: LEG } }
            });

            /* ── Barres horizontales : top parcours ── */
            const parc = @json($parParcours);
            if (el('stParc') && Object.keys(parc).length) {
                new Chart(el('stParc'), {
                    type: 'bar',
                    data: { labels: Object.keys(parc), datasets: [{ data: Object.values(parc),
                        backgroundColor: Object.keys(parc).map((_, i) => PAL[i % PAL.length]), borderRadius: 6 }] },
                    options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { stepSize: 1, color: '#51607A' }, grid: AXIS.grid },
                                  y: { grid: { display: false }, ticks: AXIS.ticks } } }
                });
            }

            /* ── Donuts : niveau, sexe, profil, candidatures ── */
            const niv = @json($parSemestre);
            donut('stNiv', Object.keys(niv), Object.values(niv), Object.keys(niv).map((_, i) => PAL[i % PAL.length]));

            const sexe = @json($parSexe);
            donut('stSexe', Object.keys(sexe).map(s => s === 'M' ? 'Masculin' : (s === 'F' ? 'Féminin' : s)),
                Object.values(sexe), Object.keys(sexe).map(s => s === 'F' ? '#DB2777' : '#1A56A8'));

            const typ = @json($parType);
            donut('stType', Object.keys(typ).map(t => t === 'nouveau' ? 'Nouveaux' : 'Anciens'),
                Object.values(typ), ['#0891B2', '#7C3AED']);

            const cand = @json($candParStatut);
            const candLbl = @json(\App\Models\Candidature::$statuts);
            donut('stCand', Object.keys(cand).map(s => candLbl[s] || s), Object.values(cand),
                Object.keys(cand).map((_, i) => PAL[i % PAL.length]));
        })();
    </script>
@endsection
