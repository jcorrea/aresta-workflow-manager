<?php

namespace App\Http\Controllers;

use App\Enums\ActivationAction;
use App\Enums\WorkflowVersionStatus;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\WorkflowVersionActivation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkflowController extends Controller
{
    public function index(Request $request): Response
    {
        $workflows = Workflow::query()
            ->with(['currentPublishedVersion', 'versions' => fn ($query) => $query->where('status', WorkflowVersionStatus::Draft)])
            ->orderBy('name')
            ->get()
            ->map(fn (Workflow $workflow) => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'slug' => $workflow->slug,
                'hasPublishedVersion' => $workflow->currentPublishedVersion !== null,
                'draftVersionId' => $workflow->versions->first()?->id,
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
            'description' => ['nullable', 'string'],
        ]);

        $organization = $request->user()->organizations()->findOrFail($data['organization_id']);

        Gate::authorize('create', [Workflow::class, $organization]);

        $workflow = Workflow::create([
            'organization_id' => $organization->id,
            'name' => $data['name'],
            'slug' => Workflow::uniqueSlug($data['name'], $organization->id),
            'description' => $data['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'status' => WorkflowVersionStatus::Draft,
            'created_by' => $request->user()->id,
            'draft_lock_workflow_id' => $workflow->id,
        ]);

        return redirect()->route('workflows.versions.edit', [$workflow, $version]);
    }

    public function show(Workflow $workflow): Response
    {
        Gate::authorize('view', $workflow);

        $workflow->load(['currentPublishedVersion', 'versions' => fn ($query) => $query->where('status', WorkflowVersionStatus::Draft)]);

        $versions = WorkflowVersion::query()
            ->where('workflow_id', $workflow->id)
            ->where('status', WorkflowVersionStatus::Published)
            ->orderByDesc('version_number')
            ->get(['id', 'version_number', 'published_at']);

        return Inertia::render('Workflows/Show', [
            'workflow' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'slug' => $workflow->slug,
                'description' => $workflow->description,
                'currentPublishedVersionId' => $workflow->current_published_version_id,
                'draftVersionId' => $workflow->versions->first()?->id,
            ],
            'publishedVersions' => $versions,
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
}
