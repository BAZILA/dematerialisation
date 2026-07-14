<?php

namespace App\Exports;

use App\Models\AncienEtudiant;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AnciensExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(public bool $templateOnly = false)
    {
    }

    public function collection()
    {
        return $this->templateOnly ? collect() : AncienEtudiant::orderBy('nom')->orderBy('prenom')->get();
    }

    public function headings(): array
    {
        return ['numero_carte', 'nom', 'prenom', 'sexe', 'date_naissance', 'lieu_naissance', 'specialite', 'derniere_universite', 'is_registered'];
    }

    public function map($a): array
    {
        return [
            $a->numero_carte,
            $a->nom,
            $a->prenom,
            $a->sexe,
            optional($a->date_naissance)->format('Y-m-d'),
            $a->lieu_naissance,
            $a->specialite,
            $a->derniere_universite,
            $a->is_registered ? 'oui' : 'non',
        ];
    }
}
