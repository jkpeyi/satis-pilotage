<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Displayed as PB-0001, computed from id (RG-13).
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->enum('type', ['bug', 'tech_debt', 'security', 'performance', 'obsolescence', 'usability']);
            $table->enum('severity', ['blocking', 'major', 'minor']);
            $table->enum('status', ['open', 'analyzed', 'solution_proposed', 'fixed', 'dropped'])->default('open');
            $table->text('proposed_solution')->nullable();
            $table->decimal('effort_days', 6, 1)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->fullText(['title', 'description']);
        });

        // An issue concerns one or more implementations, possibly in several versions (RG-09).
        Schema::create('implementation_issue', function (Blueprint $table) {
            $table->foreignId('implementation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->primary(['implementation_id', 'issue_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_issue');
        Schema::dropIfExists('issues');
    }
};
