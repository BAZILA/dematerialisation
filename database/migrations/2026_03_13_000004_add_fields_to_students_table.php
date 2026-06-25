<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('sexe', ['M', 'F'])->nullable()->after('specialite');
            $table->string('lieu_naissance')->nullable()->after('date_naissance');
            $table->unsignedBigInteger('bachelier_id')->nullable()->after('id');
            $table->unsignedBigInteger('ancien_etudiant_id')->nullable()->after('bachelier_id');

            $table->foreign('bachelier_id')
                  ->references('id')->on('bacheliers')
                  ->nullOnDelete();

            $table->foreign('ancien_etudiant_id')
                  ->references('id')->on('anciens_etudiants')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropForeign(['bachelier_id']);
            $table->dropForeign(['ancien_etudiant_id']);
            $table->dropColumn(['sexe', 'lieu_naissance', 'bachelier_id', 'ancien_etudiant_id']);
        });
    }
};
