<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('request_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('request_type_id')->constrained()->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('file_path');
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->boolean('is_stamped')->default(false);
            $table->string('stamped_file_path')->nullable();
            // Revue admin
            $table->boolean('is_valid')->nullable();   // null=non examiné, true=ok, false=invalide
            $table->boolean('is_locked')->default(false); // true = validé, l'étudiant ne peut plus modifier
            $table->text('admin_comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_files');
    }
};
