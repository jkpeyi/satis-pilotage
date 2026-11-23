<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SPEC-001 L7: refactoring strategy of a product, written and approved by people (README
     * d'intégration: a decision document, never generated from metrics). Scenarios (options),
     * migration phases with their prerequisites, risks and decisions; key findings of the debt
     * register; approval status (draft, in review, approved, rejected).
     */
    public function up(): void
    {
        $userstamps = function (Blueprint $table): void {
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        };

        Schema::create('refactoring_strategies', function (Blueprint $table) use ($userstamps) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('title');
            $table->text('context')->nullable();
            $table->text('migration')->nullable();
            // Non-regression tests and rollback.
            $table->text('safety')->nullable();
            $table->text('saas_notes')->nullable();
            $table->enum('approval_status', ['draft', 'in_review', 'approved', 'rejected'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->timestamp('archived_at')->nullable();
            $userstamps($table);
        });

        Schema::create('issue_refactoring_strategy', function (Blueprint $table) {
            $table->foreignId('refactoring_strategy_id')->constrained();
            $table->foreignId('issue_id')->constrained();
            $table->primary(['refactoring_strategy_id', 'issue_id']);
        });

        Schema::create('strategy_options', function (Blueprint $table) use ($userstamps) {
            $table->id();
            $table->foreignId('refactoring_strategy_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('benefits')->nullable();
            $table->text('drawbacks')->nullable();
            $table->decimal('effort_min_days', 7, 1)->nullable();
            $table->decimal('effort_max_days', 7, 1)->nullable();
            $table->enum('risk_level', ['low', 'medium', 'high'])->nullable();
            $table->boolean('is_recommended')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $userstamps($table);
        });

        Schema::create('strategy_phases', function (Blueprint $table) use ($userstamps) {
            $table->id();
            $table->foreignId('refactoring_strategy_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('prerequisites')->nullable();
            $table->text('rollback')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $userstamps($table);
        });

        Schema::create('strategy_risks', function (Blueprint $table) use ($userstamps) {
            $table->id();
            $table->foreignId('refactoring_strategy_id')->constrained();
            $table->text('description');
            $table->enum('probability', ['low', 'medium', 'high'])->nullable();
            $table->enum('impact', ['low', 'medium', 'high'])->nullable();
            $table->text('mitigation')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->timestamp('archived_at')->nullable();
            $userstamps($table);
        });

        Schema::create('strategy_decisions', function (Blueprint $table) use ($userstamps) {
            $table->id();
            $table->foreignId('refactoring_strategy_id')->constrained();
            $table->foreignId('strategy_option_id')->nullable()->constrained();
            $table->string('title');
            $table->text('rationale')->nullable();
            $table->enum('status', ['proposed', 'accepted', 'rejected'])->default('proposed');
            $table->foreignId('decided_by')->nullable()->constrained('users');
            $table->date('decided_on')->nullable();
            $table->timestamp('archived_at')->nullable();
            $userstamps($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('strategy_decisions');
        Schema::dropIfExists('strategy_risks');
        Schema::dropIfExists('strategy_phases');
        Schema::dropIfExists('strategy_options');
        Schema::dropIfExists('issue_refactoring_strategy');
        Schema::dropIfExists('refactoring_strategies');
    }
};
