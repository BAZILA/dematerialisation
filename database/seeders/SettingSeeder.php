<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // Général
            ['key' => 'app.university_name', 'value' => 'Université de Kara', 'type' => 'string', 'group' => 'general', 'label' => "Nom de l'université"],
            ['key' => 'app.short_name', 'value' => 'UK', 'type' => 'string', 'group' => 'general', 'label' => 'Sigle'],

            // Modules
            ['key' => 'candidatures.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'modules', 'label' => 'Candidatures en ligne', 'description' => 'Activer le portail public de candidature.'],
            ['key' => 'candidature.fees.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'modules', 'label' => 'Frais de candidature', 'description' => 'Exiger un paiement à la candidature.'],

            // Paiement
            ['key' => 'payments.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'paiement', 'label' => 'Paiement en ligne', 'description' => 'Interrupteur global du paiement en ligne.'],
            ['key' => 'payment.mobile_money.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'paiement', 'label' => 'Mobile Money (Flooz / T-Money)'],
            ['key' => 'payment.card.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'paiement', 'label' => 'Carte bancaire'],
            ['key' => 'payment.offline.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'paiement', 'label' => 'Paiement hors ligne (reçu)'],

            // Notifications
            ['key' => 'notifications.email.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'notifications', 'label' => 'Notifications e-mail'],
            ['key' => 'notifications.sms.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'notifications', 'label' => 'Notifications SMS'],

            // Affichage
            ['key' => 'affichage.per_page', 'value' => '20', 'type' => 'integer', 'group' => 'affichage', 'label' => 'Éléments par page', 'description' => "Nombre de lignes affichées par page dans les listes (demandes, archives, audit, utilisateurs…)."],

            // Recyclage (réinscription après interruption, par dérogation)
            ['key' => 'recyclage.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'recyclage', 'label' => 'Recyclage autorisé', 'description' => "Permettre la réinscription des étudiants après interruption."],
            ['key' => 'recyclage.max_annees', 'value' => '10', 'type' => 'integer', 'group' => 'recyclage', 'label' => "Nombre d'années maximum", 'description' => "Ancienneté maximale (en années depuis le BAC / la 1re inscription) admise en recyclage."],
            ['key' => 'recyclage.derogation_requise', 'value' => '1', 'type' => 'boolean', 'group' => 'recyclage', 'label' => 'Dérogation requise', 'description' => "Exiger une dérogation pour tout recyclage."],
            ['key' => 'depot.annees_derogatoires', 'value' => '2', 'type' => 'integer', 'group' => 'recyclage', 'label' => "Années dérogatoires (dépôt)", 'description' => "Nombre d'années de dépôt autorisées AU-DELÀ de la dernière année du grade (redoublement / retard). Ex. licence (3 ans) + 2 = dépôt possible jusqu'en 5e année."],

            // Notifications — interrupteur général dans « Modules »
            ['key' => 'notifications.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'modules', 'label' => 'Module de notifications', 'description' => "Prévenir les usagers par e-mail et SMS (dossier validé, candidature admise, paiement confirmé…). Désactivé, plus rien ne part : les envois sont consignés au statut « suspendue » pour que vous sachiez ce qui aurait été envoyé."],
            ['key' => 'sms.fournisseur', 'value' => 'journal', 'type' => 'string', 'group' => 'notifications', 'label' => 'Fournisseur SMS', 'description' => "togocom (Yas Togo), moov (Moov Africa), ou journal pour tester sans envoyer ni consommer de crédit."],
            ['key' => 'sms.togocom.url', 'value' => '', 'type' => 'string', 'group' => 'notifications', 'label' => 'Togocom — URL de l\'API', 'description' => "Fournie par Togocom lors de la signature du contrat marchand."],
            ['key' => 'sms.togocom.cle', 'value' => '', 'type' => 'string', 'group' => 'notifications', 'label' => 'Togocom — clé d\'API'],
            ['key' => 'sms.togocom.expediteur', 'value' => 'UK', 'type' => 'string', 'group' => 'notifications', 'label' => 'Togocom — nom d\'expéditeur', 'description' => "Affiché à la place du numéro sur le téléphone (11 caractères maximum)."],
            ['key' => 'sms.moov.url', 'value' => '', 'type' => 'string', 'group' => 'notifications', 'label' => 'Moov — URL de l\'API'],
            ['key' => 'sms.moov.cle', 'value' => '', 'type' => 'string', 'group' => 'notifications', 'label' => 'Moov — clé d\'API'],
            ['key' => 'sms.moov.expediteur', 'value' => 'UK', 'type' => 'string', 'group' => 'notifications', 'label' => 'Moov — nom d\'expéditeur'],

            // Paiement des frais de dossier (T-Money en priorité)
            ['key' => 'paiement.numero_marchand', 'value' => '', 'type' => 'string', 'group' => 'paiement', 'label' => 'Numéro marchand', 'description' => "Numéro sur lequel les usagers envoient les frais (T-Money / Flooz). Affiché dans les consignes de paiement."],
            ['key' => 'paiement.nom_marchand', 'value' => 'Université de Kara', 'type' => 'string', 'group' => 'paiement', 'label' => 'Nom du compte marchand', 'description' => "Nom affiché par l'opérateur lors du transfert — permet à l'usager de vérifier qu'il paie au bon destinataire."],
            ['key' => 'paiement.operateur_defaut', 'value' => 'tmoney', 'type' => 'string', 'group' => 'paiement', 'label' => 'Opérateur par défaut', 'description' => "tmoney, flooz, especes ou virement."],
            ['key' => 'paiement.devise', 'value' => 'XOF', 'type' => 'string', 'group' => 'paiement', 'label' => 'Devise', 'description' => "XOF pour le franc CFA."],
            ['key' => 'paiement.bloque_validation', 'value' => '1', 'type' => 'boolean', 'group' => 'paiement', 'label' => 'Bloquer la validation impayée', 'description' => "Empêcher un agent de valider un dossier dont les frais ne sont pas réglés. La permission « paiements.forcer » permet de passer outre au cas par cas."],

            // Sauvegarde automatique (base + pièces déposées)
            ['key' => 'sauvegarde.auto', 'value' => '1', 'type' => 'boolean', 'group' => 'sauvegarde', 'label' => 'Sauvegarde automatique', 'description' => "Créer chaque jour une archive de la base et des pièces déposées. Nécessite que le planificateur de tâches exécute « php artisan schedule:run » chaque minute."],
            ['key' => 'sauvegarde.heure', 'value' => '02:00', 'type' => 'string', 'group' => 'sauvegarde', 'label' => 'Heure de la sauvegarde', 'description' => "Heure quotidienne de la sauvegarde, au format HH:MM. Choisissez un créneau creux."],
            ['key' => 'sauvegarde.retention_jours', 'value' => '14', 'type' => 'integer', 'group' => 'sauvegarde', 'label' => 'Rétention (jours)', 'description' => "Les archives plus anciennes sont supprimées automatiquement. Attention à l'espace disque : chaque archive contient toutes les pièces."],
        ];

        foreach ($defaults as $d) {
            Setting::firstOrCreate(['key' => $d['key']], $d);
        }
    }
}
