<?php

namespace App\Http\Controllers;

use App\Enums\ActivationAction;
use App\Enums\WorkflowVersionStatus;
use App\Exceptions\WorkflowDraftRefusedException;
use App\Models\ProcessInstance;
use App\Models\ProcessInstanceActivity;
use App\Models\ProcessInstanceStep;
use App\Models\ProcessInstanceTransitionLog;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Models\WorkflowVersionActivation;
use App\Services\AiWorkflowDraftGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function __construct(private readonly AiWorkflowDraftGenerator $aiDraftGenerator) {}

    public function index(Request $request): Response
    {
        $workflows = Workflow::query()
            ->withCount('instances')
            ->with(['currentPublishedVersion', 'versions' => fn ($query) => $query->where('status', WorkflowVersionStatus::Draft)])
            ->orderBy('name')
            ->get()
            ->map(fn (Workflow $workflow) => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'slug' => $workflow->slug,
                'hasPublishedVersion' => $workflow->currentPublishedVersion !== null,
                'draftVersionId' => $workflow->versions->first()?->id,
                'instancesCount' => $workflow->instances_count,
            ]);

        return Inertia::render('Workflows/Index', [
            'workflows' => $workflows,
            'organizations' => $request->user()->organizations()->get(['organizations.id', 'organizations.name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            // max:2000 é novo (antes era ilimitado) — limita custo/latência do prompt quando o
            // campo é usado pra gerar o rascunho por IA (ver AiWorkflowDraftGenerator).
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $organization = $request->user()->organizations()->findOrFail($data['organization_id']);

        Gate::authorize('create', [Workflow::class, $organization]);

        try {
            [$workflow, $version] = DB::transaction(function () use ($organization, $data, $request) {
                $workflow = Workflow::create([
                    'organization_id' => $organization->id,
                    'name' => $data['name'],
                    'slug' => Workflow::uniqueSlug($data['name'], $organization->id),
                    'description' => $data['description'] ?? null,
                    'created_by' => $request->user()->id,
                ]);

                if (filled($data['description'] ?? null)) {
                    // Lança WorkflowDraftRefusedException se a IA recusar (descrição fora de
                    // assunto) ou falhar — a transaction desfaz o $workflow criado acima junto,
                    // nada fica órfão.
                    $version = $this->aiDraftGenerator->generate(
                        $organization,
                        $request->user(),
                        $workflow,
                        $data['description'],
                    );
                } else {
                    $version = WorkflowVersion::create([
                        'workflow_id' => $workflow->id,
                        'status' => WorkflowVersionStatus::Draft,
                        'created_by' => $request->user()->id,
                        'draft_lock_workflow_id' => $workflow->id,
                    ]);
                }

                return [$workflow, $version];
            });
        } catch (WorkflowDraftRefusedException $e) {
            throw ValidationException::withMessages(['description' => $e->getMessage()]);
        }

        return redirect()->route('workflows.versions.edit', [$workflow, $version]);
    }

    public function show(Workflow $workflow): Response
    {
        Gate::authorize('view', $workflow);

        $workflow->load([
            'organization',
            'createdBy',
            'currentPublishedVersion',
            'versions' => fn ($query) => $query->where('status', WorkflowVersionStatus::Draft),
        ]);

        $instancesCount = ProcessInstance::query()
            ->whereHas('workflowVersion', fn ($q) => $q->where('workflow_id', $workflow->id))
            ->count();

        $versions = WorkflowVersion::query()
            ->with('createdBy')
            ->where('workflow_id', $workflow->id)
            ->where('status', WorkflowVersionStatus::Published)
            ->orderByDesc('version_number')
            ->get(['id', 'version_number', 'published_at', 'created_by']);

        return Inertia::render('Workflows/Show', [
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'slug' => $workflow->slug,
                'description' => $workflow->description,
                'organizationName' => $workflow->organization?->name,
                'createdByName' => $workflow->createdBy?->name,
                'createdAt' => $workflow->created_at?->format('d/m/Y H:i'),
                'currentPublishedVersionId' => $workflow->current_published_version_id,
                'currentPublishedVersionNumber' => $workflow->currentPublishedVersion?->version_number,
                'draftVersionId' => $workflow->versions->first()?->id,
                'instancesCount' => $instancesCount,
            ],
            'publishedVersions' => $versions->map(fn ($v) => [
                'id' => $v->id,
                'version_number' => $v->version_number,
                'published_at' => $v->published_at?->format('d/m/Y H:i') ?? $v->published_at,
                'published_by_name' => $v->createdBy?->name,
            ]),
        ]);
    }

    /**
     * Tela com a descrição do processo + o modelo do diagrama (versão publicada, ou o rascunho
     * se ainda não houver nenhuma publicada), pra a pessoa levar pra IA que usa na implementação
     * do próprio software e essa IA identificar os pontos de integração e chamadas da API pública
     * (04-integracao-e-notificacoes.md §5) de acordo com a stack dela.
     */
    public function integrationInstructions(Request $request, Workflow $workflow): Response
    {
        Gate::authorize('view', $workflow);

        $workflow->load([
            'organization',
            'currentPublishedVersion',
            'versions' => fn ($query) => $query->where('status', WorkflowVersionStatus::Draft),
        ]);

        $version = $workflow->currentPublishedVersion ?? $workflow->versions->first();

        abort_unless($version, 404, 'Este workflow ainda não tem nenhuma versão (publicada ou rascunho) pra gerar instruções.');

        $activeToken = $request->user()->tokens()
            ->get()
            ->first(fn ($token) => in_array("org:{$workflow->organization_id}", $token->abilities ?? [], true)
                && (! $token->expires_at || $token->expires_at->isFuture()));

        return Inertia::render('Workflows/IntegrationInstructions', [
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'slug' => $workflow->slug,
                'description' => $workflow->description,
                'organizationId' => $workflow->organization_id,
                'organizationName' => $workflow->organization?->name,
            ],
            'version' => [
                'id' => $version->id,
                'status' => $version->status->value,
                'versionNumber' => $version->version_number,
            ],
            'graph' => $version->toGraphPayload(),
            'apiBaseUrl' => rtrim(config('app.url'), '/').'/api',
            'apiToken' => [
                'hasActiveToken' => $activeToken !== null,
                'expiresAt' => $activeToken?->expires_at?->format('d/m/Y'),
            ],
        ]);
    }

    public function rollback(Request $request, Workflow $workflow): RedirectResponse
    {
        Gate::authorize('publish', $workflow);

        $data = $request->validate([
            'workflow_version_id' => ['required', 'integer'],
        ]);

        $target = WorkflowVersion::query()
            ->where('workflow_id', $workflow->id)
            ->where('status', WorkflowVersionStatus::Published)
            ->findOrFail($data['workflow_version_id']);

        $workflow->update(['current_published_version_id' => $target->id]);

        WorkflowVersionActivation::create([
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $target->id,
            'action' => ActivationAction::Rollback,
            'activated_by' => $request->user()->id,
            'activated_at' => now(),
        ]);

        return redirect()->route('workflows.show', $workflow);
    }

    public function destroy(Request $request, Workflow $workflow): RedirectResponse
    {
        Gate::authorize('delete', $workflow);

        $confirmCascade = $request->boolean('confirm_cascade');
        $instancesCount = ProcessInstance::query()
            ->whereHas('workflowVersion', fn ($query) => $query->where('workflow_id', $workflow->id))
            ->count();

        if ($instancesCount > 0 && ! $confirmCascade) {
            throw ValidationException::withMessages([
                'confirm_cascade' => ['Este workflow possui instâncias executadas. É necessário confirmar a exclusão completa do histórico.'],
            ]);
        }

        DB::transaction(function () use ($workflow) {
            // Desconecta a versão publicada para evitar FK circular
            $workflow->update(['current_published_version_id' => null]);

            $versionIds = WorkflowVersion::where('workflow_id', $workflow->id)->pluck('id');

            if ($versionIds->isNotEmpty()) {
                $instanceIds = ProcessInstance::whereIn('workflow_version_id', $versionIds)->pluck('id');

                if ($instanceIds->isNotEmpty()) {
                    ProcessInstanceTransitionLog::whereIn('process_instance_id', $instanceIds)->delete();
                    ProcessInstanceActivity::whereIn('process_instance_id', $instanceIds)->delete();
                    ProcessInstanceStep::whereIn('process_instance_id', $instanceIds)->delete();
                    ProcessInstance::whereIn('id', $instanceIds)->delete();
                }

                WorkflowTransition::whereIn('workflow_version_id', $versionIds)->delete();
                WorkflowActivity::whereHas('workflowStep', fn ($q) => $q->whereIn('workflow_version_id', $versionIds))->delete();
                WorkflowStep::whereIn('workflow_version_id', $versionIds)->delete();
                WorkflowVersionActivation::whereIn('workflow_version_id', $versionIds)->delete();

                WorkflowVersion::whereIn('id', $versionIds)->update(['draft_lock_workflow_id' => null]);
                WorkflowVersion::whereIn('id', $versionIds)->delete();
            }

            $workflow->delete();
        });

        return redirect()->route('workflows.index');
    }
}
