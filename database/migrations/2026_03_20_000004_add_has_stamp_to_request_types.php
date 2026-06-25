<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la colonne has_stamp à la table request_types.
 * N'exécuter que si la table existe déjà sans cette colonne.
 * Sinon modifier directement la migration de création.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->boolean('has_stamp')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->dropColumn('has_stamp');
        });
    }
};
