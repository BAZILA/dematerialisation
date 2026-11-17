<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('annees_academiques', function (Blueprint $table) {
            $table->id();
            $table->string('libelle')->unique();          // ex : "2025-2026"
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->enum('statut', ['en_preparation', 'ouverte', 'cloturee', 'archivee'])->default('en_preparation');
            $table->boolean('is_active')->default(false);  // année de contexte courante
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annees_academiques');
    }
};
