<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            // A module with a parent is displayed as a sub-module. Depth is unlimited (RG-01).
            $table->foreignId('parent_id')->nullable()->constrained('modules')->restrictOnDelete();
            $table->string('code', 64)->nullable()->unique();
            $table->string('name');
            $table->text('standard_description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['parent_id', 'position']);
            $table->fullText(['name', 'standard_description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
