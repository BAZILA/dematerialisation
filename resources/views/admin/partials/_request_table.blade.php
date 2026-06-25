@php
    $statusColors = [
        'brouillon' => 'secondary',
        'soumis' => 'warning',
        'en_correction' => 'info',
        'validé' => 'success',
        'rejeté' => 'danger',
    ];
    $statusLabels = [
        'brouillon' => 'Brouillon',
        'soumis' => 'En attente',
        'en_correction' => 'En correction',
        'validé' => 'Validé',
        'rejeté' => 'Rejeté',
    ];
@endphp

<div class="card border-0" style="border-top-left-radius:0;border-top-right-radius:0;">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Étudiant</th>
                    <th>Année</th>
                    <th>Fichiers</th>
                    <th>Soumis le</th>
                    <th>Statut</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $req)
                    <tr>
                        <td>
                            <strong>{{ $req->student->full_name }}</strong><br>
                            <code class="small">{{ $req->student->user->numero_identifiant }}</code>
                        </td>
                        <td><small>{{ $req->academic_year }}</small></td>
                        <td><span class="badge bg-secondary"><i class="fas fa-paperclip"></i>
                                {{ $req->files->count() }}</span></td>
                        <td>
                            @if ($req->submitted_at)
                                <small>{{ $req->submitted_at->format('d/m/Y') }}</small>
                            @else
                                <span class="text-muted ">—</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $statusColors[$req->status] ?? 'secondary' }}">
                                {{ $statusLabels[$req->status] ?? $req->status }}
                            </span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('admin.requests.show', $req) }}" class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-inbox"></i> Aucune demande
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($items instanceof \Illuminate\Pagination\LengthAwarePaginator && $items->hasPages())
        <div class="card-body py-2">
            {{ $items->links('pagination::bootstrap-5') }}
        </div>
    @endif
    @if ($items->count() > 0)
        <div class="card-body py-2 border-top text-end">
            <a href="{{ route('admin.requests.index', $category) }}" class="btn btn-sm btn-outline-secondary">
                Voir tout <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    @endif
</div>
