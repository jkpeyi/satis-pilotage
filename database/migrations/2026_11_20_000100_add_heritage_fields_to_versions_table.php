<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SPEC-001 L1: lifecycle (a future version such as Satis_SaaS is out of production indicators),
     * historical pivot, technical owner and verification status of the application data.
     */
    public function up(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->enum('status', ['in_production', 'in_testing', 'future', 'retired'])->default('in_production')->change();
            // Historical point of comparison (e.g. Audit/orabank), never a target.
            $table->boolean('is_historical_pivot')->default(false)->after('status');
            $table->foreignId('technical_owner_id')->nullable()->after('is_historical_pivot')->constrained('users');
            $table->enum('verification_status', ['to_verify', 'verified', 'unverifiable'])->default('to_verify')->after('technical_owner_id');
            $table->foreignId('verified_by')->nullable()->after('verification_status')->constrained('users');
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });
    }

    public function down(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('technical_owner_id');
            $table->dropColumn(['is_historical_pivot', 'verification_status', 'verified_at']);
        });
    }
};
