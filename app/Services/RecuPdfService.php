<?php

namespace App\Services;

use App\Models\Paiement;
use App\Support\RapportPdf;

/**
 * Reçu de paiement — pièce remise à l'usager après validation.
 * Reprend l'en-tête institutionnel (logo) et porte le nom de l'agent
 * qui a établi le reçu.
 */
class RecuPdfService
{
    public function build(Paiement $paiement, string $etabliPar = ''): string
    {
        $pdf = new RapportPdf('P');
        $pdf->configurer(
            'Reçu de paiement',
            'Référence ' . $paiement->reference,
            $etabliPar ?: ($paiement->validateur?->staff_name ?? '')
        );
        $pdf->AddPage();
        $pdf->Ln(4);

        /* ── Montant, en évidence ── */
        $pdf->SetFillColor(240, 245, 252);
        $y = $pdf->GetY();
        $pdf->RoundedRect(14, $y, 182, 24, 3, '1111', 'F');
        $pdf->SetXY(14, $y + 5);
        $pdf->SetFont('helvetica', 'B', 20);
        $pdf->SetTextColor(...RapportPdf::BRAND);
        $pdf->Cell(182, 9, $paiement->montant_formate, 0, 1, 'C');
        $pdf->SetX(14);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetTextColor(...RapportPdf::MUTED);
        $pdf->Cell(182, 5, 'Montant réglé', 0, 1, 'C');
        $pdf->SetY($y + 30);

        /* ── Détail ── */
        $payable   = $paiement->payable;
        $procedure = \App\Models\Procedure::where('code', $paiement->procedure_code)->first();

        $lignes = [
            'Référence du paiement' => $paiement->reference,
            'Objet'                 => $procedure?->nom ?? ($paiement->procedure_code ?: '—'),
            'Payeur'                => $this->nomPayeur($payable),
            'Identifiant du dossier' => $this->refDossier($payable),
            'Année académique'      => $paiement->anneeAcademique?->libelle ?? '—',
            'Opérateur'             => $paiement->operateur_label,
            'N° de transaction'     => $paiement->transaction_id ?: '—',
            'Numéro payeur'         => $paiement->numero_payeur ?: '—',
            'Déclaré le'            => $paiement->declare_at?->format('d/m/Y à H:i') ?? '—',
            'Validé le'             => $paiement->valide_at?->format('d/m/Y à H:i') ?? '—',
            'Mode de vérification'  => $paiement->verifie_auto ? 'Automatique (opérateur)' : 'Rapprochement par un agent',
        ];

        $pdf->titreSection('Détail du règlement');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetDrawColor(225, 232, 242);

        foreach ($lignes as $libelle => $valeur) {
            $pdf->SetTextColor(...RapportPdf::MUTED);
            $pdf->Cell(62, 7, $libelle, 1, 0, 'L');
            $pdf->SetTextColor(...RapportPdf::INK);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(120, 7, (string) $valeur, 1, 1, 'L');
            $pdf->SetFont('helvetica', '', 9);
        }

        $pdf->Ln(6);
        $pdf->SetFont('helvetica', '', 8.5);
        $pdf->SetTextColor(...RapportPdf::MUTED);
        $pdf->MultiCell(0, 4.5,
            "Ce reçu atteste du règlement des frais mentionnés ci-dessus. Il est généré "
            . "automatiquement par la plateforme et ne nécessite pas de signature manuscrite. "
            . "Sa référence permet d'en vérifier l'authenticité auprès de la scolarité.",
            0, 'L');

        return $pdf->Output('recu.pdf', 'S');
    }

    private function nomPayeur($payable): string
    {
        if ($payable instanceof \App\Models\Candidature) {
            return $payable->full_name;
        }
        if ($payable instanceof \App\Models\StudentRequest) {
            return $payable->student?->full_name ?? '—';
        }

        return '—';
    }

    private function refDossier($payable): string
    {
        if ($payable instanceof \App\Models\Candidature) {
            return $payable->reference;
        }
        if ($payable instanceof \App\Models\StudentRequest) {
            return ($payable->student?->user?->numero_identifiant ?? '—') . ' — demande #' . $payable->id;
        }

        return '—';
    }
}
