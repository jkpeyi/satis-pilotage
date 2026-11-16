<?php

namespace App\Actions\Catalog;

use App\Models\Module;
use Illuminate\Support\Facades\DB;

/**
 * Creates a module, or a sub-module when a parent is given (RG-01).
 */
final class CreateModule
{
    public function __construct(private readonly Positions $positions) {}

    /**
     * @param  array{name: string, parent_id?: ?int, code?: ?string, standard_description?: ?string}  $data
     */
    public function handle(array $data, ?int $position = null): Module
    {
        return DB::transaction(function () use ($data, $position): Module {
            $module = Module::query()->create([
                'name' => trim($data['name']),
                'parent_id' => $data['parent_id'] ?? null,
                'code' => $data['code'] ?? null,
                'standard_description' => $data['standard_description'] ?? null,
                'position' => 0,
            ]);

            $this->positions->insert($module, $position);

            return $module->refresh();
        });
    }
}
