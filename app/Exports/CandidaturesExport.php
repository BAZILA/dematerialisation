<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Candidatures telles que filtrées à l'écran.
 *
 * Une ligne par PARCOURS demandé : c'est à ce niveau que se prend la
 * décision, et c'est ce que les commissions d'admission exploitent.
 */
class CandidaturesExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private $lignes)
    {
    }

    public function collection()
    {
        return $this->lignes;
    }

    public function headings(): array
    {
        return [
            'Référence', 'Nom', 'Prénom(s)', 'Sexe', 'Date de naissance', 'Lieu de naissance',
            'Email', 'Téléphone', 'Année académique', 'Soumise le',
            'Parcours', 'Faculté / Institut / École', 'Grade', 'Rang du choix',
            'Décision', 'Motif', 'Décidée par', 'Décidée le',
            'Pièces communes', 'Pièces propres', 'Frais', 'Paiement',
        ];
    }

    /** @param  \App\Models\CandidatureChoix  $c */
    public function map($c): array
    {
        $d = $c->candidature;
        $p = $c->paiement;

        return [
            $d->reference,
            $d->nom,
            $d->prenom,
            $d->sexe,
            $d->date_naissance?->format('d/m/Y'),
            $d->lieu_naissance,
            $d->email,
            $d->telephone ?: '—',
            $d->anneeAcademique?->libelle ?? '—',
            $d->created_at?->format('d/m/Y H:i'),
            $c->parcours?->nom ?? '—',
            $c->parcours?->departement?->faculte?->nom ?? '—',
            $c->parcours?->grade_label ?? '—',
            $c->ordre,
            $c->statut_label,
            $c->motif ?: '',
            $c->decideur?->staff_name ?? '—',
            $c->decide_at?->format('d/m/Y H:i') ?? '—',
            $d->files->whereNull('candidature_choix_id')->count(),
            $c->files->count(),
            $p?->montant ?? 0,
            $p?->statut_label ?? '—',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
