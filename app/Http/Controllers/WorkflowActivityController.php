<?php

namespace App\Http\Controllers;

use App\Enums\AssigneeType;
use App\Enums\WorkflowActivityType;
use App\Http\Controllers\Concerns\EnsuresDraftIsEditable;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkflowActivityController extends Controller
{
    use EnsuresDraftIsEditable;

    public function store(Request $request, Workflow $workflow, WorkflowVersion $version): JsonResponse
    {
        $this->ensureDraftIsEditable($workflow, $version);

        $data = $request->validate([
            'workflow_step_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(WorkflowActivityType::class)],
            'assignee_type' => ['nullable', Rule::enum(AssigneeType::class)],
            'assignee_role_id' => ['nullable', 'integer'],
            'assignee_user_id' => ['nullable', 'integer'],
            'config' => ['array'],
            'sla_hours' => ['nullable', 'integer', 'min:0'],
            'position_x' => ['integer'],
            'position_y' => ['integer'],
            'is_start' => ['boolean'],
            'is_end' => ['boolean'],
        ]);

        $step = WorkflowStep::query()->where('workflow_version_id', $version->id)->findOrFail($data['workflow_step_id']);

        $activity = WorkflowActivity::create([...$data, 'workflow_step_id' => $step->id]);

        return response()->json(['id' => $activity->id]);
    }

    public function update(Request $request, WorkflowActivity $activity): JsonResponse
    {
        $version = $activity->workflowStep->workflowVersion;
        $this->ensureDraftIsEditable($version->workflow, $version);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(WorkflowActivityType::class)],
            'assignee_type' => ['sometimes', 'nullable', Rule::enum(AssigneeType::class)],
            'assignee_role_id' => ['sometimes', 'nullable', 'integer'],
            'assignee_user_id' => ['sometimes', 'nullable', 'integer'],
            'config' => ['sometimes', 'array'],
            'sla_hours' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'position_x' => ['sometimes', 'integer'],
            'position_y' => ['sometimes', 'integer'],
            'is_start' => ['sometimes', 'boolean'],
            'is_end' => ['sometimes', 'boolean'],
        ]);

        $activity->update($data);

        return response()->json(['id' => $activity->id]);
    }

    public function destroy(WorkflowActivity $activity): JsonResponse
    {
        $version = $activity->workflowStep->workflowVersion;
        $this->ensureDraftIsEditable($version->workflow, $version);

        $activity->delete();

        return response()->json(status: 204);
    }
}
