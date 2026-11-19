<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conditions in workflows: a step is an action, a decision (« Réclamation fondée ? ») or an end,
     * and its exits lead to other steps (« Oui → 5 », « Non → 8 »), possibly back (a loop).
     * An action without exit goes on to the next step in the order.
     */
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->enum('kind', ['action', 'decision', 'end'])->default('action')->after('position');
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_step_id')->constrained('workflow_steps');
            // Condition of a decision exit (« Oui », « Montant > 1 M »); empty for a plain "then".
            $table->string('label', 150)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['workflow_step_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');

        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
