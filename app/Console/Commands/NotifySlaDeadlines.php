<?php

namespace App\Console\Commands;

use App\Enums\ProcessInstanceActivityStatus;
use App\Models\ProcessInstanceActivity;
use App\Models\User;
use App\Notifications\ActivitySlaApproachingNotification;
use App\Notifications\ActivitySlaOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Job agendado (02-motor-de-execucao.md §5 / 04-integracao-e-notificacoes.md §4, item 3) —
 * varre `process_instance_activities` pendentes com prazo, avisa quando ~80% do prazo foi
 * consumido e quando venceu. Informativo, não bloqueia a atividade. `sla_warning_notified_at`/
 * `sla_overdue_notified_at` garantem que cada aviso só é disparado uma vez por atividade.
 */
class NotifySlaDeadlines extends Command
{
    protected $signature = 'workflows:notify-sla-deadlines';

    protected $description = 'Notifica atividades com prazo (SLA) se aproximando ou vencido';

    private const WARNING_THRESHOLD = 0.8;

    public function handle(): int
    {
        $now = now();

        $this->notifyOverdue($now);
        $this->notifyApproaching($now);

        return self::SUCCESS;
    }

    private function notifyOverdue(Carbon $now): void
    {
        ProcessInstanceActivity::query()
            ->whereIn('status', [ProcessInstanceActivityStatus::Pending, ProcessInstanceActivityStatus::InProgress])
            ->whereNotNull('due_at')
            ->whereNull('sla_overdue_notified_at')
            ->where('due_at', '<', $now)
            ->with(['workflowActivity', 'processInstance'])
            ->chunkById(100, function (Collection $activities) {
                foreach ($activities as $activity) {
                    Notification::send($this->recipientsFor($activity), new ActivitySlaOverdueNotification($activity));
                    $activity->update(['sla_overdue_notified_at' => now()]);
                }
            });
    }

    private function notifyApproaching(Carbon $now): void
    {
        ProcessInstanceActivity::query()
            ->whereIn('status', [ProcessInstanceActivityStatus::Pending, ProcessInstanceActivityStatus::InProgress])
            ->whereNotNull('due_at')
            ->whereNotNull('started_at')
            ->whereNull('sla_warning_notified_at')
            ->whereNull('sla_overdue_notified_at')
            ->where('due_at', '>=', $now)
            ->with(['workflowActivity', 'processInstance'])
            ->chunkById(100, function (Collection $activities) use ($now) {
                foreach ($activities as $activity) {
                    if (! $this->isApproaching($activity, $now)) {
                        continue;
                    }

                    Notification::send($this->recipientsFor($activity), new ActivitySlaApproachingNotification($activity));
                    $activity->update(['sla_warning_notified_at' => now()]);
                }
            });
    }

    private function isApproaching(ProcessInstanceActivity $activity, Carbon $now): bool
    {
        $totalSeconds = $activity->started_at->diffInSeconds($activity->due_at);

        if ($totalSeconds <= 0) {
            return false;
        }

        $elapsedSeconds = $activity->started_at->diffInSeconds($now);

        return ($elapsedSeconds / $totalSeconds) >= self::WARNING_THRESHOLD;
    }

    /**
     * Mesma resolução de responsável usada ao ativar a atividade
     * (04-integracao-e-notificacoes.md §4, item 1): o atribuído direto, ou todo elegível da
     * fila quando `assigned_user_id` é nulo.
     *
     * @return Collection<int, User>|User[]
     */
    private function recipientsFor(ProcessInstanceActivity $activity): Collection|array
    {
        if ($activity->assigned_user_id) {
            return [$activity->assignedUser];
        }

        return $activity->workflowActivity->assigneeRole?->users ?? collect();
    }
}
