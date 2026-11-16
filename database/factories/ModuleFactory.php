<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'standard_description' => fake()->sentence(),
            'position' => 0,
        ];
    }

    public function childOf(Module $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }
}
