<?php

namespace Database\Factories;

use App\Enums\AssigneeType;
use App\Enums\WorkflowActivityType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowActivity>
 */
class WorkflowActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_step_id' => WorkflowStep::factory(),
            'name' => fake()->words(3, true),
            'type' => WorkflowActivityType::Task,
            'assignee_type' => null,
            'assignee_role_id' => null,
            'assignee_user_id' => null,
            'config' => ['instructions' => fake()->sentence()],
            'sla_hours' => null,
            'position_x' => fake()->numberBetween(0, 800),
            'position_y' => fake()->numberBetween(0, 600),
            'is_start' => false,
            'is_end' => false,
        ];
    }

    public function assignedToRole(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignee_type' => AssigneeType::Role,
            'assignee_role_id' => Role::factory(),
        ]);
    }

    public function assignedToUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'assignee_type' => AssigneeType::User,
            'assignee_user_id' => User::factory(),
        ]);
    }

    public function start(): static
    {
        return $this->state(fn (array $attributes) => ['is_start' => true]);
    }

    public function end(): static
    {
        return $this->state(fn (array $attributes) => ['is_end' => true]);
    }

    public function condition(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WorkflowActivityType::Condition,
            'config' => ['description' => fake()->sentence()],
        ]);
    }
}
