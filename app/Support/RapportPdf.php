<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Base commune à tous les rapports PDF de la plateforme.
 *
 * Chaque page porte le logo de l'université en en-tête et, en pied,
 * le nom de la personne qui a généré le document — indispensable pour
 * qu'un rapport transmis à une autorité soit traçable.
 */
class RapportPdf extends \TCPDF
{
    public const BRAND = [26, 86, 168];
    public const INK   = [20, 36, 59];
    public const MUTED = [110, 122, 140];

    private string $titreRapport = '';
    private string $sousTitre    = '';
    private string $genereePar   = '';
    private string $filtres      = '';

    public function __construct(string $orientation = 'P')
    {
        parent::__construct($orientation, 'mm', 'A4', true, 'UTF-8');

        $this->SetCreator('Plateforme de dématérialisation — ' . $this->universite());
        $this->SetAuthor($this->universite());
        $this->setPrintHeader(true);
        $this->setPrintFooter(true);
        $this->SetMargins(14, 30, 14);      // marge haute : place pour l'en-tête
        $this->SetHeaderMargin(8);
        $this->SetFooterMargin(14);
        $this->SetAutoPageBreak(true, 20);
    }

    public function universite(): string
    {
        return Setting::get('app.university_name', 'Université de Kara');
    }

    /**
     * @param  string  $genereePar  nom de l'agent — apparaît sur chaque page
     */
    public function configurer(string $titre, string $sousTitre = '', string $genereePar = '', string $filtres = ''): static
    {
        $this->titreRapport = $titre;
        $this->sousTitre    = $sousTitre;
        $this->genereePar   = $genereePar;
        $this->filtres      = $filtres;
        $this->SetTitle($titre);

        return $this;
    }

    public function filtresLisibles(): string
    {
        return $this->filtres;
    }

    /* ── En-tête : logo + institution + titre ── */

    public function Header(): void
    {
        $logo = public_path('assets/uk/logo-sigle-bg.png');
        $x = 14;

        if (is_file($logo)) {
            // 389x129 → 26 mm de large conserve les proportions
            $this->Image($logo, $x, 9, 26, 0, 'PNG', '', 'T', false, 300, '', false, false, 0);
            $x += 31;
        }

        $this->SetXY($x, 10);
        $this->SetFont('helvetica', 'B', 12);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(0, 5.5, $this->universite(), 0, 1, 'L');

        $this->SetX($x);
        $this->SetFont('helvetica', 'B', 9.5);
        $this->SetTextColor(...self::INK);
        $this->Cell(0, 4.5, $this->titreRapport, 0, 1, 'L');

        if ($this->sousTitre !== '') {
            $this->SetX($x);
            $this->SetFont('helvetica', '', 8);
            $this->SetTextColor(...self::MUTED);
            $this->Cell(0, 4, $this->sousTitre, 0, 1, 'L');
        }

        // Filet de séparation
        $this->SetDrawColor(...self::BRAND);
        $this->SetLineWidth(.5);
        $this->Line(14, 26, $this->getPageWidth() - 14, 26);
    }

    /* ── Pied : auteur de la génération + date + pagination ── */

    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetDrawColor(225, 232, 242);
        $this->SetLineWidth(.2);
        $this->Line(14, $this->GetY(), $this->getPageWidth() - 14, $this->GetY());

        $this->SetY(-11);
        $this->SetFont('helvetica', '', 7.5);
        $this->SetTextColor(...self::MUTED);

        $gauche = 'Généré par ' . ($this->genereePar ?: '—') . ' le ' . now()->format('d/m/Y à H:i');
        $this->Cell(0, 4, $gauche, 0, 0, 'L');
        $this->Cell(0, 4, 'Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }

    /* ── Briques réutilisables ── */

    public function titreSection(string $titre): void
    {
        $this->SetFont('helvetica', 'B', 9.5);
        $this->SetTextColor(...self::BRAND);
        $this->Cell(0, 6.5, $titre, 0, 1);
    }

    /** Rappel des filtres appliqués, en tête de rapport. */
    public function blocFiltres(): void
    {
        if ($this->filtres === '') {
            return;
        }
        $this->SetFont('helvetica', 'B', 9);
        $this->SetTextColor(...self::INK);
        $this->Cell(0, 5.5, 'Périmètre du rapport', 0, 1);
        $this->SetFont('helvetica', '', 8.5);
        $this->SetTextColor(...self::MUTED);
        $this->MultiCell(0, 4.5, $this->filtres, 0, 'L');
        $this->Ln(2);
    }
}
