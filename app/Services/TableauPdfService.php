<?php

namespace App\Services;

use App\Support\RapportPdf;

/**
 * Rend n'importe quel export tabulaire en PDF, avec l'en-tête
 * institutionnel et le nom du générateur en pied de chaque page.
 *
 * Les colonnes reçoivent une largeur proportionnelle à leur poids :
 * un nom de parcours a besoin de plus de place qu'un montant.
 */
class TableauPdfService
{
    /**
     * @param  array<string>        $entetes  libellés de colonnes
     * @param  iterable<array>      $lignes   valeurs, dans l'ordre des en-têtes
     * @param  array<int,float>     $poids    poids relatif de chaque colonne (défaut : 1)
     */
    public function build(
        string $titre,
        array $entetes,
        iterable $lignes,
        string $genereePar = '',
        string $filtres = '',
        array $poids = [],
        string $orientation = 'L'
    ): string {
        $pdf = new RapportPdf($orientation);
        $pdf->configurer($titre, '', $genereePar, $filtres);
        $pdf->AddPage();
        $pdf->blocFiltres();

        $utile = $pdf->getPageWidth() - 28;
        $poids = $poids ?: array_fill(0, count($entetes), 1);
        $total = array_sum($poids) ?: 1;
        $larg  = array_map(fn ($p) => $utile * $p / $total, $poids);

        $this->entete($pdf, $entetes, $larg);

        $pdf->SetFont('helvetica', '', 7);
        $pdf->SetTextColor(...RapportPdf::INK);
        $pdf->SetDrawColor(225, 232, 242);

        $zebre = false;
        $nb = 0;

        foreach ($lignes as $ligne) {
            $valeurs = array_values((array) $ligne);
            $valeurs = array_pad(array_slice($valeurs, 0, count($entetes)), count($entetes), '');

            // Hauteur dictée par la cellule la plus haute
            $h = 5;
            foreach ($valeurs as $i => $v) {
                $h = max($h, $pdf->getNumLines((string) $v, $larg[$i]) * 3.2);
            }

            if ($pdf->GetY() + $h > $pdf->getPageHeight() - 22) {
                $pdf->AddPage();
                $this->entete($pdf, $entetes, $larg);
                $pdf->SetFont('helvetica', '', 7);
                $pdf->SetTextColor(...RapportPdf::INK);
            }

            $y = $pdf->GetY();
            $x = 14;
            $pdf->SetFillColor(249, 251, 254);

            foreach ($valeurs as $i => $v) {
                $pdf->SetXY($x, $y);
                $pdf->MultiCell($larg[$i], $h, (string) $v, 1, 'L', $zebre, 0, '', '', true, 0, false, true, $h, 'T');
                $x += $larg[$i];
            }
            $pdf->SetY($y + $h);
            $zebre = !$zebre;
            $nb++;
        }

        if ($nb === 0) {
            $pdf->SetFont('helvetica', 'I', 9);
            $pdf->SetTextColor(...RapportPdf::MUTED);
            $pdf->Cell(0, 8, 'Aucune ligne pour ces critères.', 0, 1, 'C');
        } else {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetTextColor(...RapportPdf::MUTED);
            $pdf->Cell(0, 5, $nb . ' ligne(s)', 0, 1, 'R');
        }

        return $pdf->Output('tableau.pdf', 'S');
    }

    private function entete(RapportPdf $pdf, array $entetes, array $larg): void
    {
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->SetFillColor(240, 245, 252);
        $pdf->SetTextColor(...RapportPdf::MUTED);
        $pdf->SetDrawColor(225, 232, 242);

        $h = 6;
        $y = $pdf->GetY();
        $x = 14;
        foreach ($entetes as $i => $t) {
            $pdf->SetXY($x, $y);
            $pdf->MultiCell($larg[$i], $h, $t, 1, 'L', true, 0, '', '', true, 0, false, true, $h, 'M');
            $x += $larg[$i];
        }
        $pdf->SetY($y + $h);
    }
}
