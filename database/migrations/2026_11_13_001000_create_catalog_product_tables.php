<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Products a catalog item applies to (tags used for filtering).
        Schema::create('module_product', function (Blueprint $table) {
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['module_id', 'product_id']);
        });

        Schema::create('feature_product', function (Blueprint $table) {
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['feature_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_product');
        Schema::dropIfExists('module_product');
    }
};
