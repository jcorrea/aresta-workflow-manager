<?php

namespace Database\Factories;

use App\Models\ExternalSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalSystem>
 */
class ExternalSystemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'active' => true,
        ];
    }
}
