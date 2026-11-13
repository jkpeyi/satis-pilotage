<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('code_snippets', function (Blueprint $table) {
            $table->id();
            // Exactly one owner: a business rule or an issue.
            $table->foreignId('business_rule_id')->nullable()->constrained('business_rules');
            $table->foreignId('issue_id')->nullable()->constrained('issues');
            $table->enum('kind', ['finding', 'solution'])->default('finding');
            $table->string('language', 32);
            $table->mediumText('content');
            // Optional anchor. commit_sha is always set when the snippet comes from GitLab (RG-10).
            $table->foreignId('repository_id')->nullable()->constrained()->nullOnDelete();
            $table->char('commit_sha', 40)->nullable();
            $table->string('path')->nullable();
            $table->unsignedInteger('line_start')->nullable();
            $table->unsignedInteger('line_end')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['repository_id', 'path']);
        });

        DB::statement(
            'ALTER TABLE code_snippets ADD CONSTRAINT code_snippets_single_owner_check '
            .'CHECK ((business_rule_id IS NULL) <> (issue_id IS NULL))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('code_snippets');
    }
};
