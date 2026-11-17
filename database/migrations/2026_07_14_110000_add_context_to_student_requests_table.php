<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contexte académique d'une demande (surtout les dépôts) :
 * l'étudiant choisit d'abord son parcours (donc faculté/département), son année et son semestre.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_requests', function (Blueprint $table) {
            $table->foreignId('parcours_id')->nullable()->after('student_id')->constrained('parcours')->nullOnDelete();
            $table->foreignId('annee_academique_id')->nullable()->after('parcours_id')->constrained('annees_academiques')->nullOnDelete();
            $table->string('semestre')->nullable()->after('annee_academique_id');
            $table->boolean('engagement_accepte')->default(false)->after('semestre');
        });
    }

    public function down(): void
    {
        Schema::table('student_requests', function (Blueprint $table) {
            $table->dropForeign(['parcours_id']);
            $table->dropForeign(['annee_academique_id']);
            $table->dropColumn(['parcours_id', 'annee_academique_id', 'semestre', 'engagement_accepte']);
        });
    }
};
