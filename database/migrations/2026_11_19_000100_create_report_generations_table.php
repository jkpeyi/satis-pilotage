<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Register of generated deliverables (SPEC-001 §5): each export is an immutable snapshot.
     */
    public function up(): void
    {
        Schema::create('report_generations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8);
            $table->string('title');
            $table->json('scope');
            $table->char('scope_hash', 64);
            $table->string('scope_label');
            $table->string('template_file');
            $table->string('template_version', 16);
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('version_number');
            $table->decimal('completeness', 5, 2);
            $table->unsignedInteger('missing_count');
            $table->longText('snapshot');
            // Hash of the snapshot and template version: equal hashes mean the same content.
            $table->char('data_sha256', 64);
            $table->string('docx_path')->nullable();
            $table->char('docx_sha256', 64)->nullable();
            $table->string('xlsx_path')->nullable();
            $table->char('xlsx_sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['code', 'scope_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_generations');
    }
};
