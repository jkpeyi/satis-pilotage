<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD = ['not_assessed', 'operational', 'needs_improvement', 'defective', 'missing'];

    private const NEW = ['unknown', 'operational', 'partial', 'defective', 'absent', 'not_tested', 'present_not_applicable', 'not_applicable'];

    /** Old value => new value. "Non évalué" becomes an unknown presence, "Absent" moves to the presence axis. */
    private const MAPPING = ['not_assessed' => 'unknown', 'needs_improvement' => 'partial', 'missing' => 'absent'];

    /**
     * README d'intégration SPEC-001: presence and operation on two axes. Existing values and the
     * whole history are converted, nothing is lost.
     */
    public function up(): void
    {
        $this->convert(self::NEW, self::MAPPING, 'unknown');
    }

    public function down(): void
    {
        // Combinations without an old equivalent fall back to the nearest old value.
        $this->convert(self::OLD, [
            ...array_flip(self::MAPPING),
            'not_tested' => 'not_assessed',
            'present_not_applicable' => 'operational',
            'not_applicable' => 'missing',
        ], 'not_assessed');
    }

    /**
     * @param  list<string>  $target
     * @param  array<string, string>  $mapping
     */
    private function convert(array $target, array $mapping, string $default): void
    {
        $all = array_values(array_unique([...self::OLD, ...self::NEW]));
        $widen = $this->enum($all);

        DB::statement("ALTER TABLE implementations MODIFY status {$widen} NOT NULL DEFAULT '{$default}'");
        DB::statement("ALTER TABLE implementation_status_changes MODIFY from_status {$widen} NULL, MODIFY to_status {$widen} NOT NULL");

        foreach ($mapping as $from => $to) {
            DB::table('implementations')->where('status', $from)->update(['status' => $to]);
            DB::table('implementation_status_changes')->where('from_status', $from)->update(['from_status' => $to]);
            DB::table('implementation_status_changes')->where('to_status', $from)->update(['to_status' => $to]);
        }

        $narrow = $this->enum($target);
        DB::statement("ALTER TABLE implementations MODIFY status {$narrow} NOT NULL DEFAULT '{$default}'");
        DB::statement("ALTER TABLE implementation_status_changes MODIFY from_status {$narrow} NULL, MODIFY to_status {$narrow} NOT NULL");
    }

    /**
     * @param  list<string>  $values
     */
    private function enum(array $values): string
    {
        return 'ENUM('.implode(', ', array_map(fn (string $value): string => "'{$value}'", $values)).')';
    }
};
