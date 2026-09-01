<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dérogation sur une pièce obligatoire manquante.
 *
 * Un bachelier n'a pas encore son certificat de nationalité : le
 * document met des semaines à sortir, et la période de dépôt, elle,
 * se ferme. Jusqu'ici il était bloqué. Il s'engage désormais à le
 * fournir plus tard, et son dossier avance.
 *
 * La pièce reste DUE : la dérogation la diffère, elle ne l'annule pas.
 * Le dossier porte la mention tant qu'un agent n'a pas constaté
 * l'arrivée du document — c'est ce qui fait que les archives disent
 * toujours la vérité sur ce qui manquait.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Toutes les pièces ne se prêtent pas au report : l'administration
        // décide lesquelles, pièce par pièce. Par défaut, aucune.
        if (!Schema::hasColumn('request_types', 'derogation_possible')) {
            Schema::table('request_types', function (Blueprint $table) {
                $table->boolean('derogation_possible')->default(false)->after('is_required');
            });
        }

        Schema::create('derogations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_type_id')->constrained()->cascadeOnDelete();

            // engagee : la pièce est attendue · levee : elle est arrivée
            // refusee : l'agent a recalé le dossier sur ce point
            $table->string('statut', 20)->default('engagee');

            $table->timestamp('engagee_at');
            $table->timestamp('levee_at')->nullable();
            $table->foreignId('levee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motif', 500)->nullable();
            $table->timestamps();

            // Une seule dérogation vivante par pièce et par dossier
            $table->unique(['student_request_id', 'request_type_id'], 'derogation_unique');
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('derogations');

        if (Schema::hasColumn('request_types', 'derogation_possible')) {
            Schema::table('request_types', function (Blueprint $table) {
                $table->dropColumn('derogation_possible');
            });
        }
    }
};
