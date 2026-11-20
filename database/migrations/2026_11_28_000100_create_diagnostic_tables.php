<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SPEC-001 L4: diagnostic campaigns on a repository at an exact commit, the tool results
     * imported from files (tools run outside the platform), their human triage, and test runs.
     */
    public function up(): void
    {
        Schema::create('diagnostic_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained();
            $table->string('commit_sha', 40);
            $table->string('title');
            $table->date('analyzed_on')->nullable();
            $table->text('objective')->nullable();
            // What the campaign did not cover: excluded directories, tools not run, no database access…
            $table->text('limitations')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['repository_id', 'commit_sha']);
        });

        Schema::create('tool_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_campaign_id')->constrained();
            $table->string('tool_name', 100);
            $table->string('tool_version', 64)->nullable();
            $table->enum('format', ['sarif', 'phpstan', 'eslint']);
            $table->date('scanned_on');
            $table->string('file_name');
            // A file is imported once per campaign.
            $table->char('file_sha256', 64);
            $table->unsignedInteger('results_count');
            $table->unsignedInteger('new_count');
            $table->unsignedInteger('duplicate_count');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['diagnostic_campaign_id', 'file_sha256']);
        });

        Schema::create('diagnostic_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_campaign_id')->constrained();
            // The run that first reported the result; later runs only increase occurrences.
            $table->foreignId('tool_run_id')->constrained();
            $table->char('fingerprint', 64);
            $table->string('rule_id')->nullable();
            $table->enum('level', ['error', 'warning', 'note']);
            $table->text('message');
            $table->string('path', 1000)->nullable();
            $table->unsignedInteger('line_start')->nullable();
            $table->unsignedInteger('line_end')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->enum('status', ['to_verify', 'retained', 'false_positive'])->default('to_verify');
            // No ON DELETE action: MySQL forbids referential actions on columns used in a CHECK.
            $table->foreignId('issue_id')->nullable()->constrained();
            $table->text('triage_note')->nullable();
            $table->foreignId('triaged_by')->nullable()->constrained('users');
            $table->timestamp('triaged_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['diagnostic_campaign_id', 'fingerprint']);
            $table->index(['diagnostic_campaign_id', 'status']);
        });

        // A retained result is linked to its issue, and only a retained result is.
        DB::statement("ALTER TABLE diagnostic_findings ADD CONSTRAINT diagnostic_findings_retained_issue CHECK ((status = 'retained') = (issue_id IS NOT NULL))");

        Schema::create('test_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diagnostic_campaign_id')->constrained();
            $table->string('tool_name', 100);
            $table->string('tool_version', 64)->nullable();
            $table->date('executed_on');
            $table->unsignedInteger('tests_total')->nullable();
            $table->unsignedInteger('tests_passed')->nullable();
            $table->unsignedInteger('tests_failed')->nullable();
            $table->unsignedInteger('tests_skipped')->nullable();
            $table->decimal('line_coverage', 5, 2)->nullable();
            // What the run covered or left out (suites, modules, environment).
            $table->text('scope')->nullable();
            $table->string('source_file_name')->nullable();
            $table->char('source_sha256', 64)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE test_runs ADD CONSTRAINT test_runs_coverage_range CHECK (line_coverage IS NULL OR (line_coverage >= 0 AND line_coverage <= 100))');
    }

    public function down(): void
    {
        Schema::dropIfExists('test_runs');
        Schema::dropIfExists('diagnostic_findings');
        Schema::dropIfExists('tool_runs');
        Schema::dropIfExists('diagnostic_campaigns');
    }
};
