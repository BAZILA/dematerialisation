<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** État de caisse : paiements tels que filtrés à l'écran. */
class PaiementsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
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
        return [
            'Référence', 'Date', 'Année', 'Procédure', 'Payeur', 'Dossier',
            'Montant', 'Devise', 'Opérateur', 'N° transaction', 'Numéro payeur',
            'Statut', 'Vérifié par', 'Validé le', 'Motif du rejet',
        ];
    }

    public function map($p): array
    {
        $payable = $p->payable;

        $payeur = match (true) {
            $payable instanceof \App\Models\Candidature   => $payable->full_name,
            $payable instanceof \App\Models\StudentRequest => $payable->student?->full_name ?? '—',
            default => '—',
        };
        $dossier = match (true) {
            $payable instanceof \App\Models\Candidature   => $payable->reference,
            $payable instanceof \App\Models\StudentRequest => 'Demande #' . $payable->id,
            default => '—',
        };

        return [
            $p->reference,
            $p->created_at?->format('d/m/Y H:i'),
            $p->anneeAcademique?->libelle ?? '—',
            \App\Models\Procedure::labels()[$p->procedure_code] ?? $p->procedure_code,
            $payeur,
            $dossier,
            $p->montant,
            \App\Models\Paiement::libelleDevise($p->devise),
            $p->operateur_label,
            $p->transaction_id ?: '—',
            $p->numero_payeur ?: '—',
            $p->statut_label,
            $p->validateur?->staff_name ?? ($p->verifie_auto ? 'Opérateur (auto)' : '—'),
            $p->valide_at?->format('d/m/Y H:i') ?? '—',
            $p->motif_rejet ?: '',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
