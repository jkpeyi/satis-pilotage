<?php

namespace Database\Factories;

use App\Enums\VersionStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Version;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Version>
 */
class VersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'customer_id' => Customer::factory(),
            'name' => fake()->unique()->bothify('Version ##??'),
            'status' => VersionStatus::InProduction,
        ];
    }

    public function baseline(): static
    {
        return $this->state(fn (): array => ['customer_id' => null, 'name' => 'Socle']);
    }
}
