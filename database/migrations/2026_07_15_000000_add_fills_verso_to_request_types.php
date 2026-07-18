<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            // Ce document est LA fiche dont le VERSO est rempli automatiquement
            // (tableau études antérieures + cachet + signature agent) à la validation.
            $table->boolean('fills_verso')->default(false)->after('has_stamp');
        });
    }

    public function down(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->dropColumn('fills_verso');
        });
    }
};
