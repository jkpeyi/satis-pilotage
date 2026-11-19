<?php

namespace Database\Factories;

use App\Enums\ImplementationStatus;
use App\Models\Feature;
use App\Models\Implementation;
use App\Models\Module;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Implementation>
 */
class ImplementationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version_id' => Version::factory(),
            'module_id' => Module::factory(),
            'feature_id' => null,
            'status' => ImplementationStatus::Unknown,
        ];
    }

    public function forFeature(?Feature $feature = null): static
    {
        return $this->state(fn (): array => [
            'module_id' => null,
            'feature_id' => $feature->id ?? Feature::factory(),
        ]);
    }
}
