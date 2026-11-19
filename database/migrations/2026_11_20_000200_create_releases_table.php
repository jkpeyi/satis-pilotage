<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Software releases of a repository (tag and commit), deployed to environments.
     */
    public function up(): void
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
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
