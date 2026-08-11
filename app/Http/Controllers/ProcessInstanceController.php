<?php

namespace App\Http\Controllers;

use App\Enums\WorkflowVersionStatus;
use App\Exceptions\WorkflowNotPublishedException;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceActivity;
use App\Models\Workflow;
use App\Services\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Acompanhamento somente-leitura de uma instância em execução
 * (00-visao-geral.md §8, fase 6 / 03-editor-visual.md §7) — o diferencial em relação ao
 * legado, que só mostra barra de progresso linear.
 */
class ProcessInstanceController extends Controller
{
    public function index(Request $request): Response
    {
        $instances = ProcessInstance::query()
            ->with('workflowVersion.workflow')
            ->orderByDesc('started_at')
            ->get()
            ->map(fn (ProcessInstance $instance) => [
                'id' => $instance->id,
                'code' => $instance->code,
                'name' => $instance->name,
                'status' => $instance->status->value,
                'workflowName' => $instance->workflowVersion->workflow->name,
                'startedAt' => $instance->started_at?->toIso8601String(),
            ]);

        return Inertia::render('ProcessInstances/Index', [
            'instances' => $instances,
        ]);
    }

    /**
     * Inicia uma instância a partir da UI logada, para teste manual do fluxo completo
     * (criar → publicar → iniciar → concluir atividades pelo Inbox) sem depender de tinker
     * ou de um token Sanctum de sistema externo. Gate em `update` (não `view`): iniciar tem
     * efeito colateral real (cria linhas, dispara notificações), não é só leitura — não há
     * uma permissão dedicada "iniciar instância" na spec ainda, então reaproveita a mesma
     * regra de quem pode editar o desenho (`workflow-editor`/`workflow-admin`).
     */
    public function store(Request $request, Workflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $workflow);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'context' => ['nullable', 'string'],
        ]);

        $context = [];

        if (filled($data['context'] ?? null)) {
            $decoded = json_decode($data['context'], true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw ValidationException::withMessages(['context' => 'Informe um JSON válido (objeto) ou deixe em branco.']);
            }

            $context = $decoded;
        }

        try {
            $instance = app(WorkflowEngine::class)->start($workflow, $context, $request->user(), $data['name'] ?? null);
        } catch (WorkflowNotPublishedException) {
            throw ValidationException::withMessages(['name' => 'Este workflow não tem uma versão publicada.']);
        }

        return redirect()->route('process-instances.show', $instance->code);
    }

    public function show(Request $request, ProcessInstance $instance): Response
    {
        $workflow = $instance->workflowVersion->workflow;

        Gate::authorize('view', $workflow);

        $workflow->load(['versions' => fn ($query) => $query->where('status', WorkflowVersionStatus::Draft)]);

        return Inertia::render('ProcessInstances/Show', [
            'instance' => [
                'id' => $instance->id,
                'code' => $instance->code,
                'name' => $instance->name,
                'status' => $instance->status->value,
            ],
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'draftVersionId' => $workflow->versions->first()?->id,
            ],
            'graph' => $instance->toGraphPayload(),
            'tasks' => $this->tasksPayload($instance, $request->user()),
        ]);
    }

    /**
     * Lista de atividades da instância pra sidebar de acompanhamento (barra lateral direita):
     * clicar num item foca o nó correspondente no diagrama (`nodeId` casa com o formato
     * `activity-{id}` gerado por `WorkflowVersion::toGraphPayload()`). Ordenada pela ordem real
     * de execução (`started_at`, com fallback pro id em atividades ainda não iniciadas), não pela
     * ordem de desenho — é o que corresponde ao que o usuário vê acontecer.
     *
     * `canComplete`/`canClaim` reaproveitam a mesma `ProcessInstanceActivityPolicy` do Inbox
     * (04-integracao-e-notificacoes.md §2.2) — a sidebar só oferece "assumir"/"concluir" quando
     * o usuário logado de fato é elegível, sem duplicar a regra de elegibilidade aqui.
     */
    private function tasksPayload(ProcessInstance $instance, $user): array
    {
        return $instance->activities()
            ->with(['workflowActivity.outgoingTransitions', 'assignedUser'])
            ->get()
            ->sortBy([['started_at', 'asc'], ['id', 'asc']])
            ->map(fn (ProcessInstanceActivity $activity) => [
                'id' => $activity->id,
                'nodeId' => "activity-{$activity->workflow_activity_id}",
                'name' => $activity->workflowActivity->name,
                'type' => $activity->workflowActivity->type->value,
                'status' => $activity->status->value,
                'assignedUserName' => $activity->assignedUser?->name,
                'completedByExternalName' => $activity->completed_by_external_name,
                'externalAttributionNeedsReview' => $activity->external_attribution_needs_review,
                'isQueued' => $activity->assigned_user_id === null,
                'startedAt' => $activity->started_at?->toIso8601String(),
                'completedAt' => $activity->completed_at?->toIso8601String(),
                'dueAt' => $activity->due_at?->toIso8601String(),
                'fields' => $activity->workflowActivity->fieldsWithOptions(),
                'canComplete' => Gate::forUser($user)->allows('complete', $activity),
                'canClaim' => Gate::forUser($user)->allows('claim', $activity),
            ])
            ->values()
            ->all();
    }
}
