<?php

namespace App\Notifications;

use App\Models\ProcessInstanceActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 04-integracao-e-notificacoes.md §4, item 5: uma `automated_action` que falha não deve travar
 * a instância silenciosamente — fica em `status = in_progress` com o erro registrado em
 * `result`, e notifica `workflow-admin` da organização para intervenção manual.
 */
class AutomatedActionFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ProcessInstanceActivity $activity,
        public readonly string $errorSummary,
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
        $instance = $this->activity->processInstance;

        return (new MailMessage)
            ->subject("Ação automática falhou: {$name}")
            ->line("A ação automática \"{$name}\" do processo \"{$instance->name}\" ({$instance->code}) falhou: {$this->errorSummary}")
            ->line('A instância está pausada nesse ponto até uma intervenção manual.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'process_instance_activity_id' => $this->activity->id,
            'error' => $this->errorSummary,
            'message' => "Ação automática falhou: {$this->activity->workflowActivity->name}",
        ];
    }
}
