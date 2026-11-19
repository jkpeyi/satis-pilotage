<?php

use App\Support\CatalogName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog names become unique (case, accents and punctuation ignored). This step adds the
     * comparison key and the merge log; the unique index comes once duplicates are merged
     * (php artisan catalog:merge-duplicates).
     */
    public function up(): void
    {
        foreach (['modules', 'features'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('name_key')->default('')->after('name');
            });

            DB::table($tableName)->orderBy('id')->select(['id', 'name'])->chunkById(1000, function ($rows) use ($tableName): void {
                foreach ($rows as $row) {
                    DB::table($tableName)->where('id', $row->id)->update(['name_key' => CatalogName::key($row->name)]);
                }
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->index('name_key');
            });
        }

        // Trace of merged duplicates: the merged item is deleted once everything it held has moved.
        Schema::create('catalog_merges', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['module', 'feature']);
            $table->unsignedBigInteger('kept_id');
            $table->unsignedBigInteger('merged_id');
            $table->string('merged_name');
            $table->string('merged_code', 64)->nullable();
            $table->json('details');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['kind', 'kept_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_merges');

        foreach (['modules', 'features'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['name_key']);
                $table->dropColumn('name_key');
            });
        }
    }
};
