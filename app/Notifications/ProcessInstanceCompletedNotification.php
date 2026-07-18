<?php

namespace App\Notifications;

use App\Models\ProcessInstance;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 04-integracao-e-notificacoes.md §4, item 4: notifica quem iniciou a instância
 * (`started_by`) quando ela chega a um nó `is_end`.
 */
class ProcessInstanceCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ProcessInstance $instance) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Processo concluído: {$this->instance->name}")
            ->line("O processo \"{$this->instance->name}\" ({$this->instance->code}) foi concluído.");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'process_instance_id' => $this->instance->id,
            'message' => "Processo concluído: {$this->instance->name}",
        ];
    }
}
