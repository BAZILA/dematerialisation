<?php

namespace App\Services\Sms;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Socle commun aux passerelles SMS des opérateurs togolais.
 *
 * Togocom (Yas Togo) et Moov Africa exposent des API HTTP très proches :
 * une URL, une clé, un identifiant d'expéditeur, un POST JSON. Le détail
 * exact (noms des champs) est fixé par le contrat marchand — d'où des
 * réglages plutôt que des valeurs codées en dur.
 */
abstract class PasserelleSmsBase implements PasserelleSms
{
    /** Préfixe des réglages : sms.togocom.url, sms.moov.cle… */
    protected function reglage(string $suffixe, $defaut = null)
    {
        return Setting::get("sms.{$this->code()}.{$suffixe}", $defaut);
    }

    public function configuree(): bool
    {
        return filled($this->reglage('url')) && filled($this->reglage('cle'));
    }

    /**
     * Numéro togolais au format international, sans « + ».
     * Accepte « 90112233 », « +228 90 11 22 33 », « 00228-90112233 ».
     */
    public function normaliser(string $numero): ?string
    {
        $n = preg_replace('/\D+/', '', $numero) ?? '';

        if (str_starts_with($n, '00228')) {
            $n = substr($n, 5);
        } elseif (str_starts_with($n, '228') && strlen($n) > 8) {
            $n = substr($n, 3);
        }

        // Un numéro mobile togolais compte 8 chiffres
        return strlen($n) === 8 ? '228' . $n : null;
    }

    public function envoyer(string $numero, string $message): array
    {
        $destinataire = $this->normaliser($numero);
        if (!$destinataire) {
            return ['ok' => false, 'reference' => null, 'erreur' => "Numéro invalide : {$numero}"];
        }
        if (!$this->configuree()) {
            return ['ok' => false, 'reference' => null,
                    'erreur' => "Passerelle {$this->nom()} non configurée (URL et clé requises)."];
        }

        try {
            $reponse = Http::timeout(20)
                ->withToken((string) $this->reglage('cle'))
                ->acceptJson()
                ->post((string) $this->reglage('url'), [
                    'from'    => (string) $this->reglage('expediteur', Setting::get('app.short_name', 'UK')),
                    'to'      => $destinataire,
                    'message' => $message,
                ]);

            if ($reponse->successful()) {
                return [
                    'ok'        => true,
                    // Les opérateurs nomment cet identifiant différemment
                    'reference' => $reponse->json('message_id')
                        ?? $reponse->json('id')
                        ?? $reponse->json('reference'),
                    'erreur'    => null,
                ];
            }

            return ['ok' => false, 'reference' => null,
                    'erreur' => 'HTTP ' . $reponse->status() . ' — ' . mb_substr($reponse->body(), 0, 300)];

        } catch (\Throwable $e) {
            Log::error("SMS {$this->nom()} : " . $e->getMessage());

            return ['ok' => false, 'reference' => null, 'erreur' => $e->getMessage()];
        }
    }
}
