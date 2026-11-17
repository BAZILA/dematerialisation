<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facultes', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('sigle')->nullable();
            $table->enum('type', ['faculte', 'institut', 'ecole'])->default('faculte');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facultes');
    }
};
