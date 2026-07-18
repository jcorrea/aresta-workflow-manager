<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProcessInstanceActivityStatus;
use App\Enums\WorkflowActivityType;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceActivity;
use App\Models\Workflow;
use App\Services\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API pública para sistemas GIITS externos (04-integracao-e-notificacoes.md §5) — autenticada
 * via Sanctum (`ExternalSystem`, não usuário final). Todo endpoint exige `organization_id`
 * explícito (query ou body) e valida que a organização existe/está ativa: o token não define
 * a organização, o request define — um mesmo sistema atende várias organizações.
 */
class ProcessInstanceController extends Controller
{
    public function __construct(private readonly WorkflowEngine $engine) {}

    public function start(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer'],
            'context' => ['sometimes', 'array'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $organization = $this->activeOrganization($data['organization_id']);

        $workflow = Workflow::query()
            ->where('organization_id', $organization->id)
            ->where('slug', $slug)
            ->firstOrFail();

        $instance = $this->engine->start(
            $workflow,
            $data['context'] ?? [],
            $request->user(),
            $data['name'] ?? null,
        );

        return response()->json([
            'id' => $instance->id,
            'code' => $instance->code,
            'status' => $instance->status->value,
        ], 201);
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $instance = $this->instanceForOrganization($request, $code);

        $instance->load(['activities' => fn ($query) => $query->whereIn('status', ['pending', 'in_progress'])]);

        return response()->json([
            'id' => $instance->id,
            'code' => $instance->code,
            'name' => $instance->name,
            'status' => $instance->status->value,
            'context' => $instance->context,
            'started_at' => $instance->started_at?->toIso8601String(),
            'completed_at' => $instance->completed_at?->toIso8601String(),
            'active_activities' => $instance->activities->pluck('id'),
        ]);
    }

    public function activities(Request $request, string $code): JsonResponse
    {
        $instance = $this->instanceForOrganization($request, $code);

        $activities = $instance->activities()
            ->with('workflowActivity')
            ->orderBy('id')
            ->get()
            ->map(fn (ProcessInstanceActivity $activity) => [
                'id' => $activity->id,
                'name' => $activity->workflowActivity->name,
                'type' => $activity->workflowActivity->type->value,
                'status' => $activity->status->value,
                'started_at' => $activity->started_at?->toIso8601String(),
                'completed_at' => $activity->completed_at?->toIso8601String(),
                'result' => $activity->result,
            ]);

        return response()->json(['activities' => $activities]);
    }

    /**
     * Restrito a `type = automated_action` (item em aberto §5: no MVP, sem uma flag própria
     * de "permitir conclusão via API" em `config`, essa é a única automação cuja "execução" é
     * justamente o sistema externo confirmar por aqui).
     */
    public function completeActivity(Request $request, string $code, int $activityId): JsonResponse
    {
        $instance = $this->instanceForOrganization($request, $code);

        $activity = $instance->activities()->with('workflowActivity')->findOrFail($activityId);

        abort_unless($activity->workflowActivity->type === WorkflowActivityType::AutomatedAction, 422, 'Só atividades automated_action podem ser completadas via API.');
        abort_if($activity->status === ProcessInstanceActivityStatus::Completed, 422, 'Atividade já concluída.');

        $data = $request->validate([
            'result' => ['sometimes', 'array'],
        ]);

        $this->engine->completeActivity($activity, $data['result'] ?? []);

        return response()->json(['status' => $activity->fresh()->status->value]);
    }

    private function activeOrganization(int $organizationId): Organization
    {
        return Organization::query()
            ->where('id', $organizationId)
            ->where('active', true)
            ->firstOrFail();
    }

    private function instanceForOrganization(Request $request, string $code): ProcessInstance
    {
        $organizationId = (int) $request->input('organization_id');
        abort_unless($organizationId, 422, 'organization_id é obrigatório.');

        $this->activeOrganization($organizationId);

        return ProcessInstance::query()
            ->where('code', $code)
            ->where('organization_id', $organizationId)
            ->firstOrFail();
    }
}
