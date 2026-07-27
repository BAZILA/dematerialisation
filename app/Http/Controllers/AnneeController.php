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
        $msg .= $this->messageChevauchement($annee);

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

        return back()->with('success', 'Année académique mise à jour.' . $this->messageChevauchement($annee));
    }

    /**
     * Message personnalisé si les dates de l'année chevauchent une autre
     * année existante (informatif — ne bloque pas).
     */
    private function messageChevauchement(AnneeAcademique $annee): string
    {
        if (!$annee->date_debut || !$annee->date_fin) {
            return '';
        }

        $conflits = AnneeAcademique::where('id', '<>', $annee->id)
            ->whereNotNull('date_debut')->whereNotNull('date_fin')
            ->where('date_debut', '<=', $annee->date_fin)
            ->where('date_fin', '>=', $annee->date_debut)
            ->get();

        if ($conflits->isEmpty()) {
            return '';
        }

        $détail = $conflits->map(fn ($a) => "{$a->libelle} ({$a->date_debut->format('d/m/Y')} – {$a->date_fin->format('d/m/Y')})")
            ->implode(', ');

        return " ⚠ Attention : ses dates chevauchent " . ($conflits->count() > 1 ? 'les années ' : "l'année ")
            . $détail . '. Vérifiez que ce recouvrement est voulu.';
    }

    /** Définit l'année de contexte courante (une seule active). */
    public function activate(AnneeAcademique $annee)
    {
        AnneeAcademique::query()->update(['is_active' => false]);
        // Via le query builder : le modèle en mémoire peut déjà porter
        // is_active=true (ré-activation) et l'update Eloquent serait ignoré.
        AnneeAcademique::whereKey($annee->id)->update(['is_active' => true]);
        $annee->refresh();
        AnneeAcademique::flushMemo();

        \App\Models\AuditLog::record('annee.activee', $annee, "Année active : {$annee->libelle}");

        // ── BASCULE AUTOMATIQUE NOUVEAU → ANCIEN ──
        // Modèle opérationnel : les anciens ne sont chargés qu'au démarrage ;
        // ensuite seuls les nouveaux bacheliers sont importés chaque année.
        // Au passage à la nouvelle année, tout « nouveau » ayant un dépôt
        // VALIDÉ d'une année antérieure devient « ancien » (compte, historique
        // et n° de suivi conservés — le suivi reste la clé pivot).
        $n = $this->basculerNouveauxVersAnciens($annee);

        $msg = "Année active : {$annee->libelle}.";
        if ($n > 0) {
            $msg .= " {$n} étudiant(s) « nouveau » basculé(s) en « ancien » (dépôt validé d'une année antérieure).";
        }

        return back()->with('success', $msg);
    }

    /** Bascule les nouveaux ayant un dépôt validé AVANT l'année active. */
    private function basculerNouveauxVersAnciens(AnneeAcademique $active): int
    {
        $users = \App\Models\User::where('type_etudiant', 'nouveau')
            ->whereHas('student.requests', fn ($q) => $q
                ->where('category', 'depot')
                ->where('status', 'validé')
                ->where('annee_academique_id', '<>', $active->id))
            ->get();

        foreach ($users as $u) {
            $u->type_etudiant = 'ancien';
            $u->save();
        }

        if ($users->isNotEmpty()) {
            \App\Models\AuditLog::record('etudiants.bascule_anciens', $active,
                $users->count() . " nouveau(x) basculé(s) en ancien(s) à l'activation de {$active->libelle}",
                ['identifiants' => $users->pluck('numero_identifiant')->all()]);
        }

        return $users->count();
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
            // Toute procédure existante (y compris créées depuis l'interface)
            'operation'  => ['required', \Illuminate\Validation\Rule::exists('procedures', 'code')],
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

        // Message personnalisé si la fenêtre déborde des dates de l'année
        $msg = 'Fenêtre enregistrée.';
        $debut = !empty($data['date_debut']) ? \Carbon\Carbon::parse($data['date_debut']) : null;
        $fin   = !empty($data['date_fin']) ? \Carbon\Carbon::parse($data['date_fin']) : null;
        if ($annee->date_debut && $debut && $debut->lt($annee->date_debut)) {
            $msg .= " ⚠ Le début ({$debut->format('d/m/Y')}) est AVANT le début de l'année ({$annee->date_debut->format('d/m/Y')}).";
        }
        if ($annee->date_fin && $fin && $fin->gt($annee->date_fin)) {
            $msg .= " ⚠ La fin ({$fin->format('d/m/Y')}) est APRÈS la fin de l'année ({$annee->date_fin->format('d/m/Y')}).";
        }

        return back()->with('success', $msg);
    }

    public function destroyPeriode(Periode $periode)
    {
        $periode->delete();

        return back()->with('success', 'Fenêtre supprimée.');
    }
}
