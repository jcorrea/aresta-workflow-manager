<?php

namespace App\Http\Controllers;

use App\Enums\ActivationAction;
use App\Enums\GraphIssueSeverity;
use App\Enums\WorkflowVersionStatus;
use App\Models\Role;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\WorkflowVersionActivation;
use App\Exceptions\WorkflowDraftRefusedException;
use App\Services\AiWorkflowDraftRefiner;
use App\Services\WorkflowGraphValidator;
use App\Services\WorkflowVersionCloner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowVersionController extends Controller
{
    public function __construct(
        private readonly WorkflowGraphValidator $validator,
        private readonly WorkflowVersionCloner $cloner,
    ) {}

    public function edit(Workflow $workflow, WorkflowVersion $version): Response
    {
        Gate::authorize('update', $workflow);

        abort_unless($version->workflow_id === $workflow->id, 404);
        abort_unless($version->status === WorkflowVersionStatus::Draft, 422, 'Só é possível editar uma versão em rascunho.');

        $issues = $this->validator->validate($version);

        return Inertia::render('Workflows/Versions/Edit', [
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'description' => $workflow->description,
            ],
            'version' => [
                'id' => $version->id,
                'status' => $version->status->value,
            ],
            'graph' => $version->toGraphPayload(),
            'issues' => $issues->map(fn ($issue) => [
                'code' => $issue->code,
                'severity' => $issue->severity->value,
                'message' => $issue->message,
                'activityIds' => $issue->activityIds,
            ])->values(),
            'roles' => Role::query()
                ->where('organization_id', $workflow->organization_id)
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'users' => $workflow->organization->users()->orderBy('name')->get(['users.id', 'users.name']),
        ]);
    }

    public function refineWithAi(
        Request $request,
        Workflow $workflow,
        WorkflowVersion $version,
        AiWorkflowDraftRefiner $refiner,
    ): RedirectResponse {
        Gate::authorize('update', $workflow);

        abort_unless($version->workflow_id === $workflow->id, 404);
        abort_unless($version->status === WorkflowVersionStatus::Draft, 422, 'Só é possível editar uma versão em rascunho.');

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['required', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($refiner, $request, $workflow, $version, $data) {
                $refiner->refine(
                    organization: $workflow->organization,
                    createdBy: $request->user(),
                    workflow: $workflow,
                    version: $version,
                    updatedDescription: $data['description'] ?? null,
                    instructions: $data['instructions'],
                );
            });
        } catch (WorkflowDraftRefusedException $e) {
            throw ValidationException::withMessages([
                'instructions' => [$e->getMessage()],
            ]);
        }

        return redirect()->route('workflows.versions.edit', [$workflow, $version]);
    }

    /**
     * "Editar de novo" (01-modelo-de-dados.md §2.2.1, item 2) — só permitido sem draft ativo
     * (a constraint de banco de `draft_lock_workflow_id` garante isso; checamos antes também
     * para devolver um erro amigável em vez de deixar a exceção de constraint estourar).
     */
    public function store(Request $request, Workflow $workflow): RedirectResponse
    {
        Gate::authorize('update', $workflow);

        $existingDraft = WorkflowVersion::query()
            ->where('workflow_id', $workflow->id)
            ->where('status', WorkflowVersionStatus::Draft)
            ->first();

        if ($existingDraft) {
            return redirect()->route('workflows.versions.edit', [$workflow, $existingDraft]);
        }

        abort_unless($workflow->current_published_version_id, 422, 'Este workflow ainda não tem nenhuma versão publicada.');

        $newVersion = $this->cloner->cloneAsDraft($workflow->currentPublishedVersion, $workflow, $request->user());

        return redirect()->route('workflows.versions.edit', [$workflow, $newVersion]);
    }

    public function publish(Request $request, Workflow $workflow, WorkflowVersion $version): RedirectResponse
    {
        Gate::authorize('publish', $workflow);

        abort_unless($version->workflow_id === $workflow->id, 404);
        abort_unless($version->status === WorkflowVersionStatus::Draft, 422, 'Só é possível publicar uma versão em rascunho.');

        $issues = $this->validator->validate($version);
        $errors = $issues->filter(fn ($issue) => $issue->severity === GraphIssueSeverity::Error);

        if ($errors->isNotEmpty()) {
            throw ValidationException::withMessages([
                'graph' => $errors->map(fn ($issue) => $issue->message)->values()->all(),
            ]);
        }

        DB::transaction(function () use ($workflow, $version, $request) {
            $nextVersionNumber = WorkflowVersion::query()
                ->where('workflow_id', $workflow->id)
                ->max('version_number');

            $version->update([
                'version_number' => ($nextVersionNumber ?? 0) + 1,
                'status' => WorkflowVersionStatus::Published,
                'published_at' => now(),
                'draft_lock_workflow_id' => null,
            ]);

            $workflow->update(['current_published_version_id' => $version->id]);

            WorkflowVersionActivation::create([
                'workflow_id' => $workflow->id,
                'workflow_version_id' => $version->id,
                'action' => ActivationAction::Publish,
                'activated_by' => $request->user()->id,
                'activated_at' => now(),
            ]);
        });

        return redirect()->route('workflows.show', $workflow);
    }
}
