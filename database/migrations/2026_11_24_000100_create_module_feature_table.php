<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A feature exists once in the catalog but may appear under several modules (RG-16):
     * features.module_id is its main module, this table lists the other modules it also belongs to.
     */
    public function up(): void
    {
        Schema::create('module_feature', function (Blueprint $table) {
            $table->foreignId('module_id')->constrained();
            $table->foreignId('feature_id')->constrained();
            // Order among the features shown under that module, after its own features.
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['module_id', 'feature_id']);
            $table->index('feature_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_feature');
    }
};
