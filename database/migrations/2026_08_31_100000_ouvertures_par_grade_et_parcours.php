<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ouvertures de procédure ciblées par NIVEAU ou par PARCOURS.
 *
 * Jusqu'ici une fenêtre valait pour toute l'université : ouvrir les
 * dépôts les ouvrait pour tout le monde en même temps. Or les
 * calendriers diffèrent — les masters ne déposent pas quand les
 * licences déposent, et une école peut ouvrir seule.
 *
 * Les lignes existantes ont grade et parcours_id à NULL : elles restent
 * les règles GÉNÉRALES et gardent exactement le comportement actuel.
 * Les nouvelles lignes, plus précises, ne s'appliquent qu'à leur cible.
 *
 * Chaque étape est vérifiée avant d'être jouée : une migration de
 * schéma interrompue en cours de route doit pouvoir être relancée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('periodes', 'grade')) {
            Schema::table('periodes', function (Blueprint $table) {
                $table->string('grade', 30)->nullable()->after('operation');
            });
        }

        if (!Schema::hasColumn('periodes', 'parcours_id')) {
            Schema::table('periodes', function (Blueprint $table) {
                $table->foreignId('parcours_id')->nullable()->after('grade')
                    ->constrained('parcours')->nullOnDelete();
            });
        }

        // L'unicité portait sur (année, opération) : elle interdisait toute
        // règle ciblée. On installe la nouvelle AVANT de retirer l'ancienne :
        // MySQL refuse de supprimer un index dont dépend une clé étrangère
        // tant qu'aucun autre index ne peut la porter.
        if (!$this->indexExiste('periodes_cible_unique')) {
            Schema::table('periodes', function (Blueprint $table) {
                $table->unique(
                    ['annee_academique_id', 'operation', 'grade', 'parcours_id'],
                    'periodes_cible_unique'
                );
            });
        }

        if ($this->indexExiste('periodes_annee_academique_id_operation_unique')) {
            Schema::table('periodes', function (Blueprint $table) {
                $table->dropUnique('periodes_annee_academique_id_operation_unique');
            });
        }

        if (!$this->indexExiste('periodes_op_grade')) {
            Schema::table('periodes', function (Blueprint $table) {
                $table->index(['operation', 'grade'], 'periodes_op_grade');
            });
        }
    }

    public function down(): void
    {
        // Les règles ciblées n'ont pas d'équivalent dans l'ancien modèle :
        // on ne garde que les règles générales, sinon l'unicité d'origine
        // ne peut pas être rétablie.
        DB::table('periodes')->whereNotNull('grade')->orWhereNotNull('parcours_id')->delete();

        if ($this->indexExiste('periodes_op_grade')) {
            Schema::table('periodes', fn (Blueprint $t) => $t->dropIndex('periodes_op_grade'));
        }
        if (!$this->indexExiste('periodes_annee_academique_id_operation_unique')) {
            Schema::table('periodes', fn (Blueprint $t) => $t->unique(
                ['annee_academique_id', 'operation'],
                'periodes_annee_academique_id_operation_unique'
            ));
        }
        if ($this->indexExiste('periodes_cible_unique')) {
            Schema::table('periodes', fn (Blueprint $t) => $t->dropUnique('periodes_cible_unique'));
        }

        if (Schema::hasColumn('periodes', 'parcours_id')) {
            Schema::table('periodes', fn (Blueprint $t) => $t->dropConstrainedForeignId('parcours_id'));
        }
        if (Schema::hasColumn('periodes', 'grade')) {
            Schema::table('periodes', fn (Blueprint $t) => $t->dropColumn('grade'));
        }
    }

    /** SQLite et MySQL ne listent pas leurs index de la même façon. */
    private function indexExiste(string $nom): bool
    {
        $connexion = Schema::getConnection();

        if ($connexion->getDriverName() === 'sqlite') {
            return collect($connexion->select("PRAGMA index_list('periodes')"))
                ->contains(fn ($i) => $i->name === $nom);
        }

        return collect($connexion->select('SHOW INDEX FROM `periodes`'))
            ->contains(fn ($i) => $i->Key_name === $nom);
    }
};
