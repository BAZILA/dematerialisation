@extends('layouts.app')
@section('page-title', 'Tableau de bord')
@section('page-subtitle', "Vue d'ensemble — " . now()->translatedFormat('d M Y'))

@section('content')
    @php
        $cats = \App\Models\RequestType::categories();
        $catColors = [
            'depot' => 'primary',
            'attestation' => 'success',
            'diplome' => 'warning',
            'releve' => 'info',
            'duplicat' => 'danger',
        ];
        $catIcons = [
            'depot' => 'fa-folder-open',
            'attestation' => 'fa-certificate',
            'diplome' => 'fa-graduation-cap',
            'releve' => 'fa-list-alt',
            'duplicat' => 'fa-id-card',
        ];
        $sColors = [
            'brouillon' => 'secondary',
            'soumis' => 'warning',
            'en_correction' => 'info',
            'validé' => 'success',
            'rejeté' => 'danger',
        ];
        $sLabels = [
            'brouillon' => 'Brouillon',
            'soumis' => 'En attente',
            'en_correction' => 'En correction',
            'validé' => 'Validé',
            'rejeté' => 'Rejeté',
        ];
    @endphp

    {{-- ════ Stats ════ --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['Dépôts en attente', $stats['depot_pending'], 'warning', 'fa-hourglass-half'],
                ['Demandes en attente', $stats['requests_pending'], 'info', 'fa-clock'],
                ["Validés aujourd'hui", $stats['validated_today'], 'success', 'fa-check-circle'],
                ['En correction', $stats['in_correction'], 'primary', 'fa-rotate-left'],
                ['Total dossiers', $stats['total'], 'secondary', 'fa-folder'],
                ['Étudiants inscrits', $stats['students'], 'secondary', 'fa-users'],
            ];
        @endphp
        @foreach ($cards as [$label, $value, $color, $icon])
            <div class="col-6 col-md-4 col-lg-2">
                <div class="card text-center h-100">
                    <div class="card-body py-3 px-2">
                        <i class="fas {{ $icon }} text-{{ $color }} mb-2" style="font-size:1.4rem;"></i>
                        <div class="fw-bold" style="font-size:1.6rem;color:var(--t1);">{{ $value }}</div>
                        <div class="small mt-1" style="font-size:11px;line-height:1.3;color:var(--t2);">{{ $label }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ════ Graphiques ════ --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="fas fa-chart-line"></i> Évolution des soumissions</span>
            <small style="color:var(--t3);">Année {{ $annee?->libelle ?? '—' }} — par mois</small>
        </div>
        <div class="card-body">
            <div style="position:relative;height:240px;"><canvas id="chartMois"></canvas></div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-7">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-chart-bar"></i> Demandes par catégorie</div>
                <div class="card-body">
                    <div style="position:relative;height:220px;"><canvas id="chartCat"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card h-100">
                <div class="card-header"><i class="fas fa-chart-pie"></i> Répartition par statut</div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <div style="position:relative;height:220px;width:220px;">
                        <canvas id="chartStatus"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ════ Onglets ════ --}}
    <ul class="nav nav-tabs mb-0" id="adminTabs">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#td-depot" data-tab="depot">
                <i class="fas fa-folder-open"></i>
                <span class="d-none d-md-inline ms-1">Dépôts</span>
                @if ($stats['depot_pending'] > 0)
                    <span class="badge bg-warning ms-1">{{ $stats['depot_pending'] }}</span>
                @endif
            </a>
        </li>
        @foreach ($cats as $key => $label)
            @if ($key !== 'depot')
                <li class="nav-item">
                    <a class="nav-link" data-bs-toggle="tab" href="#td-{{ $key }}" data-tab="{{ $key }}">
                        <i class="fas {{ $catIcons[$key] ?? 'fa-file' }}"></i>
                        <span class="d-none d-md-inline ms-1">{{ $label }}</span>
                        @if (($stats["pending_{$key}"] ?? 0) > 0)
                            <span class="badge bg-warning ms-1">{{ $stats["pending_{$key}"] }}</span>
                        @endif
                    </a>
                </li>
            @endif
        @endforeach
    </ul>

    <div class="tab-content">
        {{-- Dépôts --}}
        <div class="tab-pane fade show active" id="td-depot">
            <div class="card" style="border-top-left-radius:0;border-top-right-radius:0;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span style="color:var(--t1);font-weight:600;">10 derniers dépôts</span>
                    <a href="{{ route('admin.requests.index', 'depot') }}" class="btn btn-sm btn-outline-secondary">
                        Voir tout <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
                @include('admin.partials._request_table', [
                    'items' => $recentDeposits,
                    'category' => 'depot',
                ])
            </div>
        </div>

        {{-- Autres catégories --}}
        @foreach ($cats as $key => $label)
            @if ($key !== 'depot')
                <div class="tab-pane fade" id="td-{{ $key }}">
                    <div class="card" style="border-top-left-radius:0;border-top-right-radius:0;">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <span style="color:var(--t1);font-weight:600;">10 dernières — {{ $label }}</span>
                            <a href="{{ route('admin.requests.index', $key) }}" class="btn btn-sm btn-outline-secondary">
                                Voir tout <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        @include('admin.partials._request_table', [
                            'items' => $recentRequests[$key] ?? collect(),
                            'category' => $key,
                        ])
                    </div>
                </div>
            @endif
        @endforeach
    </div>

@endsection

@section('js')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <script>
        const catData = @json($chartData['by_category']);
        const statusData = @json($chartData['by_status']);
        const moisData = @json($chartData['by_month'] ?? []);

        /* ── Courbe pro : soumissions par mois (dégradé sous la courbe) ── */
        (function () {
            const el = document.getElementById('chartMois');
            if (!el) return;
            const labels = Object.keys(moisData);
            const values = Object.values(moisData);
            const fmt = m => { // « 2026-07 » → « Juil. 2026 »
                const noms = ['Janv.','Févr.','Mars','Avr.','Mai','Juin','Juil.','Août','Sept.','Oct.','Nov.','Déc.'];
                const [y, mm] = m.split('-');
                return (noms[parseInt(mm, 10) - 1] || m) + ' ' + y;
            };
            const ctx = el.getContext('2d');
            const grad = ctx.createLinearGradient(0, 0, 0, 240);
            grad.addColorStop(0, 'rgba(26,86,168,.28)');
            grad.addColorStop(1, 'rgba(26,86,168,0)');
            new Chart(el, {
                type: 'line',
                data: {
                    labels: labels.map(fmt),
                    datasets: [{
                        label: 'Soumissions',
                        data: values,
                        borderColor: '#1A56A8',
                        backgroundColor: grad,
                        fill: true,
                        tension: .4,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#1A56A8',
                        pointBorderWidth: 2,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1, color: '#51607A' }, grid: { color: 'rgba(16,32,64,.07)' } },
                        x: { grid: { display: false }, ticks: { color: '#51607A' } }
                    }
                }
            });
        })();
        const catBg = {
            depot: 'rgba(27,58,107,.85)',
            attestation: 'rgba(21,136,90,.82)',
            diplome: 'rgba(182,121,28,.85)',
            releve: 'rgba(37,99,235,.78)',
            duplicat: 'rgba(210,59,78,.82)'
        };

        new Chart(document.getElementById('chartCat'), {
            type: 'bar',
            data: {
                labels: catData.map(d => d.label),
                datasets: [{
                    label: 'Total',
                    data: catData.map(d => d.total),
                    backgroundColor: catData.map(d => catBg[d.key] || 'rgba(107,114,128,.6)'),
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: '#51607A'
                        },
                        grid: {
                            color: 'rgba(16,32,64,.07)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#51607A'
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: {
                labels: statusData.map(d => d.label),
                datasets: [{
                    data: statusData.map(d => d.count),
                    backgroundColor: ['#8A95A8', '#B6791C', '#2563EB', '#15885A', '#D23B4E'],
                    borderWidth: 2,
                    borderColor: '#FFFFFF'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                size: 11
                            },
                            color: '#51607A',
                            boxWidth: 12,
                            padding: 12
                        }
                    }
                }
            }
        });

        // Persistance onglet
        document.querySelectorAll('#adminTabs .nav-link').forEach(link => {
            link.addEventListener('shown.bs.tab', function() {
                const url = new URL(window.location);
                url.searchParams.set('tab', this.dataset.tab);
                history.replaceState(null, '', url);
            });
        });
        (function() {
            const tab = new URLSearchParams(window.location.search).get('tab');
            if (tab) {
                const link = document.querySelector(`#adminTabs [data-tab="${tab}"]`);
                if (link) bootstrap.Tab.getOrCreateInstance(link).show();
            }
        })();
    </script>
@endsection
