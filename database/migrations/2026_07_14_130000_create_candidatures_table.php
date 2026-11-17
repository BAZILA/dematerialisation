<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Candidatures (admissions) soumises ANONYMEMENT (sans compte) via le portail public.
 * Suivies par un numéro de référence.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('candidatures', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('nom');
            $table->string('prenom');
            $table->enum('sexe', ['M', 'F'])->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('email');
            $table->string('telephone')->nullable();
            $table->foreignId('parcours_id')->nullable()->constrained('parcours')->nullOnDelete();
            $table->foreignId('annee_academique_id')->nullable()->constrained('annees_academiques')->nullOnDelete();
            $table->enum('status', ['soumise', 'en_instruction', 'en_correction', 'admis', 'refuse'])->default('soumise');
            $table->text('admin_comment')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decision_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidatures');
    }
};
