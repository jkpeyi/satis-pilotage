<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dated deployments of an environment: a release or a bare commit, with their verification.
     */
    public function up(): void
    {
        Schema::create('deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained();
            // No ON DELETE action: both columns are used in a CHECK constraint.
            $table->foreignId('release_id')->nullable()->constrained();
            $table->string('commit_sha', 40)->nullable();
            $table->dateTime('deployed_at');
            $table->enum('verification_status', ['to_verify', 'verified', 'unverifiable'])->default('to_verify');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            // URL or description of the proof (pipeline, ticket, server check).
            $table->string('evidence', 1000)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['environment_id', 'deployed_at']);
        });

        DB::statement('ALTER TABLE deployments ADD CONSTRAINT deployments_release_or_commit CHECK (release_id IS NOT NULL OR commit_sha IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('deployments');
    }
};
