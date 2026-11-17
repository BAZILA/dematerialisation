<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * Passerelle par défaut : n'envoie RIEN, écrit dans le journal.
 *
 * C'est le mode d'attente tant qu'aucun contrat opérateur n'est signé.
 * Le reste de l'application se comporte exactement comme en production,
 * ce qui permet de tout tester sans consommer de crédit SMS.
 */
class SmsJournal extends PasserelleSmsBase
{
    public function code(): string
    {
        return 'journal';
    }

    public function nom(): string
    {
        return 'Journal (aucun envoi réel)';
    }

    public function configuree(): bool
    {
        return true;
    }

    public function envoyer(string $numero, string $message): array
    {
        $destinataire = $this->normaliser($numero);
        if (!$destinataire) {
            return ['ok' => false, 'reference' => null, 'erreur' => "Numéro invalide : {$numero}"];
        }

        Log::info("[SMS simulé] {$destinataire} : {$message}");

        return ['ok' => true, 'reference' => 'JOURNAL-' . uniqid(), 'erreur' => null];
    }
}
