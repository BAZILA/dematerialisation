<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lignes « Études antérieures » du verso de la fiche de dépôt.
 * Rattachées à la demande (student_request) de catégorie « depot ».
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('etudes_anterieures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_request_id')->constrained('student_requests')->cascadeOnDelete();
            $table->string('annee_debut', 4)->nullable();
            $table->string('annee_fin', 4)->nullable();
            $table->string('universite')->nullable();
            $table->string('parcours')->nullable();
            $table->integer('credits')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etudes_anterieures');
    }
};
