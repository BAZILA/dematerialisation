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
            'cloner_de'  => 'nullable|exists:annees_academiques,id',
        ]);

        $annee = AnneeAcademique::create(collect($data)->except('cloner_de')->all());

        $msg = 'Année académique ajoutée.';

        // Clonage des paramètres depuis une année source
        if (!empty($data['cloner_de'])) {
            $source = AnneeAcademique::with('periodes')->find($data['cloner_de']);
            if ($source) {
                $n = $this->clonerParametres($source, $annee);
                $msg .= " {$n} paramètre(s) clonés depuis {$source->libelle}.";
            }
        }

        return back()->with('success', $msg);
    }

    /**
     * Clone les paramètres d'une année vers une autre :
     *  - les fenêtres d'ouverture par opération (dates + état) ;
     *  - (les réglages globaux — cachet, signatures — restent partagés).
     */
    private function clonerParametres(AnneeAcademique $source, AnneeAcademique $cible): int
    {
        $n = 0;
        foreach ($source->periodes as $p) {
            Periode::updateOrCreate(
                ['annee_academique_id' => $cible->id, 'operation' => $p->operation],
                [
                    'date_debut' => $p->date_debut,
                    'date_fin'   => $p->date_fin,
                    'is_open'    => $p->is_open,
                ]
            );
            $n++;
        }

        return $n;
    }

    /** Clonage à la demande depuis la fiche d'une année existante. */
    public function cloner(Request $request, AnneeAcademique $annee)
    {
        $request->validate(['source_id' => 'required|exists:annees_academiques,id|different:' . $annee->id]);

        $source = AnneeAcademique::with('periodes')->findOrFail($request->source_id);
        $n = $this->clonerParametres($source, $annee);

        return back()->with('success', "{$n} paramètre(s) clonés depuis {$source->libelle} vers {$annee->libelle}.");
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

        \App\Models\AuditLog::record('annee.activee', $annee, "Année active : {$annee->libelle}");

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
