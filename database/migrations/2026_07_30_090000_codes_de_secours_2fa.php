<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codes de secours pour la double authentification.
 *
 * Sans eux, un téléphone perdu ou réinitialisé bloque totalement le compte :
 * il faut alors qu'un gestionnaire intervienne. Les codes sont stockés
 * hachés — une fuite de la base ne permet donc pas de s'en servir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_recovery_codes');
        });
    }
};
