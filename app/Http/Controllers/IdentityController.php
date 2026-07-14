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
    public function index()
    {
        $counts = [
            'bacheliers'            => Bachelier::count(),
            'bacheliers_registered' => Bachelier::where('is_registered', true)->count(),
            'anciens'               => AncienEtudiant::count(),
            'anciens_registered'    => AncienEtudiant::where('is_registered', true)->count(),
        ];

        return view('admin.identities.index', compact('counts'));
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
        $redirect = redirect()->route('admin.identites.index');

        if (!empty($import->errors)) {
            return $redirect->with('info', $msg)
                ->with('import_errors', array_slice($import->errors, 0, 25));
        }

        return $redirect->with('success', $msg);
    }
}
