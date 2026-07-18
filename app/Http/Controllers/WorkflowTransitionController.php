<?php

namespace App\Http\Controllers;

use App\Enums\ConditionType;
use App\Http\Controllers\Concerns\EnsuresDraftIsEditable;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkflowTransitionController extends Controller
{
    use EnsuresDraftIsEditable;

    public function store(Request $request, Workflow $workflow, WorkflowVersion $version): JsonResponse
    {
        $this->ensureDraftIsEditable($workflow, $version);

        $data = $request->validate([
            'from_activity_id' => ['required', 'integer'],
            'to_activity_id' => ['required', 'integer'],
            'condition_type' => ['required', Rule::enum(ConditionType::class)],
            'condition_expression' => ['nullable', 'array'],
            'label' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['integer'],
        ]);

        // `from`/`to` precisam pertencer a esta versão (01-modelo-de-dados.md §2.5, constraint
        // de aplicação, não de banco) — reforçado consultando pelo escopo já ligado à versão
        // via `whereHas('workflowStep')`.
        $activityIds = WorkflowActivity::query()
            ->whereHas('workflowStep', fn ($query) => $query->where('workflow_version_id', $version->id))
            ->whereIn('id', [$data['from_activity_id'], $data['to_activity_id']])
            ->pluck('id');

        abort_unless($activityIds->contains($data['from_activity_id']) && $activityIds->contains($data['to_activity_id']), 422, 'As atividades da transição precisam pertencer a esta versão.');

        $transition = WorkflowTransition::create([...$data, 'workflow_version_id' => $version->id]);

        return response()->json(['id' => $transition->id]);
    }

    public function update(Request $request, WorkflowTransition $transition): JsonResponse
    {
        $version = $transition->workflowVersion;
        $this->ensureDraftIsEditable($version->workflow, $version);

        $data = $request->validate([
            'condition_type' => ['sometimes', Rule::enum(ConditionType::class)],
            'condition_expression' => ['sometimes', 'nullable', 'array'],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $transition->update($data);

        return response()->json(['id' => $transition->id]);
    }

    public function destroy(WorkflowTransition $transition): JsonResponse
    {
        $version = $transition->workflowVersion;
        $this->ensureDraftIsEditable($version->workflow, $version);

        $transition->delete();

        return response()->json(status: 204);
    }
}
