<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresDraftIsEditable;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mutações estruturais do canvas (03-editor-visual.md §3) — respostas em JSON, não Inertia,
 * porque o editor é uma SPA de canvas com chamadas de fundo por mutação (arrastar, renomear,
 * conectar), não navegações de página inteira.
 */
class WorkflowStepController extends Controller
{
    use EnsuresDraftIsEditable;

    public function store(Request $request, Workflow $workflow, WorkflowVersion $version): JsonResponse
    {
        $this->ensureDraftIsEditable($workflow, $version);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sla_days' => ['nullable', 'integer', 'min:0'],
            'position_x' => ['integer'],
            'position_y' => ['integer'],
            'width' => ['integer', 'min:1'],
            'height' => ['integer', 'min:1'],
            'sort_order' => ['integer'],
        ]);

        $step = WorkflowStep::create([...$data, 'workflow_version_id' => $version->id]);

        return response()->json(['id' => $step->id]);
    }

    public function update(Request $request, WorkflowStep $step): JsonResponse
    {
        $this->ensureDraftIsEditable($step->workflowVersion->workflow, $step->workflowVersion);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'sla_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'position_x' => ['sometimes', 'integer'],
            'position_y' => ['sometimes', 'integer'],
            'width' => ['sometimes', 'integer', 'min:1'],
            'height' => ['sometimes', 'integer', 'min:1'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $step->update($data);

        return response()->json(['id' => $step->id]);
    }

    public function destroy(WorkflowStep $step): JsonResponse
    {
        $this->ensureDraftIsEditable($step->workflowVersion->workflow, $step->workflowVersion);

        $step->delete();

        return response()->json(status: 204);
    }
}
