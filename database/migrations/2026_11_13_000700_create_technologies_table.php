<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technologies', function (Blueprint $table) {
            $table->id();
            // e.g. ('php', '8.1'), ('laravel', '9'), ('react', '17').
            $table->string('component', 64);
            $table->string('cycle', 32);
            $table->date('end_of_support')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['component', 'cycle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technologies');
    }
};
