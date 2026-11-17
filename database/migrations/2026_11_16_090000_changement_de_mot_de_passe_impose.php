<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un mot de passe provisoire doit le rester.
 *
 * Quand l'administration crée un compte ou remet un mot de passe, elle
 * le connaît. Tant que le titulaire ne l'a pas changé, deux personnes
 * peuvent se connecter sous son nom — et le journal d'audit ne sait
 * plus dire laquelle a agi. Ce drapeau impose le changement à la
 * première connexion.
 *
 * Les comptes existants ne sont pas touchés : le drapeau naît à faux.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'doit_changer_mot_de_passe')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('doit_changer_mot_de_passe')->default(false)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'doit_changer_mot_de_passe')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('doit_changer_mot_de_passe');
            });
        }
    }
};
