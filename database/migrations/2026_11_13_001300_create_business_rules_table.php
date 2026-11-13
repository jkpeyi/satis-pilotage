<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Displayed as RM-0001, computed from id (RG-13).
        Schema::create('business_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('implementation_id')->constrained();
            // Set when the rule was copied from another version (RG-11).
            $table->foreignId('origin_rule_id')->nullable()->constrained('business_rules')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('source', ['code', 'documentation', 'interview', 'observation']);
            $table->enum('status', ['pending', 'validated'])->default('pending');
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->fullText(['title', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_rules');
    }
};
