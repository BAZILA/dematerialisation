<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export Excel statistiques : feuille « Synthèse » (tous les agrégats,
 * avec le rappel des filtres appliqués) + feuille « Détail » (liste complète).
 */
class StatistiquesExport implements WithMultipleSheets
{
    public function __construct(
        public array $agg,
        public array $filtres,
        public Collection $rows,
    ) {
    }

    public function sheets(): array
    {
        return [
            new SyntheseSheet($this->agg, $this->filtres),
            new DemandesDetailExport($this->rows),
        ];
    }
}

/** Feuille « Synthèse » — agrégats posés en sections lisibles. */
class SyntheseSheet implements FromArray, WithTitle
{
    public function __construct(public array $agg, public array $filtres)
    {
    }

    public function title(): string
    {
        return 'Synthèse';
    }

    public function array(): array
    {
        $a = $this->agg;
        $l = [];

        $l[] = ['STATISTIQUES — DEMANDES & DÉPÔTS'];
        $l[] = ['Généré le', now()->format('d/m/Y à H:i')];
        foreach ($this->filtres as $k => $v) {
            $l[] = ['Filtre — ' . $k, $v];
        }
        $l[] = [];

        $l[] = ['INDICATEURS CLÉS'];
        $l[] = ['Total demandes', $a['total']];
        $l[] = ['Pièces jointes', $a['nbPieces']];
        $l[] = ['Pièces tamponnées', $a['nbTamponne']];
        $l[] = ['Délai moyen de traitement (jours)', $a['delaiJours'] ?? '—'];
        $l[] = [];

        $statuts = \App\Http\Controllers\StatistiqueController::$statutsLabels;
        $l[] = ['PAR STATUT'];
        foreach ($a['parStatut'] as $s => $n) {
            $l[] = [$statuts[$s] ?? $s, $n];
        }
        $l[] = [];

        $l[] = ['PAR PROCÉDURE × STATUT'];
        $l[] = array_merge(['Procédure'], array_values($statuts), ['Total']);
        $procs = \App\Models\Procedure::labels();
        foreach ($a['parCatStatut'] as $cat => $byStatus) {
            $row = [$procs[$cat] ?? $cat];
            foreach (array_keys($statuts) as $s) {
                $row[] = $byStatus[$s] ?? 0;
            }
            $row[] = array_sum($byStatus);
            $l[] = $row;
        }
        $l[] = [];

        foreach ([
            'PAR FACULTÉ / INSTITUT' => 'parFaculte',
            'PAR DÉPARTEMENT'        => 'parDepartement',
            'TOP PARCOURS'           => 'parParcours',
            "PAR ANNÉE D'ÉTUDES"     => 'parSemestre',
            'PAR SEXE'               => 'parSexe',
        ] as $titre => $cle) {
            $l[] = [$titre];
            foreach ($a[$cle] as $k => $n) {
                $l[] = [$k ?: '—', $n];
            }
            $l[] = [];
        }

        $l[] = ['PAR PROFIL'];
        foreach ($a['parType'] as $t => $n) {
            $l[] = [$t === 'nouveau' ? 'Nouveaux bacheliers' : 'Anciens étudiants', $n];
        }
        $l[] = [];

        $l[] = ['SOUMISSIONS PAR MOIS'];
        foreach ($a['parMois'] as $mois => $n) {
            $l[] = [$mois, $n];
        }

        return $l;
    }
}
