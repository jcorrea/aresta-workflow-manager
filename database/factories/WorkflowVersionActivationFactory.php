<?php

namespace Database\Factories;

use App\Enums\ActivationAction;
use App\Models\User;
use App\Models\WorkflowVersion;
use App\Models\WorkflowVersionActivation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowVersionActivation>
 */
class WorkflowVersionActivationFactory extends Factory
{
    /**
     * `workflow_id` (denormalizado, 01-modelo-de-dados.md §2.2.2) é derivado de
     * `workflow_version_id` via closure — não cria a versão eagerly, senão um `->for()`/
     * override de `workflow_version_id` no chamador deixaria essa versão órfã no banco.
     * A ordem das chaves importa: `workflow_version_id` precisa vir primeiro para já estar
     * resolvida quando a closure de `workflow_id` roda (padrão documentado do Laravel).
     * `withoutGlobalScopes()` porque isto é bookkeeping interno do factory, não uma leitura
     * de aplicação — o usuário autenticado do teste não necessariamente pertence à
     * organização da versão sendo criada aqui.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_version_id' => WorkflowVersion::factory()->published(),
            'workflow_id' => fn (array $attributes) => WorkflowVersion::withoutGlobalScopes()
                ->findOrFail($attributes['workflow_version_id'])
                ->workflow_id,
            'action' => ActivationAction::Publish,
            'activated_by' => User::factory(),
            'activated_at' => now(),
        ];
    }
}
