<?php

namespace App\Notifications;

use App\Models\ProcessInstanceActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 04-integracao-e-notificacoes.md §4, item 1: disparada ao criar uma `process_instance_activity`
 * já com `assigned_user_id` resolvido (responsável fixo), ou para cada usuário elegível quando
 * a atividade cai na fila (`assigned_user_id` nulo, por papel).
 */
class ActivityAssignedNotification extends Notification
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
        $instance = $this->activity->processInstance;
        $name = $this->activity->workflowActivity->name;

        return (new MailMessage)
            ->subject("Nova tarefa: {$name}")
            ->line("Você tem uma nova atividade pendente: \"{$name}\", no processo \"{$instance->name}\" ({$instance->code}).")
            ->action('Ver minhas tarefas', url('/inbox'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'process_instance_activity_id' => $this->activity->id,
            'process_instance_id' => $this->activity->process_instance_id,
            'message' => "Nova atividade: {$this->activity->workflowActivity->name}",
        ];
    }
}
