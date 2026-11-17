<?php

namespace App\Services\Paiement;

use App\Models\Paiement;
use App\Models\Setting;

/**
 * Passerelle par défaut : paiement hors ligne sur le numéro marchand,
 * déclaration de la transaction par l'usager, rapprochement par un agent.
 *
 * Aucun appel réseau — c'est volontaire tant que le contrat marchand
 * Togocom n'est pas en place.
 */
class PasserelleManuelle implements PasserelleOperateur
{
    public function code(): string
    {
        return (string) Setting::get('paiement.operateur_defaut', 'tmoney');
    }

    public function estAutomatique(): bool
    {
        return false;
    }

    public function verifier(Paiement $paiement): array
    {
        return [
            'confirme' => false,
            'message'  => "Vérification manuelle : un agent rapproche votre transaction avec le relevé de l'opérateur.",
            'meta'     => ['passerelle' => 'manuelle'],
        ];
    }

    public function instructions(Paiement $paiement): array
    {
        $numero    = (string) Setting::get('paiement.numero_marchand', '');
        $marchand  = (string) Setting::get('paiement.nom_marchand', Setting::get('app.university_name', 'Université de Kara'));
        $operateur = Paiement::$operateurs[$paiement->operateur] ?? $paiement->operateur;

        return [
            'operateur' => $operateur,
            'numero'    => $numero,
            'marchand'  => $marchand,
            'montant'   => $paiement->montant_formate,
            'reference' => $paiement->reference,
            'etapes'    => array_values(array_filter([
                $numero
                    ? "Composez le menu {$operateur} sur votre téléphone et envoyez {$paiement->montant_formate} au numéro {$numero} ({$marchand})."
                    : "Contactez la scolarité : le numéro marchand n'est pas encore renseigné sur la plateforme.",
                "Indiquez « {$paiement->reference} » en motif si l'opérateur le permet.",
                "Conservez le SMS de confirmation : il contient l'identifiant de la transaction.",
                "Saisissez ci-dessous cet identifiant ainsi que le numéro utilisé pour payer.",
                "Un agent vérifie votre versement, puis votre dossier suit son cours.",
            ])),
        ];
    }
}
