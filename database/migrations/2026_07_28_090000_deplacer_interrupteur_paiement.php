<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * « payments.enabled » est l'interrupteur général du module de paiement :
 * il doit rester visible même module éteint, sinon plus moyen de le
 * rallumer. On le range donc avec les autres modules, ce qui permet de
 * masquer intégralement le groupe « Paiement » quand il est désactivé.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'payments.enabled')->update([
            'group'       => 'modules',
            'label'       => 'Module de paiement',
            'description' => "Frais de dossier (T-Money, Flooz…). Désactivé, TOUT ce qui concerne "
                . "le paiement disparaît de la plateforme : menu, écrans agents, encadrés usagers, "
                . "frais des procédures. Les paiements déjà enregistrés sont conservés et réapparaissent "
                . "à la réactivation.",
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'payments.enabled')->update([
            'group' => 'paiement',
            'label' => 'Paiement en ligne',
        ]);
    }
};
