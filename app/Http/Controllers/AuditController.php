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

        $logs = $query->paginate(\App\Models\Setting::perPage(40))->withQueryString();

        // Listes pour les filtres
        $actions = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $acteurs = User::whereIn('id', AuditLog::select('user_id')->distinct()->whereNotNull('user_id'))
            ->orderBy('nom')->orderBy('numero_identifiant')->get();

        return view('admin.audit.index', compact('logs', 'actions', 'acteurs'));
    }
}
