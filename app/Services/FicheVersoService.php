<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Remplit automatiquement le VERSO (page 2) de la fiche d'inscription UK.
 *
 * La fiche est déjà imprimée : on écrit UNIQUEMENT dans les cases existantes
 * (texte centré), on pose la date, la mention de vérification, un cachet
 * rectangulaire « LU ET APPROUVÉ » (vectoriel) attestant l'acceptation en ligne,
 * et, si fournies, les images de signature de l'agent et de cachet officiel.
 *
 * ⚠️ Coordonnées en mm (A4 210 × 297), calées sur la fiche réelle UK
 *    (colonnes/lignes extraites au point près). Constantes en tête.
 */
class FicheVersoService
{
    private const VERSO_PAGE = 2;

    /* ── Tableau « Études antérieures » (cases pré-imprimées) ──── */
    private const FIRST_ROW_TOP = 66.0;  // Y du haut de la 1re ligne de données
    private const ROW_H         = 7.0;   // hauteur d'une ligne
    private const MAX_ROWS      = 9;

    /** Colonnes : clé => [x_gauche, largeur]. Texte centré (H + V). */
    private const COLS = [
        'periode'       => [10.0, 22.0],
        'universite'    => [32.0, 22.0],
        'domaine'       => [54.0, 22.0],
        'etablissement' => [76.0, 32.0],
        'parcours'      => [108.0, 62.0],
        'credits'       => [170.0, 30.0],
    ];

    /* ── Date « Kara, le … » (posée sur la ligne) ─────────────── */
    private const DATE_X = 110.0;
    private const DATE_Y = 228.5;

    /* ── Cachet rectangulaire « LU ET APPROUVÉ » (vectoriel, vert, penché) ── */
    private const STAMP_X = 150.0;
    private const STAMP_Y = 223.0;
    private const STAMP_W = 48.0;
    private const STAMP_H = 23.0;
    private const STAMP_ANGLE = 6.0;         // inclinaison en degrés
    private const GREEN = [47, 158, 51];     // vert UK

    /* ── « Fiche vérifiée par : {nom}   le {date} » (sur les traits) ── */
    private const VERIF_NAME_X = 48.0;
    private const VERIF_NAME_Y = 258.0;
    private const VERIF_NAME_W = 66.0;
    private const VERIF_DATE_X = 120.0;
    private const VERIF_DATE_Y = 258.0;
    private const VERIF_DATE_W = 40.0;

    /* ── Signature agent (image) + cachet officiel (image) ────── */
    private const SIGN_X   = 116.0;
    private const SIGN_Y   = 269.0;
    private const SIGN_W   = 36.0;
    private const CACHET_X = 156.0;
    private const CACHET_Y = 266.0;
    private const CACHET_W = 45.0;

    /* Tout en noir */
    private const INK = [0, 0, 0];

    /**
     * @param  array       $etudes         Lignes [annee_debut, annee_fin, universite, domaine, etablissement, parcours, credits]
     * @param  string|null $cachetPath     Image cachet officiel (PNG) — chemin absolu
     * @param  string|null $signaturePath  Image signature agent (PNG) — chemin absolu
     * @param  string|null $validatorName  Nom réel du validateur
     * @param  string|null $acceptedAt     Horodatage d'acceptation « Lu et approuvé » (Y-m-d H:i:s)
     * @param  string|null $reference      Référence de traçabilité (facultatif)
     */
    public function fillVerso(
        string $inputPath,
        string $outputPath,
        array $etudes = [],
        ?string $cachetPath = null,
        ?string $signaturePath = null,
        ?string $validatorName = null,
        ?string $acceptedAt = null,
        ?string $reference = null
    ): bool {
        if (!file_exists($inputPath)) {
            Log::error("FicheVersoService: source introuvable — {$inputPath}");
            return false;
        }
        if (!class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class)) {
            Log::error('FicheVersoService: FPDI/TCPDF manquant.');
            return false;
        }

        try {
            $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm');
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->setAutoPageBreak(false);

            $pageCount = $pdf->setSourceFile($inputPath);
            $versoPage = min(self::VERSO_PAGE, $pageCount);

            for ($p = 1; $p <= $pageCount; $p++) {
                $tpl  = $pdf->importPage($p);
                $size = $pdf->getTemplateSize($tpl);
                $ori  = ($size['width'] > $size['height']) ? 'L' : 'P';

                $pdf->AddPage($ori, [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height'], true);

                // Le VERSO reçoit le remplissage complet (études + engagement + vérification)
                if ($p === $versoPage) {
                    $this->drawEtudes($pdf, $etudes);
                    $this->drawDate($pdf);
                    $this->drawApprovalStamp($pdf, $acceptedAt, $reference);
                    $this->drawVerification($pdf, $validatorName);
                    $this->drawSignature($pdf, $signaturePath);
                    $this->drawCachet($pdf, $size['width'], $size['height'], $cachetPath);
                } else {
                    // Les AUTRES pages reçoivent le cachet officiel (centre/haut)
                    $this->drawCachetCorner($pdf, $size['width'], $size['height'], $cachetPath, $validatorName);
                }
            }

            $pdf->Output($outputPath, 'F');

            if (!file_exists($outputPath) || filesize($outputPath) === 0) {
                Log::error("FicheVersoService: sortie vide — {$outputPath}");
                return false;
            }

            Log::info("FicheVersoService: verso rempli — {$outputPath}");
            return true;

        } catch (\Throwable $e) {
            Log::error('FicheVersoService exception: ' . $e->getMessage());
            return false;
        }
    }

    /* ── Remplit les cases (texte CENTRÉ, sans bordures) ──────── */
    private function drawEtudes(\setasign\Fpdi\Tcpdf\Fpdi $pdf, array $etudes): void
    {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 7.5);

        $rows = array_slice(array_values($etudes), 0, self::MAX_ROWS);

        foreach ($rows as $i => $e) {
            $top = self::FIRST_ROW_TOP + $i * self::ROW_H;

            $periode = trim(($e['annee_debut'] ?? '') . '-' . ($e['annee_fin'] ?? ''), '-');
            $cells = [
                'periode'       => $periode,
                'universite'    => $e['universite'] ?? '',
                'domaine'       => $e['domaine'] ?? '',
                'etablissement' => $e['etablissement'] ?? '',
                'parcours'      => $e['parcours'] ?? '',
                'credits'       => (isset($e['credits']) && $e['credits'] !== null && $e['credits'] !== '')
                    ? (string) $e['credits'] : '',
            ];

            foreach (self::COLS as $key => [$x, $w]) {
                if ($cells[$key] === '') {
                    continue;
                }
                $pdf->SetXY($x, $top);
                // Centré H ('C') + V ('M' par défaut) ; stretch=1 réduit si trop long
                $pdf->Cell($w, self::ROW_H, $cells[$key], 0, 0, 'C', false, '', 1);
            }
        }
    }

    /* ── Date « Kara, le … » ──────────────────────────────────── */
    private function drawDate(\setasign\Fpdi\Tcpdf\Fpdi $pdf): void
    {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(self::DATE_X, self::DATE_Y);
        $pdf->Cell(50, 4, now()->format('d/m/Y'), 0, 0, 'L');
    }

    /* ── Cachet rectangulaire « LU ET APPROUVÉ » (vectoriel) ──── */
    private function drawApprovalStamp(\setasign\Fpdi\Tcpdf\Fpdi $pdf, ?string $acceptedAt, ?string $reference): void
    {
        $x = self::STAMP_X; $y = self::STAMP_Y; $w = self::STAMP_W; $h = self::STAMP_H;
        [$r, $g, $b] = self::GREEN;
        $ts = $acceptedAt ? \Carbon\Carbon::parse($acceptedAt) : now();

        // Rotation autour du centre du cachet (léger penché)
        $pdf->StartTransform();
        $pdf->Rotate(self::STAMP_ANGLE, $x + $w / 2, $y + $h / 2);

        // Cadre arrondi vert
        $pdf->SetDrawColor($r, $g, $b);
        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetLineWidth(0.6);
        $pdf->RoundedRect($x, $y, $w, $h, 2.0, '1111', 'D');

        // Titre
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY($x, $y + 2.5);
        $pdf->Cell($w, 6, 'LU ET APPROUVÉ', 0, 0, 'C');

        // Sous-titre
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetXY($x, $y + 8);
        $pdf->Cell($w, 4, 'Validé en ligne', 0, 0, 'C');

        // Horodatage
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($x, $y + 12);
        $pdf->Cell($w, 4, $ts->format('d/m/Y à H:i'), 0, 0, 'C');

        // Référence (facultative)
        if ($reference) {
            $pdf->SetFont('helvetica', '', 6.5);
            $pdf->SetXY($x, $y + 16.5);
            $pdf->Cell($w, 3.5, 'Réf. : ' . $reference, 0, 0, 'C');
        }

        $pdf->StopTransform();
    }

    /* ── « Fiche vérifiée par : {nom}   le {date} » ───────────── */
    private function drawVerification(\setasign\Fpdi\Tcpdf\Fpdi $pdf, ?string $validatorName): void
    {
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetXY(self::VERIF_NAME_X, self::VERIF_NAME_Y);
        $pdf->Cell(self::VERIF_NAME_W, 4, $validatorName ?: '', 0, 0, 'C', false, '', 1);

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY(self::VERIF_DATE_X, self::VERIF_DATE_Y);
        $pdf->Cell(self::VERIF_DATE_W, 4, now()->format('d/m/Y'), 0, 0, 'C');
    }

    /* ── Signature agent (image, si chargée) ──────────────────── */
    private function drawSignature(\setasign\Fpdi\Tcpdf\Fpdi $pdf, ?string $signaturePath): void
    {
        if ($signaturePath && file_exists($signaturePath)) {
            $pdf->Image($signaturePath, self::SIGN_X, self::SIGN_Y, self::SIGN_W, 0, '', '', '', false, 300);
        }
    }

    /* ── Cachet officiel (image, si chargé) — zone centre/haut aléatoire ── */
    private function drawCachet(\setasign\Fpdi\Tcpdf\Fpdi $pdf, float $pw, float $ph, ?string $cachetPath): void
    {
        if ($cachetPath && file_exists($cachetPath)) {
            $w = self::CACHET_W;
            $x = ($pw - $w) / 2 + mt_rand(-250, 250) / 10;   // centré ±25 mm
            $y = mt_rand(400, 1350) / 10;                    // 40 à 135 mm (moitié haute)
            $angle = mt_rand(-80, 80) / 10;                  // ±8°
            $pdf->StartTransform();
            $pdf->Rotate($angle, $x + $w / 2, $y + $w / 2);
            $pdf->Image($cachetPath, $x, $y, $w, 0, '', '', '', false, 300);
            $pdf->StopTransform();
        }
    }

    /** Cachet officiel (zone centre/haut) sur les pages autres que le verso. */
    private function drawCachetCorner(\setasign\Fpdi\Tcpdf\Fpdi $pdf, float $pw, float $ph, ?string $cachetPath, ?string $validatorName): void
    {
        if (!$cachetPath || !file_exists($cachetPath)) {
            return;
        }
        $w = 42;
        $x = ($pw - $w) / 2 + mt_rand(-250, 250) / 10;   // centré ±25 mm
        $y = mt_rand(400, 1350) / 10;                    // 40 à 135 mm (moitié haute)
        $angle = mt_rand(-80, 80) / 10;                  // ±8°
        try {
            $pdf->StartTransform();
            $pdf->Rotate($angle, $x + $w / 2, $y + $w / 2);
            $pdf->Image($cachetPath, $x, $y, $w, 0, '', '', '', false, 300);
            $pdf->StopTransform();
        } catch (\Throwable $e) {
            Log::warning('FicheVersoService cachet coin: ' . $e->getMessage());
        }
        if ($validatorName) {
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 6.5);
            $pdf->SetXY($x - 6, $y - 4);
            $pdf->Cell($w + 12, 3, 'Validé par : ' . $validatorName, 0, 0, 'C');
        }
    }

    public static function isAvailable(): bool
    {
        return class_exists(\setasign\Fpdi\Tcpdf\Fpdi::class);
    }
}
