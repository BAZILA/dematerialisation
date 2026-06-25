<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();           // ex: 'acte_naissance'
            $table->string('label');                    // ex: 'Acte de Naissance'
            $table->enum('type_etudiant', ['nouveau', 'ancien', 'both'])->default('both');
            $table->boolean('is_required')->default(true);
            $table->boolean('has_stamp')->default(false); // fichiers tamponnés à la validation
            $table->integer('order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
