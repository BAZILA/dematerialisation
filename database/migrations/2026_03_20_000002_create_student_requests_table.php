<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('category'); // code de procédure (voir table procedures)
            $table->text('rendez_vous_note')->nullable()->comment('Instructions / lieu de retrait');
            $table->timestamp('rendez_vous_at')->nullable()->comment('Date de retrait physique (demandes non-dépôt)');
            $table->enum('status', ['brouillon', 'soumis', 'en_correction', 'validé', 'rejeté'])->default('brouillon');
            $table->integer('version')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_message')->nullable();
            $table->text('admin_comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_requests');
    }
};
