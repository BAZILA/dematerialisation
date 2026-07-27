<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Candidatures multi-parcours : un candidat classe jusqu'à 3 choix par
 * ordre de préférence ; l'admission est prononcée sur UN des choix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidature_choix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidature_id')->constrained('candidatures')->cascadeOnDelete();
            $table->foreignId('parcours_id')->constrained('parcours')->cascadeOnDelete();
            $table->unsignedTinyInteger('ordre'); // 1 = premier choix
            $table->timestamps();
            $table->unique(['candidature_id', 'parcours_id']);
            $table->unique(['candidature_id', 'ordre']);
        });

        Schema::table('candidatures', function (Blueprint $table) {
            // Parcours retenu à l'admission (l'un des choix)
            $table->foreignId('parcours_admis_id')->nullable()->after('parcours_id')
                ->constrained('parcours')->nullOnDelete();
        });

        // Reprise : le parcours unique existant devient le 1er choix
        foreach (DB::table('candidatures')->whereNotNull('parcours_id')->get(['id', 'parcours_id']) as $c) {
            DB::table('candidature_choix')->updateOrInsert(
                ['candidature_id' => $c->id, 'parcours_id' => $c->parcours_id],
                ['ordre' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::table('candidatures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parcours_admis_id');
        });
        Schema::dropIfExists('candidature_choix');
    }
};
