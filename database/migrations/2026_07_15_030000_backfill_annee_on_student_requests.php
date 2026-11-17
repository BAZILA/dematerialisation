<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Toutes les demandes doivent porter leur année académique
     * (pré-requis à l'isolation des données par année).
     */
    public function up(): void
    {
        $activeId = DB::table('annees_academiques')->where('is_active', true)->value('id');

        if ($activeId) {
            DB::table('student_requests')->whereNull('annee_academique_id')->update([
                'annee_academique_id' => $activeId,
            ]);
        }
    }

    public function down(): void
    {
        // Pas de retour arrière : on ne peut pas distinguer les lignes rétro-remplies.
    }
};
