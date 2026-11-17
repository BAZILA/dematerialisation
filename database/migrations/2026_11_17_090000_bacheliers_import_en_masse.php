<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prépare la table des bacheliers à recevoir tout le fichier national
 * (plus de 170 000 fiches, contre quelques dizaines jusqu'ici).
 *
 *  - id_source : l'identifiant de la ligne dans le système d'où vient le
 *    fichier. Il n'est PAS le numéro de suivi — les deux ont déjà été
 *    confondus une fois sur les anciens étudiants. Il sert de clef
 *    d'import : relancer un chargement ne crée pas de doublon, et une
 *    correction ultérieure retrouve chaque fiche à coup sûr.
 *
 *  - mention_bac, adresse : présentes dans le fichier, perdues sinon.
 *
 *  - index : à cette taille, chaque connexion d'un bachelier parcourait
 *    toute la table (recherche par nom, prénom, date, lieu). La date de
 *    naissance devient le point d'entrée ; l'écran des identités, trié
 *    par nom, cesse de trier 170 000 lignes à chaque page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bacheliers', function (Blueprint $table) {
            if (!Schema::hasColumn('bacheliers', 'id_source')) {
                $table->string('id_source', 30)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('bacheliers', 'mention_bac')) {
                $table->string('mention_bac', 10)->nullable()->after('serie_bac');
            }
            if (!Schema::hasColumn('bacheliers', 'adresse')) {
                $table->string('adresse')->nullable();
            }

            $table->index('date_naissance', 'bacheliers_date_naissance_index');
            $table->index('numero_table', 'bacheliers_numero_table_index');
            $table->index('annee_bac', 'bacheliers_annee_bac_index');
            $table->index('is_registered', 'bacheliers_is_registered_index');
            // Index COMPLET (pas de préfixe) : seul un index complet permet
            // à MariaDB de lire les fiches déjà dans l'ordre, sans trier
            // toute la table à chaque page de l'écran des identités.
            $table->index(['nom', 'prenom'], 'bacheliers_nom_prenom_index');
        });
    }

    public function down(): void
    {
        Schema::table('bacheliers', function (Blueprint $table) {
            foreach (['bacheliers_nom_prenom_index', 'bacheliers_date_naissance_index',
                      'bacheliers_numero_table_index', 'bacheliers_annee_bac_index',
                      'bacheliers_is_registered_index'] as $index) {
                $table->dropIndex($index);
            }
            $table->dropUnique(['id_source']);
            $table->dropColumn(['id_source', 'mention_bac', 'adresse']);
        });
    }
};
