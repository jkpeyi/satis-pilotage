<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Code snippets are archived like every business record (RG-12).
    public function up(): void
    {
        Schema::table('code_snippets', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('line_end');
        });
    }

    public function down(): void
    {
        Schema::table('code_snippets', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
