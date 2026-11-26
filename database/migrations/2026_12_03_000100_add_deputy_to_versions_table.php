<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deputy of the technical owner of a version (« suppléant »), reported in L1.
     */
    public function up(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->foreignId('deputy_id')->nullable()->after('technical_owner_id')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deputy_id');
        });
    }
};
