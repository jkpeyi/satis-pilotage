<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD = ['open', 'analyzed', 'solution_proposed', 'fixed', 'dropped'];

    private const NEW = ['identified', 'qualified', 'planned', 'treated', 'verified', 'dropped'];

    private const MAPPING = ['open' => 'identified', 'analyzed' => 'qualified', 'solution_proposed' => 'planned', 'fixed' => 'treated'];

    /**
     * SPEC-001 L5 (debt register): README finding statuses (plus « abandonné »), effort as a range
     * with a confidence level, impact, owner, component, repository and GitLab ticket.
     * Existing statuses and efforts are converted without loss.
     */
    public function up(): void
    {
        $this->convertStatus(self::NEW, self::MAPPING, 'identified');

        Schema::table('issues', function (Blueprint $table) {
            $table->decimal('effort_min_days', 6, 1)->nullable()->after('effort_days');
            $table->decimal('effort_max_days', 6, 1)->nullable()->after('effort_min_days');
            $table->enum('confidence', ['low', 'medium', 'high'])->nullable()->after('effort_max_days');
            $table->text('impact')->nullable()->after('description');
            $table->foreignId('owner_id')->nullable()->after('confidence')->constrained('users');
            $table->foreignId('repository_id')->nullable()->after('owner_id')->constrained();
            $table->foreignId('component_id')->nullable()->after('repository_id')->constrained();
            $table->string('gitlab_issue_url', 1000)->nullable()->after('component_id');
        });

        DB::table('issues')->whereNotNull('effort_days')->update([
            'effort_min_days' => DB::raw('effort_days'),
            'effort_max_days' => DB::raw('effort_days'),
        ]);

        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('effort_days');
        });

        DB::statement('ALTER TABLE issues ADD CONSTRAINT issues_effort_range CHECK (effort_min_days IS NULL OR effort_max_days IS NULL OR effort_min_days <= effort_max_days)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE issues DROP CHECK issues_effort_range');

        Schema::table('issues', function (Blueprint $table) {
            $table->decimal('effort_days', 6, 1)->nullable()->after('proposed_solution');
        });
        DB::table('issues')->update(['effort_days' => DB::raw('COALESCE(effort_max_days, effort_min_days)')]);

        Schema::table('issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('component_id');
            $table->dropConstrainedForeignId('repository_id');
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn(['effort_min_days', 'effort_max_days', 'confidence', 'impact', 'gitlab_issue_url']);
        });

        $this->convertStatus(self::OLD, [...array_flip(self::MAPPING), 'verified' => 'fixed'], 'open');
    }

    /**
     * @param  list<string>  $target
     * @param  array<string, string>  $mapping
     */
    private function convertStatus(array $target, array $mapping, string $default): void
    {
        $enum = fn (array $values): string => 'ENUM('.implode(', ', array_map(fn (string $value): string => "'{$value}'", $values)).')';

        DB::statement('ALTER TABLE issues MODIFY status '.$enum(array_values(array_unique([...self::OLD, ...self::NEW])))." NOT NULL DEFAULT '{$default}'");
        foreach ($mapping as $from => $to) {
            DB::table('issues')->where('status', $from)->update(['status' => $to]);
        }
        DB::statement('ALTER TABLE issues MODIFY status '.$enum($target)." NOT NULL DEFAULT '{$default}'");
    }
};
