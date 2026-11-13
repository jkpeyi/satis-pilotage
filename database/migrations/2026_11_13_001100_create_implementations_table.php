<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Rattachement": how a version implements a module or a feature.
        Schema::create('implementations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained();
            // Exactly one of module_id / feature_id is set (RG-03).
            // No ON DELETE action: MySQL forbids referential actions on columns used in a CHECK.
            $table->foreignId('module_id')->nullable()->constrained('modules');
            $table->foreignId('feature_id')->nullable()->constrained('features');
            $table->enum('status', ['not_assessed', 'operational', 'needs_improvement', 'defective', 'missing'])
                ->default('not_assessed');
            // Markdown, relative to the catalog standard description (RG-07).
            $table->text('deviation')->nullable();
            $table->text('assessment_note')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users');
            $table->dateTime('assessed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            // NULLs are distinct in unique indexes, so each rule applies only to its own target type.
            $table->unique(['version_id', 'module_id']);
            $table->unique(['version_id', 'feature_id']);
            $table->index(['version_id', 'status']);
        });

        DB::statement(
            'ALTER TABLE implementations ADD CONSTRAINT implementations_single_target_check '
            .'CHECK ((module_id IS NULL) <> (feature_id IS NULL))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('implementations');
    }
};
