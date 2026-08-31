<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portée d'une pièce par GRADE, en plus de la portée par parcours.
 *
 * Jusqu'ici une pièce était soit commune à tous, soit propre à des
 * parcours nommés un par un. Réclamer un même document à tous les
 * masters obligeait à lister chaque master, et à revenir sur la liste
 * à chaque nouveau parcours ouvert.
 *
 * Les deux portées se cumulent : « demandé à tous les masters » ET
 * « demandé en plus au master d'informatique » coexistent. Une pièce
 * sans grade ni parcours reste commune à tout le monde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_type_grade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_type_id')->constrained()->cascadeOnDelete();
            $table->string('grade', 30);
            $table->timestamps();

            $table->unique(['request_type_id', 'grade'], 'rtg_unique');
            $table->index('grade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_type_grade');
    }
};
