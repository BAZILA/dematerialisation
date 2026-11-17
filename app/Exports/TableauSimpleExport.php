<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export générique d'un tableau déjà mis en forme : en-têtes + lignes
 * brutes. Sert aux récapitulatifs agrégés, où il n'y a pas de modèle
 * Eloquent à mapper — seulement des colonnes calculées.
 */
class TableauSimpleExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    public function __construct(private array $entetes, private $lignes)
    {
    }

    public function collection()
    {
        return collect($this->lignes);
    }

    public function headings(): array
    {
        return $this->entetes;
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
