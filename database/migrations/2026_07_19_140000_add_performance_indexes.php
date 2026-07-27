<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index composites pour les listes les plus consultées :
 * demandes (année + catégorie + statut), archives, statistiques, candidatures.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_requests', function (Blueprint $table) {
            $table->index(['annee_academique_id', 'category', 'status'], 'sr_annee_cat_status_idx');
            $table->index(['category', 'status'], 'sr_cat_status_idx');
        });

        Schema::table('candidatures', function (Blueprint $table) {
            $table->index('status', 'cand_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('student_requests', function (Blueprint $table) {
            $table->dropIndex('sr_annee_cat_status_idx');
            $table->dropIndex('sr_cat_status_idx');
        });

        Schema::table('candidatures', function (Blueprint $table) {
            $table->dropIndex('cand_status_idx');
        });
    }
};
