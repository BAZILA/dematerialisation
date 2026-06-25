<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class StampService
{
    /**
     * Appliquer un tampon officiel sur chaque page d'un PDF.
     *
     * @param  string $inputPath  Chemin absolu du PDF source
     * @param  string $outputPath Chemin absolu du PDF de sortie
     * @return bool
     */
    public function stampPdf(string $inputPath, string $outputPath): bool
    {
        if (!file_exists($inputPath)) {
            Log::error("StampService: fichier source introuvable — {$inputPath}");
            return false;
        }

        if (!class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            Log::error('StampService: FPDI manquant. Exécutez : composer require setasign/fpdi tecnickcom/tcpdf');
            return false;
        }

        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->setAutoPageBreak(false);

            $pageCount = $pdf->setSourceFile($inputPath);

            for ($p = 1; $p <= $pageCount; $p++) {
                $tpl = $pdf->importPage($p);
                $size = $pdf->getTemplateSize($tpl);
                $ori = ($size['width'] > $size['height']) ? 'L' : 'P';

                $pdf->AddPage($ori, [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);

                $this->drawStamp($pdf, $size['width'], $size['height']);
            }

            $pdf->Output($outputPath, 'F');

            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                Log::error("StampService: fichier de sortie vide ou absent — {$outputPath}");
                return false;
            }

            Log::info("StampService: tampon appliqué avec succès — {$outputPath}");
            return true;

        } catch (\Exception $e) {
            Log::error('StampService exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Dessine le tampon en haut à droite de la page.
     *
     * Conception :
     * - Cercle extérieur épais + cercle intérieur fin (bleu marine)
     * - Fond plein bleu marine semi-transparent
     * - Texte EN BLANC pour une lisibilité maximale
     * - Opacité 50% pour ne pas masquer le document
     */
    private function drawStamp(\setasign\Fpdi\Tcpdf\Fpdi $pdf, float $pw, float $ph): void
    {
        $cx = $pw - 22;  // centre X (coin droit)
        $cy = 22;        // centre Y (coin haut)
        $r = 19;        // rayon

        // ── Transparence 50% ──────────────────────────────────
        $pdf->setAlpha(0.50);

        // ── Fond bleu marine rempli ───────────────────────────
        $pdf->SetDrawColor(8, 45, 110);
        $pdf->SetFillColor(8, 45, 110);
        $pdf->SetLineWidth(0.8);
        $pdf->Circle($cx, $cy, $r, 0, 360, 'DF'); // D=contour F=rempli

        // ── Cercle intérieur (trait blanc fin) ────────────────
        $pdf->SetDrawColor(255, 255, 255);
        $pdf->SetLineWidth(0.3);
        $pdf->Circle($cx, $cy, $r - 3, 0, 360, 'D');

        // ── Ligne décorative horizontale blanche ──────────────
        $pdf->SetLineWidth(0.25);
        $pdf->Line($cx - ($r - 5), $cy, $cx + ($r - 5), $cy);

        // ── Texte EN BLANC (visible sur fond bleu) ────────────
        $pdf->SetTextColor(255, 255, 255);

        // Ligne 1 : UNIVERSITÉ (petites caps)
        $pdf->SetFont('helvetica', 'B', 5.5);
        $pdf->SetXY($cx - $r, $cy - 10);
        $pdf->Cell($r * 2, 4, 'UNIVERSITÉ', 0, 2, 'C');

        // Ligne 2 : VALIDÉ (grand, gras)
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetX($cx - $r);
        $pdf->Cell($r * 2, 6, 'VALIDÉ', 0, 2, 'C');

        // Ligne 3 : date du jour
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetX($cx - $r);
        $pdf->Cell($r * 2, 3.5, now()->format('d/m/Y'), 0, 0, 'C');

        // ── Restaurer opacité pleine ──────────────────────────
        $pdf->setAlpha(1);
    }

    /**
     * Vérifie que FPDI/TCPDF est disponible.
     */
    public static function isAvailable(): bool
    {
        return class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class);
    }
}
