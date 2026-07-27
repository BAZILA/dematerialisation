<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portée d'une pièce justificative : commune ou propre à des parcours.
 *
 * Aucune ligne pour un type de document = pièce COMMUNE (demandée à tous).
 * Une ou plusieurs lignes = pièce SPÉCIFIQUE, réclamée uniquement aux
 * candidats ayant choisi l'un de ces parcours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcours_request_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parcours_id')->constrained('parcours')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['request_type_id', 'parcours_id'], 'rt_parcours_unique');
            $table->index('parcours_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcours_request_type');
    }
};
