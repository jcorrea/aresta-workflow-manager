<?php

namespace App\Http\Controllers;

use App\Models\ProcessInstanceActivity;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ActivityClaimedByAnotherNotification;
use App\Services\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Assumir/concluir uma atividade — acionado tanto pelo Inbox ("Minhas tarefas") quanto pela
 * sidebar de tarefas do acompanhamento de instância (`ProcessInstanceController::show`). Os dois
 * pontos de entrada compartilham a mesma rota/policy; por isso os redirects usam `back()` em vez
 * de uma rota fixa — cada um volta pra tela de onde veio.
 */
class ProcessInstanceActivityController extends Controller
{
    public function claim(Request $request, ProcessInstanceActivity $activity): RedirectResponse
    {
        Gate::authorize('claim', $activity);

        // `WHERE assigned_user_id IS NULL` no próprio UPDATE evita corrida entre dois usuários
        // clicando "assumir" ao mesmo tempo — só um UPDATE afeta a linha
        // (04-integracao-e-notificacoes.md §3).
        $claimed = ProcessInstanceActivity::query()
            ->whereKey($activity->id)
            ->whereNull('assigned_user_id')
            ->update(['assigned_user_id' => $request->user()->id]);

        if ($claimed > 0) {
            $this->notifyOtherEligibleUsers($activity, $request->user());
        }

        return redirect()->back(fallback: route('inbox.index'));
    }

    public function complete(Request $request, ProcessInstanceActivity $activity): RedirectResponse
    {
        Gate::authorize('complete', $activity);

        $data = $request->validate([
            'result' => ['sometimes', 'array'],
            'form_data' => ['sometimes', 'array'],
        ]);

        if (isset($data['form_data'])) {
            $activity->update(['form_data' => $data['form_data']]);
        }

        app(WorkflowEngine::class)->completeActivity($activity, $data['result'] ?? []);

        return redirect()->back(fallback: route('inbox.index'));
    }

    private function notifyOtherEligibleUsers(ProcessInstanceActivity $activity, User $claimedBy): void
    {
        $roleId = $activity->workflowActivity->assignee_role_id;

        if (! $roleId) {
            return;
        }

        $others = Role::query()->find($roleId)
            ?->users()
            ->where('users.id', '!=', $claimedBy->id)
            ->get() ?? collect();

        Notification::send($others, new ActivityClaimedByAnotherNotification($activity->fresh(), $claimedBy));
    }
}
