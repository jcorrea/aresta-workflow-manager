<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssigneeType;
use App\Enums\ProcessInstanceActivityStatus;
use App\Enums\WorkflowActivityType;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceActivity;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Notifications\ExternalAttributionNeedsReviewNotification;
use App\Services\OrganizationAdmins;
use App\Services\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

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

        $organization = $this->activeOrganization($request, $data['organization_id']);

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
                'workflow_activity_id' => $activity->workflow_activity_id,
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
     * `automated_action`: confirmação de uma ação técnica, sem humano envolvido. `task`/`form`:
     * um sistema externo já conectado (ex.: giits-propostas) reportando que um humano completou
     * aquele passo por lá — exige `completed_by` pra registrar quem (04-integracao-e-
     * notificacoes.md §5). `condition` nunca é humano nem confirmação externa, não é aceito.
     */
    public function completeActivity(Request $request, string $code, int $activityId): JsonResponse
    {
        $instance = $this->instanceForOrganization($request, $code);

        $activity = $instance->activities()->with('workflowActivity')->findOrFail($activityId);
        $workflowActivity = $activity->workflowActivity;

        abort_unless(
            in_array($workflowActivity->type, [WorkflowActivityType::AutomatedAction, WorkflowActivityType::Task, WorkflowActivityType::Form], true),
            422,
            'Só atividades automated_action, task ou form podem ser completadas via API.',
        );
        abort_if($activity->status === ProcessInstanceActivityStatus::Completed, 422, 'Atividade já concluída.');

        $isHumanActivity = in_array($workflowActivity->type, [WorkflowActivityType::Task, WorkflowActivityType::Form], true);

        $data = $request->validate([
            'result' => ['sometimes', 'array'],
            'completed_by' => [$isHumanActivity ? 'required' : 'sometimes', 'array'],
            'completed_by.name' => [$isHumanActivity ? 'required' : 'sometimes', 'string', 'max:255'],
            'completed_by.email' => [$isHumanActivity ? 'required' : 'sometimes', 'email'],
        ]);

        if ($isHumanActivity) {
            $this->attributeHumanCompletion($workflowActivity, $activity, $data['completed_by']);
        }

        $this->engine->completeActivity($activity, $data['result'] ?? []);

        return response()->json(['status' => $activity->fresh()->status->value]);
    }

    /**
     * Seta (sem salvar — `WorkflowEngine::completeActivity` persiste tudo junto) a atribuição
     * humana na atividade: bate limpo com um usuário elegível pro papel/usuário do desenho ->
     * `assigned_user_id`, igual ao fluxo interno; senão cria a identidade (só isso, sem
     * vincular a nenhum papel — vincular automaticamente esvaziaria a checagem) e sinaliza pro
     * admin revisar, sem travar a conclusão da atividade.
     *
     * @param  array{name: string, email: string}  $completedBy
     */
    private function attributeHumanCompletion(WorkflowActivity $workflowActivity, ProcessInstanceActivity $activity, array $completedBy): void
    {
        if ($workflowActivity->assignee_type === AssigneeType::External) {
            $activity->completed_by_external_name = $completedBy['name'];
            $activity->completed_by_external_email = $completedBy['email'];

            return;
        }

        $user = User::where('email', $completedBy['email'])->first();
        $isNewUser = $user === null;
        $user ??= User::create(['name' => $completedBy['name'], 'email' => $completedBy['email']]);

        $isEligible = ! $isNewUser && match ($workflowActivity->assignee_type) {
            AssigneeType::User => $workflowActivity->assignee_user_id === $user->id,
            AssigneeType::Role => $workflowActivity->assigneeRole?->users()->where('users.id', $user->id)->exists() ?? false,
            default => false,
        };

        if ($isEligible) {
            $activity->assigned_user_id = $user->id;

            return;
        }

        // Atividades em fila (`assignee_type = role`) podem já ter `assigned_user_id`
        // preenchido pelo motor na ativação, quando havia exatamente um elegível
        // (`WorkflowEngine::resolveAssignee`) — quem completou de fato via API pode ser outra
        // pessoa, então limpa pra não ficar com os dois (nome errado ganhando na exibição).
        $activity->assigned_user_id = null;
        $activity->completed_by_external_name = $completedBy['name'];
        $activity->completed_by_external_email = $completedBy['email'];
        $activity->external_attribution_needs_review = true;

        Notification::send(
            OrganizationAdmins::for($activity->processInstance->organization_id),
            new ExternalAttributionNeedsReviewNotification($activity),
        );
    }

    /**
     * Token de `ExternalSystem` (por aplicação cliente, provisionado manualmente) continua sem
     * restrição de organização — mantém o comportamento já em produção. Token de `User`
     * (self-service, `ApiTokenController`) nasce escopado via ability `org:{id}` — sem essa
     * checagem aqui, qualquer usuário logado poderia trocar `organization_id` no request e
     * acessar dados de uma organização que não é a dele.
     */
    private function activeOrganization(Request $request, int $organizationId): Organization
    {
        $organization = Organization::query()
            ->where('id', $organizationId)
            ->where('active', true)
            ->firstOrFail();

        $user = $request->user();
        if ($user instanceof User) {
            abort_unless($user->tokenCan("org:{$organizationId}"), 403, 'Este token não tem permissão para esta organização.');
        }

        return $organization;
    }

    private function instanceForOrganization(Request $request, string $code): ProcessInstance
    {
        $organizationId = (int) $request->input('organization_id');
        abort_unless($organizationId, 422, 'organization_id é obrigatório.');

        $this->activeOrganization($request, $organizationId);

        return ProcessInstance::query()
            ->where('code', $code)
            ->where('organization_id', $organizationId)
            ->firstOrFail();
    }
}
