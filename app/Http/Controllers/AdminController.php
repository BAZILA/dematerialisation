<?php

namespace App\Http\Controllers;

use App\Models\RequestType;
use App\Models\StudentRequest;
use App\Models\Student;

class AdminController extends Controller
{
    public function dashboard()
    {
        $cats = array_keys(RequestType::$categories);

        /* ── Stats ── toutes les clés utilisées par la vue ─── */
        $stats = [
            // Globaux
            'total' => StudentRequest::count(),
            'students' => Student::count(),
            'validated_today' => StudentRequest::where('status', 'validé')
                ->whereDate('validated_at', today())->count(),
            'in_correction' => StudentRequest::where('status', 'en_correction')->count(),

            // Clés attendues par admin/dashboard.blade.php
            'depot_pending' => StudentRequest::where('category', 'depot')
                ->where('status', 'soumis')->count(),
            'requests_pending' => StudentRequest::where('category', '!=', 'depot')
                ->where('status', 'soumis')->count(),
        ];

        // Badge par catégorie dans les onglets : pending_depot, pending_attestation …
        foreach ($cats as $cat) {
            $stats["pending_{$cat}"] = StudentRequest::where('category', $cat)
                ->where('status', 'soumis')->count();
        }

        /* ── Données graphiques ─────────────────────────────── */
        $chartData = [
            'by_category' => collect($cats)->map(fn($c) => [
                'key' => $c,
                'label' => RequestType::$categories[$c],
                'total' => StudentRequest::where('category', $c)->count(),
            ])->values()->toArray(),

            'by_status' => [
                ['label' => 'Brouillon', 'count' => StudentRequest::where('status', 'brouillon')->count()],
                ['label' => 'En attente', 'count' => StudentRequest::where('status', 'soumis')->count()],
                ['label' => 'En correction', 'count' => StudentRequest::where('status', 'en_correction')->count()],
                ['label' => 'Validé', 'count' => StudentRequest::where('status', 'validé')->count()],
                ['label' => 'Rejeté', 'count' => StudentRequest::where('status', 'rejeté')->count()],
            ],
        ];

        /* ── Listes récentes pour les onglets du dashboard ──── */
        $recentDeposits = StudentRequest::with('student.user', 'files')
            ->where('category', 'depot')
            ->orderByRaw("CASE status
                WHEN 'soumis'        THEN 0
                WHEN 'en_correction' THEN 1
                WHEN 'brouillon'     THEN 2
                WHEN 'validé'        THEN 3
                WHEN 'rejeté'        THEN 4
                ELSE 5 END")
            ->orderBy('submitted_at', 'desc')
            ->take(10)->get();

        $recentRequests = [];
        foreach ($cats as $cat) {
            if ($cat === 'depot')
                continue;
            $recentRequests[$cat] = StudentRequest::with('student.user', 'files')
                ->where('category', $cat)
                ->orderByRaw("CASE status
                    WHEN 'soumis'        THEN 0
                    WHEN 'en_correction' THEN 1
                    WHEN 'brouillon'     THEN 2
                    WHEN 'validé'        THEN 3
                    WHEN 'rejeté'        THEN 4
                    ELSE 5 END")
                ->orderBy('submitted_at', 'desc')
                ->take(10)->get();
        }

        return view('admin.dashboard', compact(
            'stats',
            'chartData',
            'recentDeposits',
            'recentRequests'
        ));
    }
}
