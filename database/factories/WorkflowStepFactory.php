<?php

namespace Database\Factories;

use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStep>
 */
class WorkflowStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_version_id' => WorkflowVersion::factory(),
            'name' => fake()->words(2, true),
            'sla_days' => null,
            'position_x' => fake()->numberBetween(0, 2000),
            'position_y' => fake()->numberBetween(0, 2000),
            'width' => 320,
            'height' => 240,
            'sort_order' => 0,
        ];
    }
}
