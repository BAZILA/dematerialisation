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
        ];

        foreach ($defaults as $d) {
            Setting::firstOrCreate(['key' => $d['key']], $d);
        }
    }
}
