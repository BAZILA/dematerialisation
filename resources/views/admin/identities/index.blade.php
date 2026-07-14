@extends('layouts.app')
@section('page-title', 'Identités — Import / Export')
@section('page-subtitle', 'Référentiel des bacheliers et anciens étudiants (Excel / CSV)')

@section('content')
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

    <div class="alert alert-info mt-4 mb-0" style="font-size:12.5px;">
        <i class="fas fa-circle-info"></i>
        L'import fait un <strong>dédoublonnage</strong> sur la clé unique
        (<code>numero_releve</code> pour les bacheliers, <code>numero_carte</code> pour les anciens) :
        les enregistrements existants sont <strong>mis à jour</strong>, les nouveaux <strong>ajoutés</strong>.
        Les lignes incomplètes sont ignorées et listées.
    </div>
@endsection
