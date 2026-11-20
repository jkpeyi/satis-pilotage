<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SPEC-001 L6: follow-up of the audit effort of a product, period by period (planned and
     * actual hours, time lost to support interruptions).
     */
    public function up(): void
    {
        Schema::create('audit_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('hours_planned', 7, 1);
            // Empty until the period is over and the time is known.
            $table->decimal('hours_actual', 7, 1)->nullable();
            $table->decimal('support_hours', 7, 1)->nullable();
            $table->unsignedInteger('support_interruptions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['product_id', 'starts_on']);
        });

        DB::statement('ALTER TABLE audit_periods ADD CONSTRAINT audit_periods_dates CHECK (ends_on >= starts_on)');
        DB::statement('ALTER TABLE audit_periods ADD CONSTRAINT audit_periods_hours CHECK (hours_planned >= 0 AND (hours_actual IS NULL OR hours_actual >= 0) AND (support_hours IS NULL OR support_hours >= 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_periods');
    }
};
