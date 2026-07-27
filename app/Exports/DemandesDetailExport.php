<?php

namespace App\Exports;

use App\Models\Parcours;
use App\Models\StudentRequest;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Détail COMPLET des demandes filtrées (toutes procédures confondues) —
 * base des exports statistiques (feuille « Détail » / CSV).
 */
class DemandesDetailExport implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    public function __construct(public Collection $rows)
    {
    }

    public function title(): string
    {
        return 'Détail';
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'numero_suivi', 'identifiant', 'reference', 'nom', 'prenom', 'sexe', 'profil',
            'telephone', 'email',
            'procedure', 'statut',
            'faculte', 'departement', 'parcours', 'grade', 'annee_etudes',
            'annee_academique',
            'date_soumission', 'date_validation', 'valide_par',
            'nb_pieces', 'nb_pieces_tamponnees',
        ];
    }

    public function map($r): array
    {
        /** @var StudentRequest $r */
        $student  = $r->student;
        $parcours = $r->parcours;
        $source   = $student?->bachelier ?? $student?->ancienEtudiant;

        return [
            $student?->numero_suivi,
            $student?->user?->numero_identifiant,
            $student?->numero_reference,
            $student?->nom,
            $student?->prenom,
            $student?->sexe,
            $student?->user?->type_etudiant === 'nouveau' ? 'Nouveau' : 'Ancien',
            $source?->telephone,
            $source?->email,
            \App\Models\Procedure::labels()[$r->category] ?? $r->category,
            \App\Http\Controllers\StatistiqueController::$statutsLabels[$r->status] ?? $r->status,
            $parcours?->departement?->faculte?->nom,
            $parcours?->departement?->nom,
            $parcours?->nom,
            $parcours?->grade_label,
            Parcours::libelleNiveau($r->semestre, $parcours?->grade),
            $r->anneeAcademique?->libelle,
            optional($r->submitted_at)->format('Y-m-d H:i'),
            optional($r->validated_at)->format('Y-m-d H:i'),
            $r->validator?->staff_name,
            $r->files->count(),
            $r->files->where('is_stamped', true)->count(),
        ];
    }
}
