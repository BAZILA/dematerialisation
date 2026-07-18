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

    {{-- ═══ Filtres ═══ --}}
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">Faculté / Institut</label>
                    <select name="faculte_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Toutes</option>
                        @foreach ($facultes as $f)
                            <option value="{{ $f->id }}" @selected(request('faculte_id') == $f->id)>{{ $f->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Parcours</label>
                    <select name="parcours_id" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        @foreach ($parcoursList as $p)
                            <option value="{{ $p->id }}" @selected(request('parcours_id') == $p->id)>{{ $p->nom }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Semestre</label>
                    <select name="semestre" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        @foreach (['S1','S2','S3','S4','S5','S6'] as $s)
                            <option value="{{ $s }}" @selected(request('semestre') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter"></i> Filtrer</button>
                    <a href="{{ route('admin.statistiques.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i></a>
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

    <div class="row g-4">
        {{-- Par procédure --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-layer-group"></i> Par procédure</div>
                <div class="card-body">
                    @if (empty($parCategorie))
                        <div class="empty-hint">Aucune donnée.</div>
                    @else
                        @php $maxC = max($parCategorie); $i = 0; @endphp
                        @foreach ($parCategorie as $cat => $n)
                            <div class="bar-row">
                                <div class="bar-head">
                                    <span>{{ $categories[$cat] ?? $cat }}</span>
                                    <span>{{ $n }}</span>
                                </div>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width:{{ $maxC ? round($n / $maxC * 100) : 0 }}%;background:{{ $pal[$i++ % count($pal)] }};"></div>
                                </div>
                                <div class="small mt-1" style="color:var(--t3);font-size:11px;">
                                    @foreach ($parCatStatut[$cat] ?? [] as $st => $sn)
                                        <span class="me-2">{{ $statutsLabels[$st] ?? $st }} : <strong>{{ $sn }}</strong></span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- Par statut --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-list-check"></i> Répartition par statut</div>
                <div class="card-body">
                    @if (empty($parStatut))
                        <div class="empty-hint">Aucune donnée.</div>
                    @else
                        @php $maxS = max($parStatut); $i = 0; @endphp
                        @foreach ($parStatut as $st => $n)
                            <div class="bar-row">
                                <div class="bar-head"><span>{{ $statutsLabels[$st] ?? $st }}</span>
                                    <span>{{ $n }} · {{ $total ? round($n / $total * 100) : 0 }}%</span></div>
                                <div class="bar-track"><div class="bar-fill"
                                    style="width:{{ $maxS ? round($n / $maxS * 100) : 0 }}%;background:{{ $pal[$i++ % count($pal)] }};"></div></div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- Par faculté --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-building-columns"></i> Par faculté / institut</div>
                <div class="card-body">
                    @if (empty($parFaculte))
                        <div class="empty-hint">Aucune donnée.</div>
                    @else
                        @php $maxF = max($parFaculte); $i = 0; @endphp
                        @foreach ($parFaculte as $nom => $n)
                            <div class="bar-row">
                                <div class="bar-head"><span>{{ $nom }}</span><span>{{ $n }}</span></div>
                                <div class="bar-track"><div class="bar-fill"
                                    style="width:{{ $maxF ? round($n / $maxF * 100) : 0 }}%;background:{{ $pal[$i++ % count($pal)] }};"></div></div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- Top parcours --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-graduation-cap"></i> Top parcours</div>
                <div class="card-body">
                    @if (empty($parParcours))
                        <div class="empty-hint">Aucune donnée.</div>
                    @else
                        @php $maxP = max($parParcours); $i = 0; @endphp
                        @foreach ($parParcours as $nom => $n)
                            <div class="bar-row">
                                <div class="bar-head"><span>{{ $nom }}</span><span>{{ $n }}</span></div>
                                <div class="bar-track"><div class="bar-fill"
                                    style="width:{{ $maxP ? round($n / $maxP * 100) : 0 }}%;background:{{ $pal[$i++ % count($pal)] }};"></div></div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- Semestre / saison --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-calendar-week"></i> Par semestre &amp; session</div>
                <div class="card-body">
                    @if (empty($parSemestre))
                        <div class="empty-hint">Aucune donnée.</div>
                    @else
                        @php $maxSe = max($parSemestre); $i = 0; @endphp
                        @foreach ($parSemestre as $sem => $n)
                            <div class="bar-row">
                                <div class="bar-head">
                                    <span>{{ $sem }} <span style="color:var(--t3);font-weight:400;">· {{ \App\Models\StudentRequest::saison($sem) }}</span></span>
                                    <span>{{ $n }}</span>
                                </div>
                                <div class="bar-track"><div class="bar-fill"
                                    style="width:{{ $maxSe ? round($n / $maxSe * 100) : 0 }}%;background:{{ $pal[$i++ % count($pal)] }};"></div></div>
                            </div>
                        @endforeach
                        <hr>
                        <div class="d-flex gap-4">
                            @foreach ($parSaison as $s => $n)
                                <div><span class="badge bg-primary">{{ $s }}</span> <strong>{{ $n }}</strong></div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Sexe / type --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-users"></i> Profil des demandeurs</div>
                <div class="card-body">
                    <div class="sect small mb-2" style="color:var(--t3);font-weight:700;">SEXE</div>
                    @forelse ($parSexe as $sexe => $n)
                        <div class="bar-row">
                            <div class="bar-head"><span>{{ $sexe === 'M' ? 'Masculin' : ($sexe === 'F' ? 'Féminin' : $sexe) }}</span>
                                <span>{{ $n }} · {{ $total ? round($n / $total * 100) : 0 }}%</span></div>
                            <div class="bar-track"><div class="bar-fill"
                                style="width:{{ $total ? round($n / $total * 100) : 0 }}%;background:{{ $sexe === 'F' ? '#DB2777' : '#1A56A8' }};"></div></div>
                        </div>
                    @empty
                        <div class="empty-hint">Aucune donnée.</div>
                    @endforelse

                    <div class="sect small mt-3 mb-2" style="color:var(--t3);font-weight:700;">TYPE D'ÉTUDIANT</div>
                    @forelse ($parType as $t => $n)
                        <div class="bar-row">
                            <div class="bar-head"><span>{{ $t === 'nouveau' ? 'Nouveau bachelier' : 'Ancien étudiant' }}</span><span>{{ $n }}</span></div>
                            <div class="bar-track"><div class="bar-fill"
                                style="width:{{ $total ? round($n / $total * 100) : 0 }}%;background:#0891B2;"></div></div>
                        </div>
                    @empty
                        <div class="empty-hint">Aucune donnée.</div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Candidatures --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-file-signature"></i> Candidatures par statut</div>
                <div class="card-body">
                    @if (empty($candParStatut))
                        <div class="empty-hint">Aucune candidature.</div>
                    @else
                        @php $maxCa = max($candParStatut); $i = 0; $cl = \App\Models\Candidature::$statuts; @endphp
                        @foreach ($candParStatut as $st => $n)
                            <div class="bar-row">
                                <div class="bar-head"><span>{{ $cl[$st] ?? $st }}</span><span>{{ $n }}</span></div>
                                <div class="bar-track"><div class="bar-fill"
                                    style="width:{{ $maxCa ? round($n / $maxCa * 100) : 0 }}%;background:{{ $pal[$i++ % count($pal)] }};"></div></div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- Évolution mensuelle --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-chart-line"></i> Soumissions par mois</div>
                <div class="card-body">
                    @if (empty($parMois))
                        <div class="empty-hint">Aucune soumission.</div>
                    @else
                        @php $maxM = max($parMois); @endphp
                        @foreach ($parMois as $mois => $n)
                            <div class="bar-row">
                                <div class="bar-head"><span>{{ \Carbon\Carbon::createFromFormat('Y-m', $mois)->translatedFormat('F Y') }}</span><span>{{ $n }}</span></div>
                                <div class="bar-track"><div class="bar-fill"
                                    style="width:{{ $maxM ? round($n / $maxM * 100) : 0 }}%;background:#2F9E33;"></div></div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
