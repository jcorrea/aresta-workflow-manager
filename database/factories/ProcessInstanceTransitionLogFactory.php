<?php

namespace Database\Factories;

use App\Models\ProcessInstance;
use App\Models\ProcessInstanceTransitionLog;
use App\Models\User;
use App\Models\WorkflowActivity;
use App\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessInstanceTransitionLog>
 */
class ProcessInstanceTransitionLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'process_instance_id' => ProcessInstance::factory(),
            'workflow_transition_id' => WorkflowTransition::factory(),
            'from_activity_id' => WorkflowActivity::factory(),
            'to_activity_id' => WorkflowActivity::factory(),
            'transitioned_by' => User::factory(),
            'transitioned_at' => now(),
        ];
    }
}
