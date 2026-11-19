<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SPEC-001 L2: verification of an implementation's assessment (verified_by), distinct from the assessment itself.
     */
    public function up(): void
    {
        Schema::table('implementations', function (Blueprint $table) {
            $table->enum('verification_status', ['to_verify', 'verified', 'unverifiable'])->default('to_verify')->after('assessed_at');
            $table->foreignId('verified_by')->nullable()->after('verification_status')->constrained('users');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('implementations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verification_status', 'verified_at']);
        });
    }
};
