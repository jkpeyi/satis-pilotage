<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SPEC-001 L8: remediation roadmap. Work packages (lots, with their milestone) group actions;
     * an action treats a problem of the debt register, has an owner, dates, effort, acceptance
     * criteria and, once done, its test evidence. Actions may depend on other actions.
     */
    public function up(): void
    {
        Schema::create('work_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('name');
            $table->text('objective')->nullable();
            $table->date('starts_on')->nullable();
            // Milestone: the date the package must be delivered.
            $table->date('due_on')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'done', 'cancelled'])->default('planned');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        Schema::create('remediation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('work_package_id')->nullable()->constrained();
            $table->foreignId('issue_id')->nullable()->constrained();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['p1', 'p2', 'p3', 'p4'])->default('p2');
            $table->enum('status', ['todo', 'in_progress', 'blocked', 'done', 'cancelled'])->default('todo');
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->date('starts_on')->nullable();
            $table->date('due_on')->nullable();
            $table->date('completed_on')->nullable();
            $table->decimal('effort_days', 6, 1)->nullable();
            $table->text('acceptance_criteria')->nullable();
            // Link to a pipeline, a test report or a recette record, or a short text.
            $table->text('test_evidence')->nullable();
            $table->string('gitlab_issue_url', 1000)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['product_id', 'status']);
        });

        DB::statement('ALTER TABLE remediation_actions ADD CONSTRAINT remediation_actions_dates CHECK (starts_on IS NULL OR due_on IS NULL OR due_on >= starts_on)');
        DB::statement('ALTER TABLE remediation_actions ADD CONSTRAINT remediation_actions_effort CHECK (effort_days IS NULL OR effort_days >= 0)');
        // A done action carries its completion date (RG-19).
        DB::statement("ALTER TABLE remediation_actions ADD CONSTRAINT remediation_actions_done CHECK ((status = 'done') = (completed_on IS NOT NULL))");

        Schema::create('remediation_action_dependencies', function (Blueprint $table) {
            $table->foreignId('action_id')->constrained('remediation_actions');
            $table->foreignId('depends_on_id')->constrained('remediation_actions');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->primary(['action_id', 'depends_on_id']);
        });

        DB::statement('ALTER TABLE remediation_action_dependencies ADD CONSTRAINT remediation_action_dependencies_not_self CHECK (action_id <> depends_on_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('remediation_action_dependencies');
        Schema::dropIfExists('remediation_actions');
        Schema::dropIfExists('work_packages');
    }
};
