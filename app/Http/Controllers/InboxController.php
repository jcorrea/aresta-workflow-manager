<?php

namespace App\Http\Controllers;

use App\Enums\ProcessInstanceActivityStatus;
use App\Models\ProcessInstanceActivity;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tela "Minhas tarefas" (04-integracao-e-notificacoes.md §3): atividades atribuídas
 * diretamente ao usuário, mais as que estão na fila (`assigned_user_id` nulo) de um papel a
 * que ele pertence.
 */
class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $roleIds = Role::query()
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->pluck('id');

        $activities = ProcessInstanceActivity::query()
            ->whereIn('status', [ProcessInstanceActivityStatus::Pending, ProcessInstanceActivityStatus::InProgress])
            ->where(function ($query) use ($user, $roleIds) {
                $query->where('assigned_user_id', $user->id)
                    ->orWhere(function ($queued) use ($roleIds) {
                        $queued->whereNull('assigned_user_id')
                            ->whereHas('workflowActivity', fn ($wa) => $wa->whereIn('assignee_role_id', $roleIds));
                    });
            })
            ->with(['workflowActivity.outgoingTransitions', 'processInstance'])
            ->orderBy('due_at')
            ->get()
            ->map(fn (ProcessInstanceActivity $activity) => [
                'id' => $activity->id,
                'status' => $activity->status->value,
                'dueAt' => $activity->due_at?->toIso8601String(),
                'isQueued' => $activity->assigned_user_id === null,
                'processInstance' => [
                    'id' => $activity->processInstance->id,
                    'name' => $activity->processInstance->name,
                    'code' => $activity->processInstance->code,
                ],
                'workflowActivity' => [
                    'name' => $activity->workflowActivity->name,
                    'fields' => $activity->workflowActivity->fieldsWithOptions(),
                ],
            ])
            ->values();

        return Inertia::render('Inbox/Index', [
            'activities' => $activities,
        ]);
    }
}
