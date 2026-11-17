<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('periodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->string('operation'); // code de procédure (voir table procedures)
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->boolean('is_open')->default(false);
            $table->timestamps();
            $table->unique(['annee_academique_id', 'operation']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodes');
    }
};
