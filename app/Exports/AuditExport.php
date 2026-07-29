<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Journal d'audit exporté tel qu'affiché à l'écran (mêmes filtres). */
class AuditExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private $rows)
    {
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['Date', 'Heure', 'Acteur', 'Identifiant', 'Action', 'Description', 'Adresse IP'];
    }

    public function map($log): array
    {
        return [
            $log->created_at?->format('d/m/Y'),
            $log->created_at?->format('H:i:s'),
            $log->user?->staff_name ?? 'Système',
            $log->user?->numero_identifiant ?? '—',
            $log->action,
            $log->description,
            $log->ip_address ?? '—',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
