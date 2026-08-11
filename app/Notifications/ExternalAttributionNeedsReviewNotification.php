<?php

namespace App\Notifications;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\ProcessInstanceActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * 04-integracao-e-notificacoes.md §5 — um sistema externo completou uma atividade humana
 * (`task`/`form`) via API relatando um e-mail que não bate com um usuário do Aresta vinculado
 * ao papel da atividade (ou não existe usuário nenhum com esse e-mail ainda). A atividade já
 * foi completada (não trava o processo por causa de um cadastro pendente) — isso só avisa o
 * admin da organização pra revisar o vínculo, nunca vincula sozinho (vincular automaticamente
 * esvaziaria a checagem de "precisa pertencer ao papel").
 */
class ExternalAttributionNeedsReviewNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ProcessInstanceActivity $activity,
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
        $activityName = $this->activity->workflowActivity->name;
        $instance = $this->activity->processInstance;
        $name = $this->activity->completed_by_external_name;
        $email = $this->activity->completed_by_external_email;

        $message = (new MailMessage)
            ->subject("Revisar atribuição externa: {$activityName}")
            ->line("\"{$name}\" ({$email}) completou a atividade \"{$activityName}\" do processo \"{$instance->name}\" ({$instance->code}) via integração externa, mas não está vinculado ao papel responsável por essa atividade.")
            ->line('A atividade já foi concluída normalmente — isso é só um aviso para revisar o vínculo, se fizer sentido.');

        $roleId = $this->activity->workflowActivity->assignee_role_id;

        if ($roleId) {
            $message->action('Revisar papel', RoleResource::getUrl('edit', ['record' => $roleId]));
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'process_instance_activity_id' => $this->activity->id,
            'completed_by_external_name' => $this->activity->completed_by_external_name,
            'completed_by_external_email' => $this->activity->completed_by_external_email,
            'message' => "Revisar atribuição externa: {$this->activity->workflowActivity->name}",
        ];
    }
}
