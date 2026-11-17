<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le courriel devient facultatif.
 *
 * Un usager qui se connecte par code voit son compte créé à la volée
 * depuis le référentiel. Or les 1165 anciens étudiants ont été importés
 * sans adresse : l'insertion tombait alors sur « Column 'email' cannot
 * be null » et l'usager recevait une page 500 en pleine connexion.
 *
 * L'identification se fait par « numero_identifiant », jamais par le
 * courriel : rien n'exige qu'il soit renseigné. L'index d'unicité est
 * conservé — MySQL accepte plusieurs NULL dans un index unique, si bien
 * que les comptes sans adresse ne se gênent pas entre eux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        /* Revenir en arrière exige de remplir les adresses manquantes,
           sinon la contrainte ne peut pas se poser. On leur donne une
           adresse interne, invalide à dessein : elle ne doit jamais
           servir à écrire à quiconque. */
        \Illuminate\Support\Facades\DB::table('users')->whereNull('email')->update([
            'email' => \Illuminate\Support\Facades\DB::raw(
                "CONCAT('sans-adresse.', numero_identifiant, '@invalide.local')"
            ),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
