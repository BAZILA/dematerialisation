@extends('layouts.app')
@section('page-title', "Journal d'audit")
@section('page-subtitle', 'Traçabilité des actions sensibles (lecture seule)')

@section('content')
    {{-- Filtres --}}
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Action</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">Toutes</option>
                        @foreach ($actions as $a)
                            <option value="{{ $a }}" @selected(request('action') === $a)>
                                {{ \App\Models\AuditLog::$labels[$a] ?? $a }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Auteur</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">Tous</option>
                        @foreach ($acteurs as $u)
                            <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>
                                {{ $u->staff_name }} ({{ $u->numero_identifiant }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Du</label>
                    <input type="date" name="date_debut" class="form-control form-control-sm" value="{{ request('date_debut') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Au</label>
                    <input type="date" name="date_fin" class="form-control form-control-sm" value="{{ request('date_fin') }}">
                </div>
                <div class="col-md-2 filtre-actions">
                    <button class="btn btn-primary btn-filtrer"><i class="fas fa-filter"></i> Filtrer</button>
                    <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary btn-reset"
                        title="Réinitialiser"><i class="fas fa-times"></i></a>
                </div>
                <div class="col-12">
                    <input type="text" name="search" class="form-control form-control-sm mt-1"
                        placeholder="Rechercher dans les descriptions…" value="{{ request('search') }}">
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span style="font-weight:600;color:var(--t1);"><i class="fas fa-clipboard-list"></i> Journal</span>
            <div class="d-flex align-items-center gap-3">
                <small class="text-muted">{{ $logs->total() }} entrée(s)</small>
                @php
                    // L'export reprend exactement les filtres de l'écran
                    $qs = request()->getQueryString();
                    $qs = $qs ? '?' . $qs : '';
                @endphp
                <div class="btn-group btn-group-sm" role="group" aria-label="Exporter le journal">
                    <a href="{{ route('admin.audit.export', 'excel') }}{{ $qs }}" class="btn btn-outline-success"
                        title="Exporter au format Excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                    <a href="{{ route('admin.audit.export', 'csv') }}{{ $qs }}" class="btn btn-outline-secondary"
                        title="Exporter au format CSV">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                    <a href="{{ route('admin.audit.export', 'pdf') }}{{ $qs }}" class="btn btn-outline-danger"
                        title="Rapport PDF avec en-tête de l'université">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Date &amp; heure</th>
                        <th>Auteur</th>
                        <th>Action</th>
                        <th>Détail</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="small" style="white-space:nowrap;">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                            <td class="small">
                                @if ($log->user)
                                    <strong>{{ $log->user->staff_name }}</strong><br>
                                    <span class="text-muted">{{ $log->user->numero_identifiant }}</span>
                                @else
                                    <span class="text-muted">Système / anonyme</span>
                                @endif
                            </td>
                            <td><span class="badge bg-secondary">{{ $log->action_label }}</span></td>
                            <td class="small">{{ $log->description }}</td>
                            <td class="small text-muted">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="fas fa-clipboard d-block fs-2 mb-2" style="opacity:.35;"></i>
                                Aucune entrée pour ces critères.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-4">{{ $logs->links('pagination::bootstrap-5') }}</div>
@endsection
