<?php

namespace Database\Factories;

use App\Enums\ProcessInstanceStatus;
use App\Models\ProcessInstance;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessInstance>
 */
class ProcessInstanceFactory extends Factory
{
    /**
     * `organization_id` (denormalizado, 01-modelo-de-dados.md §3.1) é derivado de
     * `workflow_version_id` via closure — mesmo cuidado de `WorkflowVersionActivationFactory`
     * para não deixar uma versão/organização órfã quando o chamador sobrescreve
     * `workflow_version_id`. A ordem das chaves importa (`workflow_version_id` primeiro).
     * `Workflow::withoutGlobalScopes()->find(...)` direto (não `->with('workflow')` a partir
     * de `WorkflowVersion`) porque `withoutGlobalScopes()` não se propaga para relations
     * eager-loaded — cada model aplica seu próprio scope na própria query.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_version_id' => WorkflowVersion::factory()->published(),
            'organization_id' => function (array $attributes) {
                $workflowId = WorkflowVersion::withoutGlobalScopes()
                    ->findOrFail($attributes['workflow_version_id'])
                    ->workflow_id;

                return Workflow::withoutGlobalScopes()->findOrFail($workflowId)->organization_id;
            },
            'code' => fake()->unique()->numerify('####-######'),
            'name' => fake()->sentence(4),
            'status' => ProcessInstanceStatus::Running,
            'started_by' => User::factory(),
            'started_at' => now(),
            'completed_at' => null,
            'context' => [],
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProcessInstanceStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
