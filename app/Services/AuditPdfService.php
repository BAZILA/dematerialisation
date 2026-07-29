<?php

namespace App\Services;

use App\Support\RapportPdf;

/**
 * Journal d'audit en PDF — document à valeur probante : logo de
 * l'université en en-tête, agent générateur et pagination en pied.
 * Format paysage, le journal étant large (acteur, action, description).
 */
class AuditPdfService
{
    public function build($logs, string $filtres = '', string $genereePar = ''): string
    {
        $pdf = new RapportPdf('L');
        $pdf->configurer(
            "Journal d'audit",
            $logs->count() . ' entrée(s)',
            $genereePar,
            $filtres
        );
        $pdf->AddPage();
        $pdf->blocFiltres();

        // Largeurs (269 mm utiles en paysage A4 avec marges de 14)
        $cols = [
            'Date / heure' => 30,
            'Acteur'       => 42,
            'Action'       => 42,
            'Description'  => 130,
            'IP'           => 25,
        ];

        $this->enTeteTableau($pdf, $cols);

        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->SetTextColor(...RapportPdf::INK);
        $pdf->SetDrawColor(225, 232, 242);

        $zebre = false;
        foreach ($logs as $log) {
            $description = (string) $log->description;
            // Hauteur de ligne adaptée au texte le plus long
            $lignes = max(1, $pdf->getNumLines($description, $cols['Description']));
            $h = max(5, $lignes * 3.4);

            // Saut de page manuel : on réimprime l'en-tête du tableau
            if ($pdf->GetY() + $h > $pdf->getPageHeight() - 22) {
                $pdf->AddPage();
                $this->enTeteTableau($pdf, $cols);
                $pdf->SetFont('helvetica', '', 7.5);
                $pdf->SetTextColor(...RapportPdf::INK);
            }

            $pdf->SetFillColor(249, 251, 254);
            $y = $pdf->GetY();
            $x = 14;

            $valeurs = [
                $log->created_at?->format('d/m/Y H:i') ?? '—',
                ($log->user?->staff_name ?? 'Système')
                    . ($log->user?->numero_identifiant ? "\n" . $log->user->numero_identifiant : ''),
                $log->action,
                $description,
                $log->ip_address ?? '—',
            ];

            foreach (array_values($cols) as $i => $w) {
                $pdf->SetXY($x, $y);
                $pdf->MultiCell($w, $h, $valeurs[$i], 1, 'L', $zebre, 0, '', '', true, 0, false, true, $h, 'T');
                $x += $w;
            }
            $pdf->SetY($y + $h);
            $zebre = !$zebre;
        }

        if ($logs->isEmpty()) {
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(...RapportPdf::MUTED);
            $pdf->Cell(0, 8, 'Aucune entrée pour ces critères.', 0, 1, 'C');
        }

        return $pdf->Output('audit.pdf', 'S');
    }

    private function enTeteTableau(RapportPdf $pdf, array $cols): void
    {
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(240, 245, 252);
        $pdf->SetTextColor(...RapportPdf::MUTED);
        $pdf->SetDrawColor(225, 232, 242);

        foreach ($cols as $titre => $w) {
            $pdf->Cell($w, 6, $titre, 1, 0, 'L', true);
        }
        $pdf->Ln();
    }
}
