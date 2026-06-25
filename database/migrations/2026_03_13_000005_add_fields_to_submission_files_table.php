<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('submission_files', function (Blueprint $table) {
            $table->foreignId('document_type_id')
                  ->nullable()
                  ->after('submission_id')
                  ->constrained('document_types')
                  ->nullOnDelete();

            // Revue par l'admin : null=non examiné, true=valide, false=invalide
            $table->boolean('is_file_valid')->nullable()->after('is_stamped');
            $table->text('admin_comment')->nullable()->after('is_file_valid');
            $table->string('stamped_file_path')->nullable()->after('admin_comment');
        });
    }

    public function down(): void
    {
        Schema::table('submission_files', function (Blueprint $table) {
            $table->dropForeign(['document_type_id']);
            $table->dropColumn(['document_type_id', 'is_file_valid', 'admin_comment', 'stamped_file_path']);
        });
    }
};
