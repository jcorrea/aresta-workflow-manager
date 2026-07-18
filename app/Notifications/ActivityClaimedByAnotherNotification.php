<?php

namespace App\Notifications;

use App\Models\ProcessInstanceActivity;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 04-integracao-e-notificacoes.md §4, item 2: quando alguém "assume" uma atividade da fila, os
 * demais elegíveis são avisados que ela não está mais disponível — evita trabalho duplicado
 * (melhoria em relação ao legado, que não tinha noção de fila/concorrência).
 */
class ActivityClaimedByAnotherNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ProcessInstanceActivity $activity,
        public readonly User $claimedBy,
    ) {}

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
            ->subject("Tarefa já assumida: {$name}")
            ->line("\"{$name}\" foi assumida por {$this->claimedBy->name} e não está mais disponível na fila.");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'process_instance_activity_id' => $this->activity->id,
            'claimed_by' => $this->claimedBy->id,
            'message' => "{$this->activity->workflowActivity->name} foi assumida por {$this->claimedBy->name}.",
        ];
    }
}
