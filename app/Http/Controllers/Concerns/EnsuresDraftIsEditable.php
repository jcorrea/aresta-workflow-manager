<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\Gate;

/**
 * Checagem repetida em todo endpoint de mutação do canvas (steps/activities/transitions):
 * só quem tem `workflow-editor` ou superior mexe no desenho (`WorkflowPolicy::update`), e só
 * numa versão `draft` — `published` é imutável para sempre (01-modelo-de-dados.md §2.2).
 */
trait EnsuresDraftIsEditable
{
    private function ensureDraftIsEditable(Workflow $workflow, WorkflowVersion $version): void
    {
        Gate::authorize('update', $workflow);

        abort_unless($version->workflow_id === $workflow->id, 404);
        abort_unless($version->status === WorkflowVersionStatus::Draft, 422, 'Só é possível editar uma versão em rascunho.');
    }
}
