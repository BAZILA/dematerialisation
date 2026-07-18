@extends('layouts.app')
@section('page-title', 'Archives numériques')
@section('page-subtitle', ($annee ? "Année {$annee->libelle}" : 'Toutes années') . ' — dossiers de dépôt validés')

@section('content')
    <div class="alert alert-info small d-flex align-items-start gap-2">
        <i class="fas fa-box-archive mt-1"></i>
        <span>Archive des <strong>dossiers de dépôt validés</strong> de l'année consultée. Vous pouvez
            télécharger une <strong>fiche</strong>, le <strong>dossier complet</strong> d'un étudiant,
            une <strong>sélection</strong>, ou <strong>tout le résultat filtré</strong> (parcours, grade,
            plusieurs semestres…). Les fiches tamponnées sont fournies dans leur version officielle.</span>
    </div>

    {{-- ═══ Filtres ═══ --}}
    <form method="GET" class="card mb-3" id="filtres">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Faculté / Institut</label>
                    <select name="faculte_id" class="form-select form-select-sm" onchange="document.getElementById('filtres').submit()">
                        <option value="">Toutes</option>
                        @foreach ($facultes as $f)
                            <option value="{{ $f->id }}" @selected(request('faculte_id') == $f->id)>{{ $f->nom }}</option>
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
                <div class="col-md-2">
                    <label class="form-label small">Grade / Niveau</label>
                    <select name="grade" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        @foreach ($grades as $k => $g)
                            <option value="{{ $k }}" @selected(request('grade') === $k)>{{ $g['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Recherche</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                        placeholder="Nom, prénom, identifiant…" value="{{ request('search') }}">
                </div>
            </div>

            {{-- Semestres multiples --}}
            <div class="mt-3 p-3 rounded" style="background:var(--surface-2);border:1px solid var(--line);">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <label class="form-label small mb-0">Semestres <span class="text-muted">(plusieurs possibles — aucun coché = tous)</span></label>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="semAll">Tout cocher</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="semNone">Tout décocher</button>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-3" id="semList">
                    @php $selSems = (array) request('semestres', []); @endphp
                    @foreach (['S1','S2','S3','S4','S5','S6','Année 1','Année 2','Année 3'] as $s)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="semestres[]" value="{{ $s }}"
                                id="f-sem-{{ $loop->index }}" @checked(in_array($s, $selSems))>
                            <label class="form-check-label small" for="f-sem-{{ $loop->index }}">{{ $s }}</label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filtrer</button>
                <a href="{{ route('admin.archives.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times"></i> Réinitialiser</a>
            </div>
        </div>
    </form>

    {{-- ═══ Résultats + actions de lot ═══ --}}
    <form method="POST" action="{{ route('admin.archives.lot') }}" id="lotForm">
        @csrf
        {{-- On reporte les filtres courants pour « tout télécharger » --}}
        @foreach (request()->except(['_token', 'page', 'ids']) as $k => $v)
            @if (is_array($v))
                @foreach ($v as $vv)<input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">@endforeach
            @else
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endif
        @endforeach

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span style="font-weight:600;color:var(--t1);">
                    <i class="fas fa-folder-tree"></i> {{ $total }} dossier(s) archivé(s)
                </span>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-success" data-loading-text="Préparation…">
                        <i class="fas fa-file-zipper"></i> Télécharger la sélection
                    </button>
                    <button type="submit" class="btn btn-sm btn-outline-primary" name="all" value="1"
                        onclick="document.querySelectorAll('.chk-dossier').forEach(c=>c.checked=false);"
                        data-loading-text="Préparation…">
                        <i class="fas fa-download"></i> Tout le résultat filtré
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width:34px;"><input type="checkbox" class="form-check-input" id="chkAll"></th>
                            <th>Étudiant</th>
                            <th>N° suivi</th>
                            <th>Parcours</th>
                            <th>Sem.</th>
                            <th>Pièces</th>
                            <th>Validé le</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dossiers as $d)
                            <tr>
                                <td><input type="checkbox" class="form-check-input chk-dossier" name="ids[]" value="{{ $d->id }}"></td>
                                <td>
                                    <strong>{{ $d->student->nom ?? '' }} {{ $d->student->prenom ?? '' }}</strong><br>
                                    <small class="text-muted">{{ $d->student->user->numero_identifiant ?? '—' }}</small>
                                </td>
                                <td><code class="small">{{ $d->student->numero_suivi ?? '—' }}</code></td>
                                <td class="small">
                                    {{ $d->parcours->nom ?? '—' }}<br>
                                    <small class="text-muted">{{ $d->parcours->departement->faculte->nom ?? '' }}</small>
                                </td>
                                <td><span class="badge bg-secondary">{{ $d->semestre ?? '—' }}</span></td>
                                <td>
                                    <span class="badge bg-secondary"><i class="fas fa-paperclip"></i> {{ $d->files->count() }}</span>
                                    @php $st = $d->files->where('is_stamped', true)->count(); @endphp
                                    @if ($st)<span class="badge bg-primary"><i class="fas fa-stamp"></i> {{ $st }}</span>@endif
                                </td>
                                <td class="small">{{ optional($d->validated_at)->format('d/m/Y') }}</td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.archives.dossier', $d) }}" class="btn btn-outline-primary"
                                            title="Télécharger le dossier complet (ZIP)">
                                            <i class="fas fa-file-zipper"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse"
                                            data-bs-target="#fic-{{ $d->id }}" title="Voir les fiches">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr class="collapse" id="fic-{{ $d->id }}">
                                <td colspan="8" style="background:var(--surface-2);">
                                    <div class="d-flex flex-wrap gap-2 py-2">
                                        @forelse ($d->files as $f)
                                            <a href="{{ route('admin.archives.fiche', $f) }}"
                                                class="btn btn-sm {{ $f->is_stamped ? 'btn-primary' : 'btn-outline-secondary' }}">
                                                <i class="fas {{ $f->is_stamped ? 'fa-stamp' : 'fa-file' }}"></i>
                                                {{ $f->requestType->label ?? 'Document' }}
                                                <i class="fas fa-download ms-1"></i>
                                            </a>
                                        @empty
                                            <span class="text-muted small">Aucune pièce.</span>
                                        @endforelse
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="fas fa-box-open d-block fs-2 mb-2" style="opacity:.35;"></i>
                                    Aucun dossier validé pour ces critères.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-center mt-4">{{ $dossiers->links('pagination::bootstrap-5') }}</div>
@endsection

@section('js')
    <script>
        (function () {
            const all = document.getElementById('chkAll');
            if (all) all.addEventListener('change', function () {
                document.querySelectorAll('.chk-dossier').forEach(c => c.checked = this.checked);
            });
            document.getElementById('semAll')?.addEventListener('click', () =>
                document.querySelectorAll('#semList input').forEach(c => c.checked = true));
            document.getElementById('semNone')?.addEventListener('click', () =>
                document.querySelectorAll('#semList input').forEach(c => c.checked = false));
        })();
    </script>
@endsection
