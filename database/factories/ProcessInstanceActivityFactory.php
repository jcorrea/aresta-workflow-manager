<?php

namespace Database\Factories;

use App\Enums\ProcessInstanceActivityStatus;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceActivity;
use App\Models\WorkflowActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessInstanceActivity>
 */
class ProcessInstanceActivityFactory extends Factory
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
            'workflow_activity_id' => WorkflowActivity::factory(),
            'assigned_user_id' => null,
            'status' => ProcessInstanceActivityStatus::Pending,
            'form_data' => null,
            'result' => null,
            'due_at' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
