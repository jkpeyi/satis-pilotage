<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('environments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained();
            $table->foreignId('repository_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['production', 'staging', 'testing', 'other']);
            $table->string('name');
            $table->string('url')->nullable();
            // Branch, tag or commit currently deployed.
            $table->string('deployed_ref')->nullable();
            $table->string('database_engine')->nullable();
            $table->string('database_version')->nullable();
            $table->string('php_version')->nullable();
            $table->string('server')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('environments');
    }
};
