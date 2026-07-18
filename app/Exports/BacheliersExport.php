<?php

namespace App\Exports;

use App\Models\Bachelier;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BacheliersExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(public bool $templateOnly = false)
    {
    }

    public function collection()
    {
        return $this->templateOnly ? collect() : Bachelier::orderBy('nom')->orderBy('prenom')->get();
    }

    public function headings(): array
    {
        return ['numero_suivi', 'nom', 'prenom', 'sexe', 'date_naissance', 'lieu_naissance', 'serie_bac', 'annee_bac', 'numero_releve', 'numero_table', 'academie', 'telephone', 'email', 'is_registered'];
    }

    public function map($b): array
    {
        return [
            $b->numero_suivi,
            $b->nom,
            $b->prenom,
            $b->sexe,
            optional($b->date_naissance)->format('Y-m-d'),
            $b->lieu_naissance,
            $b->serie_bac,
            $b->annee_bac,
            $b->numero_releve,
            $b->numero_table,
            $b->academie,
            $b->telephone,
            $b->email,
            $b->is_registered ? 'oui' : 'non',
        ];
    }
}
