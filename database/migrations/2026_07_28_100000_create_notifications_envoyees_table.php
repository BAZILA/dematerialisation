<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des notifications (e-mail et SMS).
 *
 * Tracer les envois est indispensable : quand un étudiant affirme
 * n'avoir rien reçu, on doit pouvoir dire quand, sur quel numéro,
 * et ce que l'opérateur a répondu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_envoyees', function (Blueprint $table) {
            $table->id();
            $table->string('canal', 10);                 // email | sms
            $table->string('evenement', 60)->index();    // depot.valide, candidature.admise…
            $table->nullableMorphs('concerne');          // dossier / candidature / paiement
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('destinataire', 190);         // adresse e-mail ou numéro
            $table->string('sujet', 190)->nullable();
            $table->text('message');

            $table->string('statut', 20)->default('en_attente'); // en_attente|envoye|echec|suspendu
            $table->string('erreur', 500)->nullable();
            $table->string('fournisseur', 30)->nullable();       // togocom, moov, journal, smtp
            $table->string('reference_externe', 120)->nullable();// id retourné par l'opérateur
            $table->unsignedTinyInteger('tentatives')->default(0);
            $table->timestamp('envoye_at')->nullable();

            $table->timestamps();

            $table->index(['canal', 'statut']);
            $table->index(['statut', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_envoyees');
    }
};
