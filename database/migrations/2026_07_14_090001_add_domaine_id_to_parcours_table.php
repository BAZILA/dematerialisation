<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('parcours', function (Blueprint $table) {
            $table->foreignId('domaine_id')->nullable()->after('departement_id')
                ->constrained('domaines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parcours', function (Blueprint $table) {
            $table->dropForeign(['domaine_id']);
            $table->dropColumn('domaine_id');
        });
    }
};
