<?php

namespace App\Exports;

use App\Models\StudentRequest;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export des demandes de dépôt (pour reprise dans le logiciel d'inscription).
 * Reçoit une collection déjà filtrée (statut / périmètre / recherche).
 */
class DepotsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(public Collection $rows)
    {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'numero_suivi', 'identifiant', 'reference', 'nom', 'prenom', 'sexe',
            'telephone', 'email',
            'faculte', 'departement', 'parcours', 'grade', 'annee_etudes', 'semestres', 'annee_academique',
            'statut', 'date_soumission', 'date_validation', 'valide_par',
            'nb_pieces', 'nb_pieces_tamponnees',
            // Ce qui manquait au dépôt et reste dû : sans ces colonnes,
            // l'export dirait qu'un dossier différé est un dossier complet.
            'pieces_differees', 'pieces_encore_dues',
        ];
    }

    public function map($r): array
    {
        /** @var StudentRequest $r */
        $student  = $r->student;
        $parcours = $r->parcours;
        $faculte  = $parcours?->departement?->faculte;
        $source   = $student?->bachelier ?? $student?->ancienEtudiant;

        return [
            $student?->numero_suivi,           // N° de suivi (logiciel inscription)
            $student?->user?->numero_identifiant,
            $student?->numero_reference,       // N° carte (ancien) ou N° table (bachelier)
            $student?->nom,
            $student?->prenom,
            $student?->sexe,
            $source?->telephone,
            $source?->email,
            $faculte?->nom,
            $parcours?->departement?->nom,
            $parcours?->nom,
            $parcours?->grade_label,
            \App\Models\Parcours::libelleNiveau($r->semestre, $parcours?->grade),
            implode(' & ', \App\Models\Parcours::niveauxParGrade($parcours?->grade ?? '')[$r->semestre]['semestres'] ?? []),
            $r->anneeAcademique?->libelle,
            $r->status_label,
            optional($r->submitted_at)->format('Y-m-d H:i'),
            optional($r->validated_at)->format('Y-m-d H:i'),
            $r->validator?->staff_name,
            $r->files->count(),
            $r->files->where('is_stamped', true)->count(),
            $r->derogations->count(),
            $r->derogations->where('statut', 'engagee')
                ->map(fn ($d) => $d->requestType?->label)
                ->filter()->implode(' | '),
        ];
    }
}
