<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SPEC-001 L2: business workflows of a product, their ordered steps, and the business rules
     * that apply to each step (a rule may apply to several steps).
     */
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained();
            $table->unsignedInteger('position');
            $table->string('name');
            // Who performs the step: a role or a team, never a person's name.
            $table->string('actor')->nullable();
            // Catalog feature that realizes the step, the same in every version (RG-02).
            $table->foreignId('feature_id')->nullable()->constrained();
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['workflow_id', 'position']);
        });

        Schema::create('business_rule_workflow_step', function (Blueprint $table) {
            $table->foreignId('business_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
            $table->primary(['business_rule_id', 'workflow_step_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_rule_workflow_step');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
    }
};
