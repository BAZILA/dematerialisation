<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revue pièce par pièce d'une candidature.
 *
 * L'agent valide ou rejette CHAQUE pièce avec un motif. Le candidat rappelé
 * en correction ne peut alors remplacer que les pièces rejetées : celles
 * déjà validées lui sont verrouillées, et le motif s'affiche en face du champ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidature_files', function (Blueprint $table) {
            $table->string('statut', 20)->default('fourni')->after('request_type_id');
            $table->string('motif_rejet', 500)->nullable()->after('statut');
            $table->foreignId('verifie_par')->nullable()->after('motif_rejet')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('verifie_at')->nullable()->after('verifie_par');

            $table->index(['candidature_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::table('candidature_files', function (Blueprint $table) {
            $table->dropIndex(['candidature_id', 'statut']);
            $table->dropConstrainedForeignId('verifie_par');
            $table->dropColumn(['statut', 'motif_rejet', 'verifie_at']);
        });
    }
};
