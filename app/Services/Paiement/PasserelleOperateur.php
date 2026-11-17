<?php

namespace App\Services\Paiement;

use App\Models\Paiement;

/**
 * Contrat d'une passerelle opérateur (T-Money, Flooz…).
 *
 * Tant qu'aucun contrat marchand n'est signé, c'est la passerelle
 * MANUELLE qui est utilisée : l'usager paie de son côté et déclare sa
 * transaction, un agent rapproche. Le jour où l'API Togocom est
 * disponible, il suffit d'écrire une classe implémentant ce contrat
 * et de la déclarer dans le conteneur : ni le parcours de l'usager,
 * ni le schéma de base, ni les écrans agents ne changent.
 */
interface PasserelleOperateur
{
    /** Identifiant technique : tmoney, flooz… */
    public function code(): string;

    /** La vérification est-elle automatique ? (false = rapprochement humain) */
    public function estAutomatique(): bool;

    /**
     * Vérifie une transaction auprès de l'opérateur.
     *
     * @return array{confirme:bool, message:string, meta:array}
     *         confirme=false avec la passerelle manuelle : c'est l'agent qui tranche.
     */
    public function verifier(Paiement $paiement): array;

    /** Consignes affichées à l'usager pour payer. */
    public function instructions(Paiement $paiement): array;
}
