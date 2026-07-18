<?php

namespace App\Notifications;

use App\Models\ProcessInstanceActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 04-integracao-e-notificacoes.md §4, item 3 / 02-motor-de-execucao.md §5: `due_at < now()`.
 * Informativo/gerencial — não bloqueia o avanço da atividade (o legado também não bloqueava
 * por prazo).
 */
class ActivitySlaOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ProcessInstanceActivity $activity) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->activity->workflowActivity->name;

        return (new MailMessage)
            ->subject("Prazo vencido: {$name}")
            ->line("A atividade \"{$name}\" está com o prazo vencido desde {$this->activity->due_at}.")
            ->action('Ver minhas tarefas', url('/inbox'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'process_instance_activity_id' => $this->activity->id,
            'due_at' => $this->activity->due_at?->toIso8601String(),
            'message' => "Prazo vencido: {$this->activity->workflowActivity->name}",
        ];
    }
}
