<?php

namespace App\Http\Controllers;

use App\Models\AgentPerimetre;
use App\Models\Faculte;
use App\Models\Parcours;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Affectation des périmètres (parcours × semestre) aux agents.
 * Défaut = tout ; une affectation restreint l'agent à son périmètre.
 */
class PerimetreController extends Controller
{
    public function index()
    {
        // Agents concernés (le super-admin couvre tout par définition → exclu)
        $agents = User::whereNotIn('role', ['etudiant', 'candidat', 'super_admin'])
            ->with('perimetres.parcours.departement.faculte')
            ->orderBy('nom')->orderBy('numero_identifiant')
            ->paginate(\App\Models\Setting::perPage())->withQueryString();

        $facultes = Faculte::where('is_active', true)
            ->with(['departements' => fn ($q) => $q->where('is_active', true)
                ->with(['parcours' => fn ($q) => $q->where('is_active', true)->orderBy('nom')])])
            ->orderBy('nom')->get();

        $grades = Parcours::$grades;

        return view('admin.perimetres.index', compact('agents', 'facultes', 'grades'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'     => 'required|exists:users,id',
            'parcours_id' => 'required|exists:parcours,id',
            'semestres'   => 'nullable|array',
            'semestres.*' => 'nullable|string|max:20',
        ]);

        $user = User::findOrFail($data['user_id']);
        abort_if(in_array($user->role, ['etudiant', 'candidat']), 403);

        // Aucun semestre coché = tous les semestres (une seule ligne, semestre = null)
        $semestres = array_values(array_filter($data['semestres'] ?? [], fn ($s) => filled($s)));
        if (empty($semestres)) {
            $semestres = [null];
        }

        $n = 0;
        foreach ($semestres as $s) {
            $p = AgentPerimetre::firstOrCreate([
                'user_id'     => $data['user_id'],
                'parcours_id' => $data['parcours_id'],
                'semestre'    => $s,
            ]);
            if ($p->wasRecentlyCreated) {
                $n++;
            }
        }

        \App\Models\AuditLog::record('perimetre.affecte', $user,
            "Périmètre affecté à {$user->staff_name}",
            ['parcours_id' => $data['parcours_id'], 'semestres' => $semestres]);

        return back()->with('success', $n > 1
            ? "$n périmètres affectés."
            : ($n === 1 ? 'Périmètre affecté.' : 'Ce périmètre existait déjà.'));
    }

    public function destroy(AgentPerimetre $perimetre)
    {
        $u = $perimetre->user;
        $perimetre->delete();

        \App\Models\AuditLog::record('perimetre.retire', $u,
            "Périmètre retiré à " . ($u->staff_name ?? '—'));

        return back()->with('success', 'Affectation retirée.');
    }
}
