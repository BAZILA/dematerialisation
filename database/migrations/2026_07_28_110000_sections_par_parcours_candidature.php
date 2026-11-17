<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Candidature en SECTIONS : une section par parcours visé.
 *
 * Le candidat constitue son dossier une fois (pièces communes), puis
 * postule à autant de parcours qu'il veut. Chaque section porte
 * uniquement les pièces PROPRES à son parcours et sa propre quittance ;
 * les pièces communes sont partagées et ne sont jamais redemandées.
 *
 * candidature_files.candidature_choix_id :
 *   NULL  → pièce commune, valable pour toutes les sections
 *   rempli → pièce exigée par ce parcours précis
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidature_files', function (Blueprint $table) {
            $table->foreignId('candidature_choix_id')->nullable()->after('candidature_id')
                ->constrained('candidature_choix')->cascadeOnDelete();
            $table->index(['candidature_id', 'candidature_choix_id'], 'cf_dossier_section');
        });

        Schema::table('candidature_choix', function (Blueprint $table) {
            $table->timestamp('soumis_at')->nullable()->after('ordre');
        });

        // Les sections existantes datent de la création de leur dossier.
        // UPDATE ... JOIN est de la syntaxe MySQL ; ailleurs (SQLite des
        // tests) il n'y a de toute façon aucune ligne à reprendre.
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
            return;
        }

        \Illuminate\Support\Facades\DB::statement(
            'UPDATE candidature_choix c
             JOIN candidatures d ON d.id = c.candidature_id
             SET c.soumis_at = d.created_at
             WHERE c.soumis_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table('candidature_files', function (Blueprint $table) {
            $table->dropIndex('cf_dossier_section');
            $table->dropConstrainedForeignId('candidature_choix_id');
        });

        Schema::table('candidature_choix', function (Blueprint $table) {
            $table->dropColumn('soumis_at');
        });
    }
};
