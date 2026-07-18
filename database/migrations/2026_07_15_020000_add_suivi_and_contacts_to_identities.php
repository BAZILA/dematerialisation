<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bacheliers', function (Blueprint $table) {
            $table->string('numero_suivi')->nullable()->index()->after('id'); // N° de suivi (logiciel inscription)
            $table->string('numero_table')->nullable()->after('numero_releve'); // N° de table au BAC
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
        });

        Schema::table('anciens_etudiants', function (Blueprint $table) {
            $table->string('numero_suivi')->nullable()->index()->after('numero_carte');
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('adresse')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bacheliers', function (Blueprint $table) {
            $table->dropColumn(['numero_suivi', 'numero_table', 'telephone', 'email']);
        });
        Schema::table('anciens_etudiants', function (Blueprint $table) {
            $table->dropColumn(['numero_suivi', 'telephone', 'email', 'adresse']);
        });
    }
};
