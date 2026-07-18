<?php

namespace App\Http\Controllers;

use App\Models\ProcessInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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

    public function show(ProcessInstance $instance): Response
    {
        Gate::authorize('view', $instance->workflowVersion->workflow);

        return Inertia::render('ProcessInstances/Show', [
            'instance' => [
                'id' => $instance->id,
                'code' => $instance->code,
                'name' => $instance->name,
                'status' => $instance->status->value,
            ],
            'graph' => $instance->toGraphPayload(),
        ]);
    }
}
