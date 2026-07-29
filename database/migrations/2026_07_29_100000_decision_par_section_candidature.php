<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Décision PAR PARCOURS.
 *
 * Un candidat peut être admis sur plusieurs parcours à la fois : c'est
 * ensuite, sur la plateforme d'inscription, qu'il choisit celui où il
 * s'inscrit réellement. Chaque section porte donc sa propre décision,
 * rendue par l'agent dont le périmètre couvre ce parcours.
 *
 * Le statut du dossier (candidatures.status) reste, calculé à partir
 * des sections : il sert aux filtres et aux écrans de synthèse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidature_choix', function (Blueprint $table) {
            $table->string('statut', 20)->default('soumise')->after('soumis_at');
            $table->string('motif', 1000)->nullable()->after('statut');
            $table->foreignId('decide_par')->nullable()->after('motif')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('decide_at')->nullable()->after('decide_par');

            $table->index(['statut', 'parcours_id']);
        });

        // Report de l'ancienne décision globale sur les sections
        DB::statement("
            UPDATE candidature_choix c
            JOIN candidatures d ON d.id = c.candidature_id
            SET c.statut = CASE
                    WHEN d.status = 'admis'  AND d.parcours_admis_id = c.parcours_id THEN 'admis'
                    WHEN d.status = 'admis'  AND d.parcours_admis_id <> c.parcours_id THEN 'refuse'
                    WHEN d.status = 'refuse' THEN 'refuse'
                    ELSE d.status
                END,
                c.motif      = d.admin_comment,
                c.decide_par = d.traite_par,
                c.decide_at  = d.decision_at
        ");
    }

    public function down(): void
    {
        Schema::table('candidature_choix', function (Blueprint $table) {
            $table->dropIndex(['statut', 'parcours_id']);
            $table->dropConstrainedForeignId('decide_par');
            $table->dropColumn(['statut', 'motif', 'decide_at']);
        });
    }
};
