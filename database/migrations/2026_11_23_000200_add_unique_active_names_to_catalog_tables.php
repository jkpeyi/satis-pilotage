<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two active modules (or two active features) can no longer share a name. Archived items keep
     * their name: a converted feature leaves an archived copy behind (RG-14).
     */
    public function up(): void
    {
        foreach (['modules', 'features'] as $tableName) {
            $duplicates = DB::table($tableName)->whereNull('archived_at')
                ->select('name_key')->groupBy('name_key')->havingRaw('count(*) > 1')->count();

            if ($duplicates > 0) {
                throw new RuntimeException(
                    "Le catalogue contient encore {$duplicates} nom(s) en double dans la table {$tableName}. "
                    .'Lancez « php artisan catalog:merge-duplicates » pour les voir, puis avec --force pour les fusionner, et relancez la migration.'
                );
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('active_name_key')->nullable()->storedAs('IF(archived_at IS NULL, name_key, NULL)');
                $table->unique('active_name_key');
            });
        }
    }

    public function down(): void
    {
        foreach (['modules', 'features'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['active_name_key']);
                $table->dropColumn('active_name_key');
            });
        }
    }
};
