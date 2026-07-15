<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opérations / procédures dématérialisées (dépôt, attestation, candidature…).
 * Remplace les enums codés en dur : gérable depuis l'interface.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('procedures', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // ex : depot, attestation, candidature…
            $table->string('nom');
            $table->text('description')->nullable();
            $table->boolean('requires_auth')->default(true);   // false = soumission anonyme (candidatures)
            $table->boolean('pour_nouveaux')->default(false);  // visible aux nouveaux bacheliers
            $table->boolean('pour_anciens')->default(true);    // visible aux anciens étudiants
            $table->boolean('is_active')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procedures');
    }
};
