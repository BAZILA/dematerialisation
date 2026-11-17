<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_perimetres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parcours_id')->constrained('parcours')->cascadeOnDelete();
            // semestre null = tous les semestres de ce parcours
            $table->string('semestre', 20)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'parcours_id', 'semestre'], 'uniq_perimetre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_perimetres');
    }
};
