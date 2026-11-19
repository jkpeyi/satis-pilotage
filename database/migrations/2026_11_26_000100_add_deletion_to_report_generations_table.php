<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An export can be deleted from the registry: its files are removed, the row stays as a trace
     * (who deleted it and when), so document ids and version numbers are never reused.
     */
    public function up(): void
    {
        Schema::table('report_generations', function (Blueprint $table) {
            $table->timestamp('deleted_at')->nullable()->after('created_at');
            $table->foreignId('deleted_by')->nullable()->after('deleted_at')->constrained('users');
        });
    }

    public function down(): void
    {
        Schema::table('report_generations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn('deleted_at');
        });
    }
};
