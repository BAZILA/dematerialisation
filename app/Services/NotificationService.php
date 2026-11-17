<?php

namespace App\Services;

use App\Models\NotificationEnvoyee;
use App\Models\Setting;
use App\Services\Sms\PasserelleSms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envoi des notifications aux usagers, par e-mail et par SMS.
 *
 * Tout passe par ici : un interrupteur général suspend l'ensemble, et
 * chaque canal a le sien. Suspendu, l'envoi est quand même consigné au
 * statut « suspendu » — on sait donc ce qui aurait été envoyé.
 */
class NotificationService
{
    /** Modèles de messages : sujet e-mail, corps e-mail, texte SMS (court). */
    public const MODELES = [
        'depot.valide' => [
            'sujet' => 'Votre dossier a été validé',
            'email' => "Bonjour :nom,\n\nVotre :procedure pour l'année :annee a été VALIDÉ.\n\n"
                . "Vous pouvez télécharger vos pièces tamponnées depuis votre espace personnel.\n\n:universite",
            'sms'   => ":universite : votre :procedure (:annee) est VALIDÉ. Connectez-vous pour récupérer vos pièces.",
        ],
        'depot.correction' => [
            'sujet' => 'Votre dossier doit être corrigé',
            'email' => "Bonjour :nom,\n\nVotre :procedure nécessite une correction :\n\n:motif\n\n"
                . "Connectez-vous pour remplacer les pièces concernées.\n\n:universite",
            'sms'   => ":universite : votre :procedure doit être corrigé. Motif : :motif. Connectez-vous.",
        ],
        'depot.rejete' => [
            'sujet' => 'Votre dossier a été rejeté',
            'email' => "Bonjour :nom,\n\nVotre :procedure a été rejeté.\n\nMotif : :motif\n\n"
                . "Rapprochez-vous de la scolarité pour toute question.\n\n:universite",
            'sms'   => ":universite : votre :procedure a été rejeté. Motif : :motif",
        ],
        'candidature.admise' => [
            'sujet' => 'Votre candidature est acceptée',
            'email' => "Bonjour :nom,\n\nFélicitations : votre candidature :reference est ACCEPTÉE"
                . " (:parcours).\n\nPrésentez-vous à la scolarité avec votre numéro de suivi"
                . " pour finaliser votre inscription.\n\n:universite",
            'sms'   => "Felicitations :nom ! Candidature :reference ACCEPTEE (:parcours). Presentez-vous a la scolarite. :universite",
        ],
        'candidature.refusee' => [
            'sujet' => 'Suite donnée à votre candidature',
            'email' => "Bonjour :nom,\n\nVotre candidature :reference n'a pas été retenue.\n\n:motif\n\n"
                . "Nous vous remercions de l'intérêt porté à notre établissement.\n\n:universite",
            'sms'   => ":universite : votre candidature :reference n'a pas ete retenue.",
        ],
        'candidature.correction' => [
            'sujet' => 'Votre candidature doit être complétée',
            'email' => "Bonjour :nom,\n\nVotre candidature :reference est incomplète :\n\n:motif\n\n"
                . "Rendez-vous sur la page de suivi avec votre numéro de suivi pour la compléter.\n\n:universite",
            'sms'   => ":universite : candidature :reference a completer. :motif",
        ],
        // Relances automatiques : le dossier dort, on rappelle l'usager
        'candidature.relance' => [
            'sujet' => 'Rappel — votre candidature attend vos pièces',
            'email' => "Bonjour :nom,\n\nVotre candidature :reference est toujours incomplète depuis :jours jour(s).\n\n"
                . ":motif\n\nSans réponse de votre part, elle ne pourra pas être examinée.\n"
                . "Rendez-vous sur la page de suivi avec votre numéro de suivi pour la compléter.\n\n:universite",
            'sms'   => ":universite : candidature :reference toujours incomplete depuis :jours jour(s). Completez-la sur la page de suivi.",
        ],
        'depot.relance' => [
            'sujet' => 'Rappel — votre dossier attend une correction',
            'email' => "Bonjour :nom,\n\nVotre :procedure attend une correction depuis :jours jour(s).\n\n"
                . ":motif\n\nConnectez-vous à votre espace personnel pour remplacer les pièces concernées.\n\n:universite",
            'sms'   => ":universite : votre :procedure attend une correction depuis :jours jour(s). Connectez-vous.",
        ],
        'acces.code' => [
            'sujet' => "Votre code d'accès",
            'email' => "Bonjour :nom,

Voici votre code d'accès : :code

"
                . "Il est valable :minutes minutes et ne sert qu'une fois. "
                . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.

:universite",
            'sms'   => ":universite : votre code d'acces est :code (valable :minutes min).",
        ],
        'paiement.valide' => [
            'sujet' => 'Paiement confirmé',
            'email' => "Bonjour :nom,\n\nVotre paiement de :montant (référence :reference) a bien été enregistré.\n\n"
                . "Votre dossier suit désormais son cours.\n\n:universite",
            'sms'   => ":universite : paiement de :montant confirme (ref :reference). Merci.",
        ],
        'paiement.rejete' => [
            'sujet' => 'Votre paiement n\'a pas pu être confirmé',
            'email' => "Bonjour :nom,\n\nLe versement déclaré pour la référence :reference n'a pas pu être confirmé.\n\n"
                . "Motif : :motif\n\nVous pouvez déclarer à nouveau votre transaction.\n\n:universite",
            'sms'   => ":universite : versement :reference non confirme. :motif Redeclarez votre transaction.",
        ],
    ];

    public function __construct(private PasserelleSms $sms)
    {
    }

    public function passerelleSms(): PasserelleSms
    {
        return $this->sms;
    }

    /**
     * Notifie un usager sur les canaux actifs.
     *
     * @param  array{email?:?string, telephone?:?string, nom?:string}  $destinataire
     * @param  array<string,string>  $variables  remplacent :nom, :motif…
     * @return array<NotificationEnvoyee>
     */
    public function notifier(string $evenement, array $destinataire, array $variables = [], ?Model $concerne = null, ?int $userId = null): array
    {
        $modele = self::MODELES[$evenement] ?? null;
        if (!$modele) {
            Log::warning("Notification inconnue : {$evenement}");

            return [];
        }

        $variables += [
            'universite' => Setting::get('app.university_name', 'Université de Kara'),
            'nom'        => $destinataire['nom'] ?? '',
        ];

        $envoyees = [];

        if (filled($destinataire['email'] ?? null)) {
            $envoyees[] = $this->traiter('email', $evenement, $destinataire['email'],
                $this->remplacer($modele['sujet'], $variables),
                $this->remplacer($modele['email'], $variables),
                $concerne, $userId);
        }

        if (filled($destinataire['telephone'] ?? null)) {
            $envoyees[] = $this->traiter('sms', $evenement, $destinataire['telephone'],
                null,
                $this->remplacer($modele['sms'], $variables),
                $concerne, $userId);
        }

        return array_filter($envoyees);
    }

    /** Consigne puis expédie (ou suspend) une notification. */
    private function traiter(string $canal, string $evenement, string $destinataire, ?string $sujet, string $message, ?Model $concerne, ?int $userId): NotificationEnvoyee
    {
        $actif = NotificationEnvoyee::canalActif($canal);

        $n = NotificationEnvoyee::create([
            'canal'         => $canal,
            'evenement'     => $evenement,
            'concerne_type' => $concerne?->getMorphClass(),
            'concerne_id'   => $concerne?->getKey(),
            'user_id'       => $userId,
            'destinataire'  => $destinataire,
            'sujet'         => $sujet,
            'message'       => $message,
            // Module ou canal éteint : rien ne part, mais la trace existe
            'statut'        => $actif ? 'en_attente' : 'suspendu',
            'fournisseur'   => $canal === 'sms' ? $this->sms->code() : 'smtp',
        ]);

        if ($actif) {
            $this->expedier($n);
        }

        return $n;
    }

    /** Expédition réelle. Isolée pour être rejouable depuis un worker. */
    public function expedier(NotificationEnvoyee $n): bool
    {
        if (!NotificationEnvoyee::canalActif($n->canal)) {
            $n->update(['statut' => 'suspendu']);

            return false;
        }

        $n->increment('tentatives');

        try {
            if ($n->canal === 'sms') {
                $r = $this->sms->envoyer($n->destinataire, $n->message);
                $n->update($r['ok']
                    ? ['statut' => 'envoye', 'envoye_at' => now(), 'reference_externe' => $r['reference'], 'erreur' => null]
                    : ['statut' => 'echec', 'erreur' => mb_substr((string) $r['erreur'], 0, 500)]);

                return $r['ok'];
            }

            Mail::raw($n->message, function ($m) use ($n) {
                $m->to($n->destinataire)->subject($n->sujet ?: 'Notification');
            });
            $n->update(['statut' => 'envoye', 'envoye_at' => now(), 'erreur' => null]);

            return true;

        } catch (\Throwable $e) {
            Log::error("Notification {$n->canal} #{$n->id} : " . $e->getMessage());
            $n->update(['statut' => 'echec', 'erreur' => mb_substr($e->getMessage(), 0, 500)]);

            return false;
        }
    }

    private function remplacer(string $texte, array $variables): string
    {
        foreach ($variables as $cle => $valeur) {
            $texte = str_replace(':' . $cle, (string) $valeur, $texte);
        }

        // Une variable non fournie ne doit pas laisser « :motif » dans le message
        return trim(preg_replace('/\s*:[a-z_]+/', '', $texte));
    }
}
