<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only history of status changes (RG-06).
        Schema::create('implementation_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('implementation_id')->constrained()->cascadeOnDelete();
            $statuses = ['not_assessed', 'operational', 'needs_improvement', 'defective', 'missing'];
            $table->enum('from_status', $statuses)->nullable();
            $table->enum('to_status', $statuses);
            $table->text('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['implementation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('implementation_status_changes');
    }
};
