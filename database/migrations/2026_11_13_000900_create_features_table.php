<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            // A feature always belongs to a module or a sub-module (RG-01).
            $table->foreignId('module_id')->constrained('modules')->restrictOnDelete();
            $table->string('code', 64)->nullable()->unique();
            $table->string('name');
            $table->text('standard_description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['module_id', 'position']);
            $table->fullText(['name', 'standard_description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
