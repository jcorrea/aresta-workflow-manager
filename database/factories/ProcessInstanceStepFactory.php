<?php

namespace Database\Factories;

use App\Enums\ProcessInstanceStepStatus;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceStep;
use App\Models\WorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessInstanceStep>
 */
class ProcessInstanceStepFactory extends Factory
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
            'workflow_step_id' => WorkflowStep::factory(),
            'status' => ProcessInstanceStepStatus::Pending,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
