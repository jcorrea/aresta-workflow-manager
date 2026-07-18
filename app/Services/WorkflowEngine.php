<?php

namespace App\Services;

use App\Enums\AssigneeType;
use App\Enums\ConditionType;
use App\Enums\OrganizationRole;
use App\Enums\ProcessInstanceActivityStatus;
use App\Enums\ProcessInstanceStatus;
use App\Enums\WorkflowActivityType;
use App\Exceptions\WorkflowNotPublishedException;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceActivity;
use App\Models\ProcessInstanceTransitionLog;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowTransition;
use App\Notifications\ActivityAssignedNotification;
use App\Notifications\AutomatedActionFailedNotification;
use App\Notifications\ProcessInstanceCompletedNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role as PermissionRole;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Avalia o grafo (`workflow_activities` + `workflow_transitions`) de forma determinística —
 * substitui a "máquina de estados" implícita do legado (02-motor-de-execucao.md §1). Confia
 * que o grafo é bem formado (fork/join válidos); essa garantia é responsabilidade do
 * `WorkflowGraphValidator` na publicação (fase 3), não deste serviço (§6.1).
 */
class WorkflowEngine
{
    public function __construct(
        private readonly ConditionEvaluator $conditionEvaluator = new ConditionEvaluator,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function start(Workflow $workflow, array $context, User $startedBy, ?string $name = null): ProcessInstance
    {
        if (! $workflow->current_published_version_id) {
            throw new WorkflowNotPublishedException($workflow);
        }

        $version = $workflow->currentPublishedVersion;

        $instance = ProcessInstance::create([
            'workflow_version_id' => $version->id,
            'organization_id' => $workflow->organization_id,
            'code' => $this->generateCode(),
            'name' => $name ?? $workflow->name,
            'status' => ProcessInstanceStatus::Running,
            'started_by' => $startedBy->id,
            'started_at' => now(),
            'context' => $context,
        ]);

        $startActivities = WorkflowActivity::query()
            ->whereHas('workflowStep', fn ($query) => $query->where('workflow_version_id', $version->id))
            ->where('is_start', true)
            ->get();

        foreach ($startActivities as $activity) {
            $this->activateActivity($instance, $activity);
        }

        return $instance;
    }

    public function activateActivity(ProcessInstance $instance, WorkflowActivity $activity): ProcessInstanceActivity
    {
        $status = match ($activity->type) {
            WorkflowActivityType::Task, WorkflowActivityType::Form => ProcessInstanceActivityStatus::Pending,
            WorkflowActivityType::AutomatedAction, WorkflowActivityType::Condition => ProcessInstanceActivityStatus::InProgress,
        };

        $piActivity = ProcessInstanceActivity::create([
            'process_instance_id' => $instance->id,
            'workflow_activity_id' => $activity->id,
            'assigned_user_id' => $this->resolveAssignee($activity),
            'status' => $status,
            'due_at' => $activity->sla_hours ? now()->addHours($activity->sla_hours) : null,
            'started_at' => now(),
        ]);

        // `condition`/`automated_action` não esperam humano — avaliam/executam e concluem
        // na hora (§2, item 2), disparando advance() sem intervenção externa.
        if ($activity->type === WorkflowActivityType::Condition) {
            $this->completeActivity($piActivity, []);
        } elseif ($activity->type === WorkflowActivityType::AutomatedAction) {
            $this->runAutomatedAction($piActivity, $activity);
        } else {
            $this->notifyAssigned($piActivity, $activity);
        }

        return $piActivity;
    }

    /**
     * 04-integracao-e-notificacoes.md §4, item 1: notifica o responsável fixo diretamente, ou
     * todo elegível quando a atividade nasce em fila (`assigned_user_id` nulo, por papel).
     */
    private function notifyAssigned(ProcessInstanceActivity $piActivity, WorkflowActivity $activity): void
    {
        if ($piActivity->assigned_user_id) {
            $piActivity->assignedUser->notify(new ActivityAssignedNotification($piActivity));

            return;
        }

        if ($activity->assignee_type === AssigneeType::Role) {
            $eligibleUsers = $activity->assigneeRole?->users ?? collect();
            Notification::send($eligibleUsers, new ActivityAssignedNotification($piActivity));
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function completeActivity(ProcessInstanceActivity $activity, array $result = []): void
    {
        $activity->update([
            'status' => ProcessInstanceActivityStatus::Completed,
            'result' => $result,
            'completed_at' => now(),
        ]);

        $workflowActivity = $activity->workflowActivity;
        $instance = $activity->processInstance;

        if ($workflowActivity->is_end) {
            $instance->update([
                'status' => ProcessInstanceStatus::Completed,
                'completed_at' => now(),
            ]);

            $instance->startedBy->notify(new ProcessInstanceCompletedNotification($instance));

            return;
        }

        $this->advance($activity);
    }

    /**
     * O coração do motor (§3): fork (>=2 `always` saindo do nó, todas seguidas), decisão
     * (transições `expression` avaliadas em ordem, primeira que bater vence — XOR, com uma
     * `always` final opcional como fallback) e join (tratado em `followTransition()`/
     * `maybeActivateJoin()`, §3.3.2 — só contagem, nenhuma análise de grafo em runtime).
     */
    private function advance(ProcessInstanceActivity $activity): void
    {
        $instance = $activity->processInstance;
        $fromActivity = $activity->workflowActivity;

        $transitions = WorkflowTransition::query()
            ->where('from_activity_id', $fromActivity->id)
            ->orderBy('sort_order')
            ->get();

        $expressionTransitions = $transitions->where('condition_type', ConditionType::Expression);
        $alwaysTransitions = $transitions->where('condition_type', ConditionType::Always);

        if ($expressionTransitions->isNotEmpty()) {
            foreach ($expressionTransitions as $transition) {
                $matches = $this->conditionEvaluator->evaluate(
                    $transition->condition_expression ?? [],
                    $instance->context ?? [],
                    $activity->result ?? [],
                );

                if ($matches) {
                    $this->followTransition($instance, $fromActivity, $transition);

                    return;
                }
            }

            if ($alwaysTransitions->isNotEmpty()) {
                $this->followTransition($instance, $fromActivity, $alwaysTransitions->first());

                return;
            }

            // Fluxo mal desenhado (nenhum ramo bateu e não há fallback) — logar como erro de
            // configuração, não falhar silenciosamente nem adivinhar um caminho (§3.2).
            Log::error('WorkflowEngine: nenhuma transição correspondeu e não há fallback — instância travada.', [
                'process_instance_id' => $instance->id,
                'workflow_activity_id' => $fromActivity->id,
            ]);

            return;
        }

        foreach ($alwaysTransitions as $transition) {
            $this->followTransition($instance, $fromActivity, $transition);
        }
    }

    private function followTransition(ProcessInstance $instance, WorkflowActivity $fromActivity, WorkflowTransition $transition): void
    {
        ProcessInstanceTransitionLog::create([
            'process_instance_id' => $instance->id,
            'workflow_transition_id' => $transition->id,
            'from_activity_id' => $fromActivity->id,
            'to_activity_id' => $transition->to_activity_id,
            'transitioned_by' => null,
            'transitioned_at' => now(),
        ]);

        $toActivity = $transition->toActivity;

        if ($this->isJoin($toActivity)) {
            $this->maybeActivateJoin($instance, $toActivity);

            return;
        }

        $this->activateActivity($instance, $toActivity);
    }

    private function isJoin(WorkflowActivity $activity): bool
    {
        return WorkflowTransition::query()->where('to_activity_id', $activity->id)->count() > 1;
    }

    /**
     * Só conta chegadas, sem análise de grafo (§3.3.2) — a garantia de "bem formado" vem da
     * validação de publicação (fase 3). Em ciclo, a janela começa na conclusão mais recente
     * da própria ativação do join nesta instância (não da do fork): como fork e join sempre
     * se alternam dentro de um laço, é equivalente e não exige identificar F.
     */
    private function maybeActivateJoin(ProcessInstance $instance, WorkflowActivity $joinActivity): void
    {
        $totalIncoming = WorkflowTransition::query()->where('to_activity_id', $joinActivity->id)->count();

        $windowStart = ProcessInstanceActivity::query()
            ->where('process_instance_id', $instance->id)
            ->where('workflow_activity_id', $joinActivity->id)
            ->where('status', ProcessInstanceActivityStatus::Completed)
            ->orderByDesc('completed_at')
            ->value('completed_at');

        $arrivedTransitionIds = ProcessInstanceTransitionLog::query()
            ->where('process_instance_id', $instance->id)
            ->where('to_activity_id', $joinActivity->id)
            ->when($windowStart, fn ($query) => $query->where('transitioned_at', '>', $windowStart))
            ->pluck('workflow_transition_id')
            ->unique();

        if ($arrivedTransitionIds->count() >= $totalIncoming) {
            $this->activateActivity($instance, $joinActivity);
        }
    }

    private function resolveAssignee(WorkflowActivity $activity): ?int
    {
        if ($activity->assignee_type === AssigneeType::User) {
            return $activity->assignee_user_id;
        }

        if ($activity->assignee_type === AssigneeType::Role) {
            $eligibleUserIds = $activity->assigneeRole?->users()->pluck('users.id') ?? collect();

            // Auto-atribui só quando há exatamente 1 elegível; senão nasce em fila
            // (01-modelo-de-dados.md §3.4) — resolvida pela tela "Minhas tarefas"
            // (04-integracao-e-notificacoes.md §3), fora deste serviço.
            return $eligibleUserIds->count() === 1 ? $eligibleUserIds->first() : null;
        }

        return null;
    }

    /**
     * Uma ação automática que falha não trava a instância silenciosamente
     * (04-integracao-e-notificacoes.md §4, item 5): a atividade fica em `in_progress` com o
     * erro registrado em `result` (não chama `completeActivity()`, então `advance()` nunca
     * roda), e `workflow-admin` da organização é notificado para intervenção manual.
     */
    private function runAutomatedAction(ProcessInstanceActivity $piActivity, WorkflowActivity $activity): void
    {
        try {
            $result = $this->executeAutomatedAction($activity, $piActivity->processInstance);
        } catch (Throwable $exception) {
            $this->failAutomatedAction($piActivity, $exception->getMessage());

            return;
        }

        if (($result['successful'] ?? true) === false) {
            $this->failAutomatedAction($piActivity, "HTTP {$result['status']}");

            return;
        }

        $this->completeActivity($piActivity, $result);
    }

    private function failAutomatedAction(ProcessInstanceActivity $piActivity, string $errorSummary): void
    {
        $piActivity->update(['result' => ['error' => $errorSummary]]);

        $this->notifyWorkflowAdmins($piActivity, $errorSummary);
    }

    private function notifyWorkflowAdmins(ProcessInstanceActivity $piActivity, string $errorSummary): void
    {
        $organizationId = $piActivity->processInstance->organization_id;

        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($organizationId);

        $admins = PermissionRole::query()
            ->where('name', OrganizationRole::Admin->value)
            ->where('organization_id', $organizationId)
            ->first()
            ?->users ?? collect();

        $registrar->setPermissionsTeamId($previousTeamId);

        Notification::send($admins, new AutomatedActionFailedNotification($piActivity, $errorSummary));
    }

    /**
     * `send_email`/`generate_document` ainda não têm implementação real (fase 4/futura) —
     * só `webhook` é executado de fato por ora; os demais completam com resultado vazio em
     * vez de falhar, para não travar o grafo por uma ação ainda não construída.
     *
     * @return array<string, mixed>
     */
    private function executeAutomatedAction(WorkflowActivity $activity, ProcessInstance $instance): array
    {
        $config = $activity->config ?? [];

        return match ($config['action'] ?? null) {
            'webhook' => $this->executeWebhookAction($config, $instance),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function executeWebhookAction(array $config, ProcessInstance $instance): array
    {
        $payload = [
            'process_instance_id' => $instance->id,
            'context' => $instance->context,
        ];

        $response = match (strtolower($config['method'] ?? 'post')) {
            'get' => Http::get($config['url']),
            'put' => Http::put($config['url'], $payload),
            'patch' => Http::patch($config['url'], $payload),
            'delete' => Http::delete($config['url']),
            default => Http::post($config['url'], $payload),
        };

        return [
            'status' => $response->status(),
            'successful' => $response->successful(),
        ];
    }

    private function generateCode(): string
    {
        return sprintf('%d-%06d', now()->year, ProcessInstance::withoutGlobalScopes()->count() + 1);
    }
}
