<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accès des usagers par CODE, sans mot de passe.
 *
 * Un étudiant qui vient une fois par an ne se souvient pas d'un mot de
 * passe : il le perd, le réinitialise, ou en choisit un faible. Il
 * prouve désormais son identité contre le référentiel, et reçoit un
 * code à usage unique valable quelques minutes.
 *
 * Le code est stocké HACHÉ : une fuite de la base ne permet pas de
 * s'en servir. Il n'est lisible qu'au moment où il est remis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes_acces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->timestamp('expire_at');
            $table->timestamp('utilise_at')->nullable();
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->string('canal', 20)->default('ecran');   // ecran | email
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            // Un usager n'a qu'un code vivant à la fois : on retrouve vite
            // le dernier émis, et on purge les périmés.
            $table->index(['user_id', 'expire_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_acces');
    }
};
