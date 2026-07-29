@extends('layouts.app')
@section('page-title', 'Sauvegardes')
@section('page-subtitle', 'Base de données et pièces déposées — archives horodatées')

@section('content')

    {{-- ═══ État du dispositif ═══ --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body text-center">
                <div class="fw-bold" style="font-size:1.6rem;color:var(--t1);">{{ count($archives) }}</div>
                <div class="small text-muted">Archive(s) disponible(s)</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body text-center">
                <div class="fw-bold" style="font-size:1.6rem;color:var(--t1);">{{ $service->formaterTaille($espace) }}</div>
                <div class="small text-muted">Espace occupé</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body text-center">
                <div class="fw-bold" style="font-size:1.6rem;color:{{ $auto ? 'var(--green)' : '#DC2626' }};">
                    {{ $auto ? $heure : 'Inactive' }}
                </div>
                <div class="small text-muted">Sauvegarde automatique</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card h-100"><div class="card-body text-center">
                <div class="fw-bold" style="font-size:1.6rem;color:var(--t1);">{{ $retention }} j</div>
                <div class="small text-muted">Rétention</div>
            </div></div>
        </div>
    </div>

    {{-- Dernière sauvegarde : l'information la plus importante de la page --}}
    @php $derniere = $archives[0] ?? null; @endphp
    @if (!$derniere)
        <div class="alert alert-danger d-flex align-items-start gap-2">
            <i class="fas fa-triangle-exclamation mt-1"></i>
            <span><strong>Aucune sauvegarde n'existe.</strong> En cas de panne disque, la base et
                toutes les pièces déposées seraient perdues. Lancez-en une maintenant.</span>
        </div>
    @else
        @php $age = $derniere['date']->diffInHours(now()); @endphp
        <div class="alert {{ $age > 48 ? 'alert-warning' : 'alert-success' }} d-flex align-items-start gap-2">
            <i class="fas {{ $age > 48 ? 'fa-triangle-exclamation' : 'fa-circle-check' }} mt-1"></i>
            <span>
                Dernière sauvegarde : <strong>{{ $derniere['date']->translatedFormat('d/m/Y à H:i') }}</strong>
                ({{ $derniere['date']->diffForHumans() }}) — {{ $service->formaterTaille($derniere['taille']) }}.
                @if ($age > 48)
                    <br>Plus de 48 h se sont écoulées : vérifiez que le planificateur de tâches tourne bien.
                @endif
            </span>
        </div>
    @endif

    {{-- File d'attente : sans worker, les travaux s'empilent sans jamais s'exécuter --}}
    @if (!$file['sync'])
        <div class="alert {{ $file['en_attente'] > 20 || $file['echoues'] ? 'alert-warning' : 'alert-info' }} small">
            <i class="fas fa-list-check"></i>
            File d'attente « {{ $file['connexion'] }} » :
            <strong>{{ $file['en_attente'] }}</strong> travail(aux) en attente,
            <strong>{{ $file['echoues'] }}</strong> en échec.
            @if ($file['en_attente'] > 20)
                <br>La file s'allonge : le worker <code>php artisan queue:work</code> tourne-t-il ?
            @endif
        </div>
    @endif

    <div class="row g-4">
        {{-- ═══ Lancer une sauvegarde ═══ --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><i class="fas fa-play"></i> Sauvegarder maintenant</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('superadmin.sauvegardes.executer') }}">
                        @csrf
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="sans_fichiers" value="1" id="sansFichiers">
                            <label class="form-check-label small" for="sansFichiers">
                                Base de données seule
                                <br><span class="text-muted">Beaucoup plus rapide et léger, mais sans les pièces déposées.</span>
                            </label>
                        </div>
                        <button class="btn btn-primary w-100" data-loading-text="Sauvegarde en cours…">
                            <i class="fas fa-shield-halved"></i> Lancer une sauvegarde
                        </button>
                    </form>

                    @if ($file['sync'])
                        <div class="small text-muted mt-3">
                            <i class="fas fa-circle-info"></i>
                            La sauvegarde s'exécute pendant la requête : sur une base volumineuse,
                            la page peut mettre un moment à répondre.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header"><i class="fas fa-rotate-left"></i> Restaurer</div>
                <div class="card-body small" style="color:var(--t2);">
                    <ol class="ps-3 mb-0">
                        <li>Télécharger et décompresser l'archive.</li>
                        <li>Base : <code>mysql -u root -p dematerialisation &lt; base/dematerialisation.sql</code></li>
                        <li>Recopier <code>fichiers/</code> dans <code>storage/app/</code>.</li>
                        <li><code>php artisan optimize:clear</code></li>
                    </ol>
                    <div class="mt-2">La marche à suivre est aussi rappelée dans le
                        <code>MANIFESTE.txt</code> de chaque archive.</div>
                </div>
            </div>
        </div>

        {{-- ═══ Archives ═══ --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-box-archive"></i> Archives</span>
                    <small class="text-muted">purge automatique au-delà de {{ $retention }} jours</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Archive</th>
                                <th>Date</th>
                                <th>Taille</th>
                                <th>Contenu</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($archives as $a)
                                @php $baseSeule = str_contains($a['nom'], 'base-seule'); @endphp
                                <tr>
                                    <td><code class="small">{{ $a['nom'] }}</code></td>
                                    <td class="small">
                                        {{ $a['date']->format('d/m/Y H:i') }}<br>
                                        <span class="text-muted">{{ $a['date']->diffForHumans() }}</span>
                                    </td>
                                    <td class="small">{{ $service->formaterTaille($a['taille']) }}</td>
                                    <td>
                                        @if ($baseSeule)
                                            <span class="badge bg-secondary">Base seule</span>
                                        @else
                                            <span class="badge bg-success">Base + pièces</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-1 justify-content-center">
                                            <a href="{{ route('superadmin.sauvegardes.telecharger', $a['nom']) }}"
                                                class="btn btn-sm btn-outline-primary" title="Télécharger">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <form method="POST" action="{{ route('superadmin.sauvegardes.supprimer', $a['nom']) }}"
                                                onsubmit="return confirm('Supprimer définitivement cette archive ?');">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        <i class="fas fa-box-open d-block fs-2 mb-2"></i>
                                        Aucune archive pour le moment.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="alert alert-warning mt-3 small mb-0">
                <i class="fas fa-hard-drive"></i>
                <strong>Les archives sont sur le même serveur que les données.</strong>
                Elles protègent d'une suppression accidentelle ou d'une mauvaise manipulation,
                pas d'une panne de la machine. Copiez-les régulièrement ailleurs
                (disque externe, espace de stockage distant).
            </div>
        </div>
    </div>

@endsection
