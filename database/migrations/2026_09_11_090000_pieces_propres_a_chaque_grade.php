<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chaque niveau d'études a ses propres pièces.
 *
 * Jusqu'ici une pièce sans rattachement était réclamée à TOUT LE MONDE.
 * En pratique cela donnait des dossiers absurdes : un étudiant de master
 * devait fournir son attestation de réussite au Bac, et un nouveau
 * bachelier un diplôme de Licence qu'il n'a évidemment pas. Le libellé
 * « Diplôme de Licence (si passage M1) » disait déjà le manque : faute
 * de pouvoir viser le niveau, la condition avait été écrite dans le
 * texte de la pièce.
 *
 * Désormais une pièce appartient à UN niveau, et à un seul. Les niveaux
 * partagent les mêmes procédures, jamais les mêmes pièces. À l'intérieur
 * d'un niveau, « commune » ne veut plus dire « tous les niveaux » mais
 * « tous les parcours de CE niveau » ; la portée par parcours reste
 * disponible pour viser plus étroit.
 *
 * Aucun fichier déjà déposé n'est touché : les pièces gardent leur
 * identifiant, seules leurs conditions d'application changent.
 */
return new class extends Migration
{
    private const NIVEAU_PAR_DEFAUT = 'licence';

    public function up(): void
    {
        if (!Schema::hasColumn('request_types', 'grade')) {
            Schema::table('request_types', function (Blueprint $table) {
                $table->string('grade', 20)->nullable()->after('category')->index();
            });
        }

        /* Reprise de l'existant.
           Une pièce déjà rattachée à un ou plusieurs niveaux garde le
           premier ; les autres — c'est-à-dire toutes, en pratique —
           basculent en licence, qui porte l'essentiel des effectifs. */
        if (Schema::hasTable('request_type_grade')) {
            $premiers = DB::table('request_type_grade')
                ->select('request_type_id', DB::raw('MIN(grade) as grade'))
                ->groupBy('request_type_id')->pluck('grade', 'request_type_id');

            foreach ($premiers as $id => $grade) {
                DB::table('request_types')->where('id', $id)->update(['grade' => $grade]);
            }
        }

        DB::table('request_types')->whereNull('grade')
            ->update(['grade' => self::NIVEAU_PAR_DEFAUT]);

        /* La table de liaison devient une seconde source de vérité pour
           la même information : on la retire plutôt que de risquer
           qu'elles divergent. */
        Schema::dropIfExists('request_type_grade');
    }

    public function down(): void
    {
        if (!Schema::hasTable('request_type_grade')) {
            Schema::create('request_type_grade', function (Blueprint $table) {
                $table->id();
                $table->foreignId('request_type_id')->constrained()->cascadeOnDelete();
                $table->string('grade', 20);
                $table->timestamps();
                $table->unique(['request_type_id', 'grade']);
            });
        }

        // On remet dans la table de liaison ce que la colonne portait.
        foreach (DB::table('request_types')->whereNotNull('grade')->get(['id', 'grade']) as $type) {
            DB::table('request_type_grade')->insert([
                'request_type_id' => $type->id,
                'grade'           => $type->grade,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        if (Schema::hasColumn('request_types', 'grade')) {
            Schema::table('request_types', function (Blueprint $table) {
                $table->dropColumn('grade');
            });
        }
    }
};
