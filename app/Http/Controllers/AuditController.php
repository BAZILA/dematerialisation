<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Consultation du journal d'audit (permission audit.consulter).
 * Lecture seule : le journal est immuable.
 */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = $this->base($request)->paginate(\App\Models\Setting::perPage(40))->withQueryString();

        // Listes pour les filtres
        $actions = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $acteurs = User::whereIn('id', AuditLog::select('user_id')->distinct()->whereNotNull('user_id'))
            ->orderBy('nom')->orderBy('numero_identifiant')->get();

        return view('admin.audit.index', compact('logs', 'actions', 'acteurs'));
    }

    /** Requête filtrée, partagée par l'affichage et les exports. */
    private function base(Request $request)
    {
        $query = AuditLog::with('user')->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('date_debut')) {
            $query->whereDate('created_at', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('created_at', '<=', $request->date_fin);
        }
        if ($request->filled('search')) {
            $query->where('description', 'LIKE', '%' . $request->search . '%');
        }

        return $query;
    }

    /**
     * Export du journal : avec les filtres en cours, ou l'intégralité
     * si aucun n'est posé. Plafonné pour ne pas saturer la mémoire.
     */
    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['excel', 'csv', 'pdf'], true), 404);

        $plafond = 20000;
        $total   = $this->base($request)->count();
        $rows    = $this->base($request)->limit($plafond)->get();

        $filtres = $this->filtresLisibles($request);

        // Nom de fichier lisible : action / acteur filtrés, s'il y en a
        $contexte = [
            $request->filled('action') ? $request->action : null,
            $request->filled('date_debut') ? 'depuis-' . $request->date_debut : null,
        ];

        AuditLog::record('audit.export', null,
            "Export du journal d'audit ({$format}) — {$rows->count()} entrée(s)"
                . ($total > $plafond ? " sur {$total} (plafonné)" : ''),
            ['format' => $format, 'filtres' => $filtres, 'lignes' => $rows->count()]);

        if ($format === 'pdf') {
            $pdf = app(\App\Services\AuditPdfService::class)->build(
                $rows,
                $filtres . ($total > $plafond
                    ? "   ·   Export limité aux {$plafond} entrées les plus récentes sur {$total}" : ''),
                $request->user()->staff_name
            );

            $nom = \App\Support\NomExport::build('audit', $contexte, 'pdf');

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $nom . '"',
            ]);
        }

        $ext    = $format === 'csv' ? 'csv' : 'xlsx';
        $writer = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\AuditExport($rows),
            \App\Support\NomExport::build('audit', $contexte, $ext),
            $writer
        );
    }

    /** Filtres appliqués, en clair, pour l'en-tête du rapport. */
    private function filtresLisibles(Request $request): string
    {
        $f = [];

        if ($request->filled('action')) {
            $f[] = 'Action : ' . $request->action;
        }
        if ($request->filled('user_id')) {
            $u = User::find($request->user_id);
            $f[] = 'Acteur : ' . ($u?->staff_name ?? $request->user_id);
        }
        if ($request->filled('date_debut')) {
            $f[] = 'Du ' . \Carbon\Carbon::parse($request->date_debut)->format('d/m/Y');
        }
        if ($request->filled('date_fin')) {
            $f[] = 'Au ' . \Carbon\Carbon::parse($request->date_fin)->format('d/m/Y');
        }
        if ($request->filled('search')) {
            $f[] = 'Recherche : « ' . $request->search . ' »';
        }

        return $f ? implode('   ·   ', $f) : 'Journal complet — aucun filtre appliqué';
    }
}
