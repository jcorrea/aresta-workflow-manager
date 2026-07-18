<?php

namespace Database\Factories;

use App\Enums\WorkflowVersionStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowVersion>
 */
class WorkflowVersionFactory extends Factory
{
    /**
     * Default: `draft` com `draft_lock_workflow_id` preenchido (01-modelo-de-dados.md §2.2) —
     * estado mais comum ao construir um grafo em teste.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workflow = Workflow::factory();

        return [
            'workflow_id' => $workflow,
            'version_number' => null,
            'status' => WorkflowVersionStatus::Draft,
            'canvas_json' => null,
            'published_at' => null,
            'created_by' => User::factory(),
            'draft_lock_workflow_id' => fn (array $attributes) => $attributes['workflow_id'],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'version_number' => 1,
            'status' => WorkflowVersionStatus::Published,
            'published_at' => now(),
            'draft_lock_workflow_id' => null,
        ]);
    }
}
