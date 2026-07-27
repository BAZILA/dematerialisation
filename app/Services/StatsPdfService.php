<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Rapport statistique PDF (synthèse) — généré avec TCPDF (déjà présent
 * pour le tamponnage). Une page A4 propre pour les autorités :
 * en-tête institution, filtres appliqués, KPI, tableaux d'agrégats.
 */
class StatsPdfService
{
    private const BRAND = [26, 86, 168];
    private const INK   = [20, 36, 59];
    private const MUTED = [110, 122, 140];

    public function build(array $agg, array $filtres): string
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Plateforme de dématérialisation');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(14, 12, 14);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();

        /* ── En-tête ── */
        $pdf->SetTextColor(...self::BRAND);
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->Cell(0, 8, Setting::get('app.university_name', 'Université de Kara'), 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(...self::INK);
        $pdf->Cell(0, 6, 'Rapport statistique — Demandes & dépôts dématérialisés', 0, 1, 'C');
        $pdf->SetFont('helvetica', 'I', 8.5);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->Cell(0, 5, 'Généré le ' . now()->format('d/m/Y à H:i'), 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetDrawColor(...self::BRAND);
        $pdf->SetLineWidth(.5);
        $pdf->Line(14, $pdf->GetY(), 196, $pdf->GetY());
        $pdf->Ln(3);

        /* ── Filtres appliqués ── */
        if ($filtres) {
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->SetTextColor(...self::INK);
            $pdf->Cell(0, 5.5, 'Périmètre du rapport', 0, 1);
            $pdf->SetFont('helvetica', '', 8.5);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->MultiCell(0, 4.5, collect($filtres)->map(fn ($v, $k) => "$k : $v")->implode('   ·   '), 0, 'L');
            $pdf->Ln(2);
        }

        /* ── KPI en bandeaux ── */
        $kpis = [
            ['Total demandes', $agg['total']],
            ['Validées', $agg['parStatut']['validé'] ?? 0],
            ['En attente', $agg['parStatut']['soumis'] ?? 0],
            ['Délai moyen (j)', $agg['delaiJours'] ?? '—'],
        ];
        $w = (196 - 14 - 3 * 4) / 4;
        $x = 14;
        $y = $pdf->GetY();
        foreach ($kpis as [$lbl, $val]) {
            $pdf->SetFillColor(240, 245, 252);
            $pdf->RoundedRect($x, $y, $w, 16, 2, '1111', 'F');
            $pdf->SetXY($x, $y + 2.5);
            $pdf->SetFont('helvetica', 'B', 13);
            $pdf->SetTextColor(...self::BRAND);
            $pdf->Cell($w, 6, (string) $val, 0, 0, 'C');
            $pdf->SetXY($x, $y + 9);
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(...self::MUTED);
            $pdf->Cell($w, 4, $lbl, 0, 0, 'C');
            $x += $w + 4;
        }
        $pdf->SetY($y + 20);

        /* ── Tableaux d'agrégats ── */
        $statuts = \App\Http\Controllers\StatistiqueController::$statutsLabels;
        $this->table($pdf, 'Par statut',
            collect($agg['parStatut'])->mapWithKeys(fn ($n, $s) => [$statuts[$s] ?? $s => $n])->all());

        // Procédure × statut (tableau large)
        $this->sectionTitle($pdf, 'Par procédure × statut');
        $procs = \App\Models\Procedure::labels();
        $cols = array_values($statuts);
        $wLbl = 58;
        $wCol = (182 - $wLbl) / (count($cols) + 1);
        $pdf->SetFont('helvetica', 'B', 7.5);
        $pdf->SetFillColor(245, 248, 252);
        $pdf->SetTextColor(...self::MUTED);
        $pdf->Cell($wLbl, 6, 'Procédure', 1, 0, 'L', true);
        foreach ($cols as $c) {
            $pdf->Cell($wCol, 6, $c, 1, 0, 'C', true);
        }
        $pdf->Cell($wCol, 6, 'Total', 1, 1, 'C', true);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(...self::INK);
        foreach ($agg['parCatStatut'] as $cat => $byStatus) {
            $pdf->Cell($wLbl, 5.5, $procs[$cat] ?? $cat, 1, 0, 'L');
            foreach (array_keys($statuts) as $s) {
                $pdf->Cell($wCol, 5.5, (string) ($byStatus[$s] ?? 0), 1, 0, 'C');
            }
            $pdf->Cell($wCol, 5.5, (string) array_sum($byStatus), 1, 1, 'C');
        }
        $pdf->Ln(3);

        $this->table($pdf, 'Par faculté / institut', $agg['parFaculte']);
        $this->table($pdf, 'Top parcours', $agg['parParcours']);
        $this->table($pdf, "Par année d'études", $agg['parSemestre']);
        $this->table($pdf, 'Par sexe', $agg['parSexe']);
        $this->table($pdf, 'Par profil',
            collect($agg['parType'])->mapWithKeys(fn ($n, $t) => [($t === 'nouveau' ? 'Nouveaux bacheliers' : 'Anciens étudiants') => $n])->all());
        $this->table($pdf, 'Soumissions par mois', $agg['parMois']);

        return $pdf->Output('stats.pdf', 'S');
    }

    private function sectionTitle(\TCPDF $pdf, string $titre): void
    {
        $pdf->SetFont('helvetica', 'B', 9.5);
        $pdf->SetTextColor(...self::BRAND);
        $pdf->Cell(0, 6.5, $titre, 0, 1);
    }

    /** Petit tableau 2 colonnes (libellé / valeur). */
    private function table(\TCPDF $pdf, string $titre, array $data): void
    {
        if (empty($data)) {
            return;
        }
        $this->sectionTitle($pdf, $titre);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetTextColor(...self::INK);
        $pdf->SetDrawColor(225, 232, 242);
        foreach ($data as $k => $n) {
            $pdf->Cell(120, 5.5, (string) ($k ?: '—'), 1, 0, 'L');
            $pdf->Cell(30, 5.5, (string) $n, 1, 1, 'C');
        }
        $pdf->Ln(3);
    }
}
