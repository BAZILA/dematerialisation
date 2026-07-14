<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Periode;
use Illuminate\Http\Request;

class AnneeController extends Controller
{
    public function index()
    {
        $annees = AnneeAcademique::with('periodes')->orderByDesc('libelle')->get();

        return view('admin.annees.index', compact('annees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'libelle'    => 'required|string|max:20|unique:annees_academiques,libelle',
            'date_debut' => 'nullable|date',
            'date_fin'   => 'nullable|date|after_or_equal:date_debut',
            'statut'     => 'required|in:en_preparation,ouverte,cloturee,archivee',
        ]);
        AnneeAcademique::create($data);

        return back()->with('success', 'Année académique ajoutée.');
    }

    public function update(Request $request, AnneeAcademique $annee)
    {
        $data = $request->validate([
            'libelle'    => 'required|string|max:20|unique:annees_academiques,libelle,' . $annee->id,
            'date_debut' => 'nullable|date',
            'date_fin'   => 'nullable|date|after_or_equal:date_debut',
            'statut'     => 'required|in:en_preparation,ouverte,cloturee,archivee',
        ]);
        $annee->update($data);

        return back()->with('success', 'Année académique mise à jour.');
    }

    /** Définit l'année de contexte courante (une seule active). */
    public function activate(AnneeAcademique $annee)
    {
        AnneeAcademique::query()->update(['is_active' => false]);
        $annee->update(['is_active' => true]);

        return back()->with('success', "Année active : {$annee->libelle}.");
    }

    public function destroy(AnneeAcademique $annee)
    {
        if ($annee->is_active) {
            return back()->withErrors("Impossible de supprimer l'année active.");
        }
        $annee->delete();

        return back()->with('success', 'Année académique supprimée.');
    }

    /* ── Périodes (fenêtres d'ouverture par opération) ─────── */

    public function savePeriode(Request $request, AnneeAcademique $annee)
    {
        $data = $request->validate([
            'operation'  => 'required|in:candidature,depot,attestation,releve,diplome,duplicat,homologation',
            'date_debut' => 'nullable|date',
            'date_fin'   => 'nullable|date|after_or_equal:date_debut',
        ]);

        Periode::updateOrCreate(
            ['annee_academique_id' => $annee->id, 'operation' => $data['operation']],
            [
                'date_debut' => $data['date_debut'] ?? null,
                'date_fin'   => $data['date_fin'] ?? null,
                'is_open'    => $request->boolean('is_open'),
            ]
        );

        return back()->with('success', 'Fenêtre enregistrée.');
    }

    public function destroyPeriode(Periode $periode)
    {
        $periode->delete();

        return back()->with('success', 'Fenêtre supprimée.');
    }
}
