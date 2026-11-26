<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * API and integrations, releases and deployments are no longer tracked: their tables and data
     * are dropped. Environments stay.
     */
    public function up(): void
    {
        Schema::dropIfExists('deployments');
        Schema::dropIfExists('releases');
        Schema::dropIfExists('integrations');
    }

    /**
     * Recreates the empty tables; the dropped data cannot come back.
     */
    public function down(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained();
            $table->string('name', 100);
            $table->string('commit_sha', 40)->nullable();
            $table->date('released_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['repository_id', 'name']);
        });

        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained();
            $table->foreignId('release_id')->nullable()->constrained();
            $table->string('commit_sha', 40)->nullable();
            $table->dateTime('deployed_at');
            $table->enum('verification_status', ['to_verify', 'verified', 'unverifiable'])->default('to_verify');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->string('evidence', 1000)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['environment_id', 'deployed_at']);
        });
        DB::statement('ALTER TABLE deployments ADD CONSTRAINT deployments_release_or_commit CHECK (release_id IS NOT NULL OR commit_sha IS NOT NULL)');

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_component_id')->constrained('components');
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
};
