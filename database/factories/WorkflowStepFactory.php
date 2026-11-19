<?php

namespace Database\Factories;

use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'position' => fake()->unique()->numberBetween(1, 1000),
            'name' => fake()->words(2, true),
        ];
    }
}
