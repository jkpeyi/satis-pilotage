<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained();
            // GitLab path_with_namespace, entered manually in phase 1.
            $table->string('path');
            $table->unsignedBigInteger('gitlab_project_id')->nullable();
            $table->string('web_url')->nullable();
            $table->enum('role', ['backend', 'frontend', 'other']);
            $table->string('production_branch');
            $table->json('active_branches')->nullable();
            // Entered manually in phase 1, detected from lock files in phase 2.
            $table->json('stack')->nullable();
            $table->timestamp('stack_detected_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['version_id', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repositories');
    }
};
