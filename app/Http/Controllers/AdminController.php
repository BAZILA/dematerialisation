<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\RequestType;
use App\Models\StudentRequest;
use App\Models\Student;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /** Change l'année académique de contexte (consultation). */
    public function switchAnnee(Request $request)
    {
        $request->validate(['annee_id' => 'required|exists:annees_academiques,id']);
        session(['annee_contexte_id' => (int) $request->annee_id]);

        return back();
    }

    public function dashboard()
    {
        $user = auth()->user();

        // Un agent ne voit que les catégories qu'il peut traiter ;
        // un superviseur (contrôle / consultation des dossiers) voit tout.
        $toutes = array_keys(RequestType::categories());
        $traitables = $user->treatableCategories();
        $cats = $traitables
            ? array_values(array_intersect($toutes, $traitables))
            : ($user->canAny(['demandes.controler', 'dossiers.consulter']) ? $toutes : []);

        // ── Tout le tableau de bord est borné à l'ANNÉE DE CONTEXTE
        //    ET au PÉRIMÈTRE de l'agent connecté ──
        $annee = AnneeAcademique::context();
        $base = function () use ($annee, $user, $cats) {
            $q = StudentRequest::query()
                ->when($annee, fn ($qq) => $qq->where('annee_academique_id', $annee->id))
                ->whereIn('category', $cats ?: ['__aucune__']);
            $user->applyPerimeterFilter($q);

            return $q;
        };

        /* ── Stats ── toutes les clés utilisées par la vue ─── */
        $stats = [
            'total' => $base()->count(),
            'students' => Student::count(),
            'validated_today' => $base()->where('status', 'validé')
                ->whereDate('validated_at', today())->count(),
            'in_correction' => $base()->where('status', 'en_correction')->count(),

            // Clés attendues par admin/dashboard.blade.php
            'depot_pending' => $base()->where('category', 'depot')
                ->where('status', 'soumis')->count(),
            'requests_pending' => $base()->where('category', '!=', 'depot')
                ->where('status', 'soumis')->count(),
        ];

        // Badge par catégorie dans les onglets : pending_depot, pending_attestation …
        $pendings = $base()->where('status', 'soumis')
            ->selectRaw('category, COUNT(*) as n')->groupBy('category')->pluck('n', 'category');
        foreach ($cats as $cat) {
            $stats["pending_{$cat}"] = (int) ($pendings[$cat] ?? 0);
        }

        /* ── Données graphiques ─────────────────────────────── */
        $parCategorie = $base()->selectRaw('category, COUNT(*) as n')->groupBy('category')->pluck('n', 'category');
        $parStatut = $base()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $chartData = [
            'by_category' => collect($cats)->map(fn ($c) => [
                'key' => $c,
                'label' => RequestType::categories()[$c],
                'total' => (int) ($parCategorie[$c] ?? 0),
            ])->values()->toArray(),

            'by_status' => [
                ['label' => 'Brouillon', 'count' => (int) ($parStatut['brouillon'] ?? 0)],
                ['label' => 'En attente', 'count' => (int) ($parStatut['soumis'] ?? 0)],
                ['label' => 'En correction', 'count' => (int) ($parStatut['en_correction'] ?? 0)],
                ['label' => 'Validé', 'count' => (int) ($parStatut['validé'] ?? 0)],
                ['label' => 'Rejeté', 'count' => (int) ($parStatut['rejeté'] ?? 0)],
            ],

            // Courbe : soumissions par mois de l'année consultée
            'by_month' => $base()->whereNotNull('submitted_at')
                ->selectRaw("DATE_FORMAT(submitted_at, '%Y-%m') as mois, COUNT(*) as n")
                ->groupBy('mois')->orderBy('mois')->pluck('n', 'mois')->toArray(),
        ];

        /* ── Listes récentes pour les onglets du dashboard ──── */
        $ordreStatuts = "CASE status
                WHEN 'soumis'        THEN 0
                WHEN 'en_correction' THEN 1
                WHEN 'brouillon'     THEN 2
                WHEN 'validé'        THEN 3
                WHEN 'rejeté'        THEN 4
                ELSE 5 END";

        $recentDeposits = $base()->with('student.user', 'files')
            ->where('category', 'depot')
            ->orderByRaw($ordreStatuts)
            ->orderBy('submitted_at', 'desc')
            ->take(10)->get();

        $recentRequests = [];
        foreach ($cats as $cat) {
            if ($cat === 'depot')
                continue;
            $recentRequests[$cat] = $base()->with('student.user', 'files')
                ->where('category', $cat)
                ->orderByRaw($ordreStatuts)
                ->orderBy('submitted_at', 'desc')
                ->take(10)->get();
        }

        return view('admin.dashboard', compact(
            'stats',
            'chartData',
            'recentDeposits',
            'recentRequests',
            'annee'
        ));
    }
}
