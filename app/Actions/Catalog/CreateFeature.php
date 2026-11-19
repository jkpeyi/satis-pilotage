<?php

namespace App\Actions\Catalog;

use App\Models\Feature;
use Illuminate\Support\Facades\DB;

final class CreateFeature
{
    public function __construct(
        private readonly Positions $positions,
        private readonly EnsureUniqueCatalogName $ensureUniqueName,
    ) {}

    /**
     * @param  array{name: string, module_id: int, code?: ?string, standard_description?: ?string}  $data
     */
    public function handle(array $data, ?int $position = null): Feature
    {
        $this->ensureUniqueName->handle($data['name']);

        return DB::transaction(function () use ($data, $position): Feature {
            $feature = Feature::query()->create([
                'name' => trim($data['name']),
                'module_id' => $data['module_id'],
                'code' => $data['code'] ?? null,
                'standard_description' => $data['standard_description'] ?? null,
                'position' => 0,
            ]);

            $this->positions->insert($feature, $position);

            return $feature->refresh();
        });
    }
}
