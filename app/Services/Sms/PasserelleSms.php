<?php

namespace App\Services\Sms;

/**
 * Contrat d'une passerelle SMS.
 *
 * Une implémentation par opérateur : Togocom (Yas Togo), Moov Africa,
 * plus une passerelle « journal » qui n'envoie rien et sert tant qu'aucun
 * contrat n'est signé. Changer de fournisseur = changer un réglage,
 * jamais le code métier.
 */
interface PasserelleSms
{
    /** Identifiant technique : togocom, moov, journal. */
    public function code(): string;

    public function nom(): string;

    /** Prête à envoyer ? (identifiants renseignés) */
    public function configuree(): bool;

    /**
     * Envoie un SMS.
     *
     * @return array{ok:bool, reference:?string, erreur:?string}
     */
    public function envoyer(string $numero, string $message): array;

    /**
     * Normalise un numéro togolais au format international.
     * « 90 11 22 33 » → « 22890112233 ».
     */
    public function normaliser(string $numero): ?string;
}
