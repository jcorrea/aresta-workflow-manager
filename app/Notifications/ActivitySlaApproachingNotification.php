<?php

namespace App\Notifications;

use App\Models\ProcessInstanceActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 04-integracao-e-notificacoes.md §4, item 3 / 02-motor-de-execucao.md §5: disparada pelo job
 * agendado quando ~80% do prazo (`due_at`) já foi consumido. Informativo — não bloqueia a
 * atividade.
 */
class ActivitySlaApproachingNotification extends Notification
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
            ->subject("Prazo se aproximando: {$name}")
            ->line("A atividade \"{$name}\" está com o prazo se aproximando (vence em {$this->activity->due_at}).")
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
            'message' => "Prazo se aproximando: {$this->activity->workflowActivity->name}",
        ];
    }
}
