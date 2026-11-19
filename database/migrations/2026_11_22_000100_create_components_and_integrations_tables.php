<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SPEC-001 L3: technical components of an application and the integrations between them or
     * with external systems.
     */
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained();
            $table->foreignId('repository_id')->nullable()->constrained();
            $table->string('name');
            $table->enum('type', ['api', 'web', 'mobile', 'batch', 'worker', 'database', 'library', 'other']);
            $table->string('technology')->nullable();
            $table->string('technology_version', 64)->nullable();
            $table->text('description')->nullable();
            // Where the information can be checked (GitLab file, documentation page).
            $table->string('evidence_url', 1000)->nullable();
            $table->enum('verification_status', ['to_verify', 'verified', 'unverifiable'])->default('to_verify');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['version_id', 'name']);
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_component_id')->constrained('components');
            // Exactly one target: a component of the estate or an external system (CHECK below).
            // No ON DELETE action: MySQL forbids referential actions on columns used in a CHECK.
            $table->foreignId('target_component_id')->nullable()->constrained('components');
            $table->string('external_system')->nullable();
            $table->enum('protocol', ['rest', 'soap', 'file', 'database', 'messaging', 'other']);
            $table->text('description')->nullable();
            $table->string('evidence_url', 1000)->nullable();
            $table->enum('verification_status', ['to_verify', 'verified', 'unverifiable'])->default('to_verify');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE integrations ADD CONSTRAINT integrations_one_target CHECK ((target_component_id IS NULL) <> (external_system IS NULL))');
        DB::statement('ALTER TABLE integrations ADD CONSTRAINT integrations_not_self CHECK (target_component_id IS NULL OR target_component_id <> source_component_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('components');
    }
};
