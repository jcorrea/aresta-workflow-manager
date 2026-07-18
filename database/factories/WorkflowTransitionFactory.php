<?php

namespace Database\Factories;

use App\Enums\ConditionType;
use App\Models\WorkflowActivity;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTransition>
 */
class WorkflowTransitionFactory extends Factory
{
    /**
     * `from`/`to`/`workflow_version_id` default para factories independentes (sem garantir
     * que pertencem à mesma versão) — a invariante "aresta não cruza versões"
     * (01-modelo-de-dados.md §2.5) é de aplicação, não de banco; testes que exercitam o grafo
     * de verdade devem sobrescrever os três explicitamente com o mesmo `WorkflowVersion`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_version_id' => WorkflowVersion::factory(),
            'from_activity_id' => WorkflowActivity::factory(),
            'to_activity_id' => WorkflowActivity::factory(),
            'condition_type' => ConditionType::Always,
            'condition_expression' => null,
            'label' => null,
            'sort_order' => 0,
        ];
    }
}
