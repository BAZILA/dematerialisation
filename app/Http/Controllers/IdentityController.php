<?php

namespace App\Http\Controllers;

use App\Exports\AnciensExport;
use App\Exports\BacheliersExport;
use App\Imports\AnciensImport;
use App\Imports\BacheliersImport;
use App\Models\AncienEtudiant;
use App\Models\Bachelier;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class IdentityController extends Controller
{
    public function index(Request $request)
    {
        $counts = [
            'bacheliers'            => Bachelier::count(),
            'bacheliers_registered' => Bachelier::where('is_registered', true)->count(),
            'anciens'               => AncienEtudiant::count(),
            'anciens_registered'    => AncienEtudiant::where('is_registered', true)->count(),
        ];

        $perPage = \App\Models\Setting::perPage();

        // Deux listes paginées indépendamment (pages « pb » et « pa »)
        $bacheliers = Bachelier::with('student.user')
            ->when($request->filled('qb'), fn ($q) => $this->rechercher($q, $request->qb, ['numero_releve', 'numero_table']))
            ->when($request->input('etat_b') === 'inscrits', fn ($q) => $q->where('is_registered', true))
            ->when($request->input('etat_b') === 'sans_compte', fn ($q) => $q->where('is_registered', false))
            ->orderBy('nom')->orderBy('prenom')
            ->paginate($perPage, ['*'], 'pb')->withQueryString();

        $anciens = AncienEtudiant::with('student.user')
            ->when($request->filled('qa'), fn ($q) => $this->rechercher($q, $request->qa, ['numero_carte']))
            ->when($request->input('etat_a') === 'inscrits', fn ($q) => $q->where('is_registered', true))
            ->when($request->input('etat_a') === 'sans_compte', fn ($q) => $q->where('is_registered', false))
            ->orderBy('nom')->orderBy('prenom')
            ->paginate($perPage, ['*'], 'pa')->withQueryString();

        return view('admin.identities.index', compact('counts', 'bacheliers', 'anciens'));
    }

    /** Recherche sur l'identité + les numéros propres au référentiel. */
    private function rechercher($query, string $terme, array $colonnes)
    {
        return $query->where(function ($q) use ($terme, $colonnes) {
            $q->where('nom', 'LIKE', "%{$terme}%")
              ->orWhere('prenom', 'LIKE', "%{$terme}%")
              ->orWhere('numero_suivi', 'LIKE', "%{$terme}%");
            foreach ($colonnes as $c) {
                $q->orWhere($c, 'LIKE', "%{$terme}%");
            }
        });
    }

    /* ── Ajout manuel : l'étudiant absent du fichier peut quand même déposer ── */

    public function storeBachelier(Request $request)
    {
        $data = $request->validate([
            'nom'            => 'required|string|max:100',
            'prenom'         => 'required|string|max:100',
            'sexe'           => 'required|in:M,F',
            'date_naissance' => 'required|date|before:today',
            'lieu_naissance' => 'required|string|max:150',
            'numero_suivi'   => 'nullable|string|max:50|unique:bacheliers,numero_suivi',
            'serie_bac'      => 'nullable|string|max:50',
            'annee_bac'      => 'nullable|string|max:10',
            'numero_releve'  => 'nullable|string|max:50|unique:bacheliers,numero_releve',
            'numero_table'   => 'nullable|string|max:50',
            'academie'       => 'nullable|string|max:100',
            'telephone'      => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:150',
        ], [
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'numero_suivi.unique'   => 'Ce numéro de suivi est déjà attribué à un autre bachelier.',
            'numero_releve.unique'  => 'Ce numéro de relevé est déjà attribué à un autre bachelier.',
        ], [
            'numero_suivi'   => 'numéro de suivi',
            'numero_releve'  => 'numéro de relevé',
            'numero_table'   => 'numéro de table',
            'date_naissance' => 'date de naissance',
            'lieu_naissance' => 'lieu de naissance',
        ]);

        if (blank($data['numero_suivi'] ?? null) && blank($data['numero_releve'] ?? null)) {
            return back()->withInput()->withErrors([
                'numero_suivi' => "Renseignez au moins le numéro de suivi ou le numéro de relevé : c'est la clé qui permettra à l'étudiant de se connecter.",
            ]);
        }

        $b = Bachelier::create($data + ['is_registered' => false]);

        \App\Models\AuditLog::record('identites.ajout_manuel', $b,
            "Bachelier ajouté manuellement : {$b->full_name} (suivi " . ($b->numero_suivi ?: '—') . ')',
            ['type' => 'bachelier', 'numero_suivi' => $b->numero_suivi]);

        return redirect()->route('admin.identites.index', ['tab' => 'bacheliers'])
            ->with('success', "« {$b->full_name} » ajouté au référentiel : il peut désormais créer son compte et déposer.");
    }

    public function storeAncien(Request $request)
    {
        $data = $request->validate([
            'nom'                 => 'required|string|max:100',
            'prenom'              => 'required|string|max:100',
            'sexe'                => 'required|in:M,F',
            'date_naissance'      => 'required|date|before:today',
            'lieu_naissance'      => 'required|string|max:150',
            'numero_carte'        => 'required|string|max:50|unique:anciens_etudiants,numero_carte',
            'numero_suivi'        => 'nullable|string|max:50',
            'specialite'          => 'nullable|string|max:150',
            'derniere_universite' => 'nullable|string|max:150',
            'telephone'           => 'nullable|string|max:30',
            'email'               => 'nullable|email|max:150',
            'adresse'             => 'nullable|string|max:255',
        ], [
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'numero_carte.unique'   => 'Ce numéro de carte est déjà attribué à un autre étudiant.',
        ], [
            'numero_carte'        => 'numéro de carte',
            'numero_suivi'        => 'numéro de suivi',
            'date_naissance'      => 'date de naissance',
            'lieu_naissance'      => 'lieu de naissance',
            'derniere_universite' => 'dernière université',
        ]);

        $a = AncienEtudiant::create($data + ['is_registered' => false]);

        \App\Models\AuditLog::record('identites.ajout_manuel', $a,
            "Ancien étudiant ajouté manuellement : {$a->full_name} (carte {$a->numero_carte})",
            ['type' => 'ancien', 'numero_carte' => $a->numero_carte, 'numero_suivi' => $a->numero_suivi]);

        return redirect()->route('admin.identites.index', ['tab' => 'anciens'])
            ->with('success', "« {$a->full_name} » ajouté au référentiel : il peut désormais créer son compte et déposer.");
    }

    /* ── Suppression : impossible dès qu'un compte est rattaché ── */

    public function destroyBachelier(Bachelier $bachelier)
    {
        if ($bachelier->is_registered || $bachelier->student) {
            return back()->withErrors(
                "« {$bachelier->full_name} » a déjà un compte étudiant : supprimez le compte avant de retirer son identité."
            );
        }
        $nom = $bachelier->full_name;
        $bachelier->delete();

        \App\Models\AuditLog::record('identites.suppression', null, "Bachelier supprimé : {$nom}");

        return back()->with('success', "« {$nom} » retiré du référentiel.");
    }

    public function destroyAncien(AncienEtudiant $ancien)
    {
        if ($ancien->is_registered || $ancien->student) {
            return back()->withErrors(
                "« {$ancien->full_name} » a déjà un compte étudiant : supprimez le compte avant de retirer son identité."
            );
        }
        $nom = $ancien->full_name;
        $ancien->delete();

        \App\Models\AuditLog::record('identites.suppression', null, "Ancien étudiant supprimé : {$nom}");

        return back()->with('success', "« {$nom} » retiré du référentiel.");
    }

    /* ── Bacheliers ───────────────────────────────────────── */

    public function importBacheliers(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240']);
        $import = new BacheliersImport();
        Excel::import($import, $request->file('file'));

        return $this->flashResult($import);
    }

    public function exportBacheliers()
    {
        return Excel::download(new BacheliersExport(), 'bacheliers_' . date('Ymd_His') . '.xlsx');
    }

    public function templateBacheliers()
    {
        return Excel::download(new BacheliersExport(true), 'modele_bacheliers.xlsx');
    }

    /* ── Anciens étudiants ────────────────────────────────── */

    public function importAnciens(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240']);
        $import = new AnciensImport();
        Excel::import($import, $request->file('file'));

        return $this->flashResult($import);
    }

    public function exportAnciens()
    {
        return Excel::download(new AnciensExport(), 'anciens_etudiants_' . date('Ymd_His') . '.xlsx');
    }

    public function templateAnciens()
    {
        return Excel::download(new AnciensExport(true), 'modele_anciens_etudiants.xlsx');
    }

    /* ── Résumé d'import ──────────────────────────────────── */

    private function flashResult($import)
    {
        $msg = "Import terminé : {$import->created} ajouté(s), {$import->updated} mis à jour, {$import->skipped} ignoré(s).";
        if (!empty($import->migres)) {
            $msg .= " {$import->migres} étudiant(s) « nouveau » migré(s) automatiquement vers « ancien » (même n° de suivi) — leurs comptes et historiques sont conservés.";
        }
        $redirect = redirect()->route('admin.identites.index');

        if (!empty($import->errors)) {
            return $redirect->with('info', $msg)
                ->with('import_errors', array_slice($import->errors, 0, 25));
        }

        return $redirect->with('success', $msg);
    }
}
