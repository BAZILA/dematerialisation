<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * L'université compte aussi des ÉCOLES, en plus des facultés et des instituts.
 * La colonne était un ENUM figé sur deux valeurs : on l'élargit.
 *
 * ENUM modifié en SQL brut : doctrine/dbal ne sait pas le faire proprement
 * et le change() de Laravel dégraderait la colonne en VARCHAR.
 *
 * ENUM n'existe que sous MySQL/MariaDB. Ailleurs — SQLite des tests, et
 * PostgreSQL si l'hébergement change un jour — la colonne est déjà un
 * texte libre : il n'y a rien à élargir, et lancer ce SQL casserait la
 * migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `facultes`
            MODIFY `type` ENUM('faculte','institut','ecole') NOT NULL DEFAULT 'faculte'");
    }

    public function down(): void
    {
        // Les écoles redeviennent des instituts, sinon la contrainte casse
        DB::table('facultes')->where('type', 'ecole')->update(['type' => 'institut']);

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `facultes`
            MODIFY `type` ENUM('faculte','institut') NOT NULL DEFAULT 'faculte'");
    }
};
