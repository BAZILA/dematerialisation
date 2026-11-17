<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiements des frais de dossier (T-Money en priorité).
 *
 * Fonctionne d'abord en MANUEL : l'usager paie sur le numéro marchand,
 * saisit son identifiant de transaction, un agent rapproche et valide.
 * Les colonnes `operateur`, `transaction_id`, `verifie_auto` et `meta`
 * sont prévues pour l'API Togocom : le branchement futur ne changera
 * ni le schéma ni le parcours de l'usager.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Frais propres à chaque procédure (dépôt, attestation, candidature…)
        Schema::table('procedures', function (Blueprint $table) {
            $table->boolean('frais_actifs')->default(false)->after('is_active');
            $table->unsignedInteger('frais_montant')->default(0)->after('frais_actifs');
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();

            // Objet payé : une demande étudiante OU une candidature
            $table->nullableMorphs('payable');
            $table->string('procedure_code', 50)->nullable()->index();
            $table->foreignId('annee_academique_id')->nullable()->constrained('annees_academiques')->nullOnDelete();

            $table->unsignedInteger('montant');                 // en francs CFA, entier
            $table->string('devise', 5)->default('XOF');
            $table->string('operateur', 20)->default('tmoney'); // tmoney | flooz | especes | virement

            // Renseignés par l'usager
            $table->string('numero_payeur', 30)->nullable();
            $table->string('transaction_id', 80)->nullable()->index();
            $table->timestamp('declare_at')->nullable();

            $table->string('statut', 20)->default('en_attente'); // en_attente|soumis|valide|rejete|rembourse
            $table->string('motif_rejet', 500)->nullable();

            // Rapprochement
            $table->foreignId('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_at')->nullable();
            $table->boolean('verifie_auto')->default(false);    // true = confirmé par l'API opérateur
            $table->json('meta')->nullable();                   // réponse brute de l'opérateur

            $table->timestamps();

            $table->index(['statut', 'created_at']);
            $table->index(['annee_academique_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');

        Schema::table('procedures', function (Blueprint $table) {
            $table->dropColumn(['frais_actifs', 'frais_montant']);
        });
    }
};
