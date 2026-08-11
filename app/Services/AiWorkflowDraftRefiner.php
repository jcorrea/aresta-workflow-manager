<?php

namespace App\Services;

use App\Exceptions\WorkflowDraftRefusedException;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Support\Collection;

/**
 * Refina e reestrutura uma `WorkflowVersion` em rascunho (draft) utilizando Inteligência Artificial
 * a partir do grafo atual e de instruções em linguagem natural fornecidas pelo usuário.
 */
class AiWorkflowDraftRefiner extends AiWorkflowDraftGenerator
{
    public function refine(
        Organization $organization,
        User $createdBy,
        Workflow $workflow,
        WorkflowVersion $version,
        ?string $updatedDescription,
        string $instructions,
    ): WorkflowVersion {
        $this->throttle($createdBy);

        $roles = Role::query()
            ->where('organization_id', $organization->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $currentGraph = $version->toGraphPayload();

        $prompt = $this->buildRefinePrompt(
            currentWorkflowName: $workflow->name,
            currentDescription: $updatedDescription ?? $workflow->description ?? '',
            currentGraph: $currentGraph,
            roles: $roles,
            instructions: $instructions,
        );

        $raw = $this->callLlmAndValidateShape(
            $prompt,
            genericError: 'A IA não conseguiu aplicar essas instruções de forma consistente. Tente novamente ou ajuste o texto.',
        );

        if (($raw['is_workflow_description'] ?? null) !== true) {
            throw new WorkflowDraftRefusedException(
                $raw['refusal_reason'] ?? 'Não foi possível refinar o processo com as instruções fornecidas.',
            );
        }

        $raw['transitions'] = $this->filterResolvableTransitions($raw);

        if ($updatedDescription !== null && $updatedDescription !== $workflow->description) {
            $workflow->update(['description' => $updatedDescription]);
        }

        // Limpa o grafo atual da versão em rascunho
        WorkflowTransition::where('workflow_version_id', $version->id)->delete();
        WorkflowActivity::whereHas('workflowStep', fn ($q) => $q->where('workflow_version_id', $version->id))->delete();
        WorkflowStep::where('workflow_version_id', $version->id)->delete();

        $activityIdMap = [];

        foreach ($raw['steps'] as $stepIndex => $step) {
            $newStep = WorkflowStep::create([
                'workflow_version_id' => $version->id,
                'name' => $step['name'],
                'position_x' => 80 + $stepIndex * 360,
                'position_y' => 80,
                'width' => 320,
                'height' => 220,
                'sort_order' => $stepIndex,
            ]);

            foreach ($step['activities'] as $actIndex => $activity) {
                ['type' => $assigneeType, 'role_id' => $assigneeRoleId] = $this->resolveAssignee($activity, $roles, $organization);

                $newActivity = WorkflowActivity::create([
                    'workflow_step_id' => $newStep->id,
                    'name' => $activity['name'],
                    'type' => $activity['type'],
                    'assignee_type' => $assigneeType,
                    'assignee_role_id' => $assigneeRoleId,
                    'assignee_user_id' => null,
                    'config' => $activity['config'] ?? [],
                    'sla_hours' => $activity['sla_hours'] ?? null,
                    'position_x' => 20,
                    'position_y' => 48 + $actIndex * 90,
                    'is_start' => $activity['is_start'] ?? false,
                    'is_end' => $activity['is_end'] ?? false,
                ]);

                $activityIdMap[$activity['tmp_id']] = $newActivity->id;
            }
        }

        foreach ($raw['transitions'] as $sortOrder => $transition) {
            WorkflowTransition::create([
                'workflow_version_id' => $version->id,
                'from_activity_id' => $activityIdMap[$transition['from_tmp_id']],
                'to_activity_id' => $activityIdMap[$transition['to_tmp_id']],
                'condition_type' => $transition['condition_type'],
                'condition_expression' => $transition['condition_expression'] ?? null,
                'label' => $transition['label'] ?? null,
                'sort_order' => $sortOrder,
            ]);
        }

        return $version;
    }

    private function buildRefinePrompt(
        string $currentWorkflowName,
        string $currentDescription,
        array $currentGraph,
        Collection $roles,
        string $instructions,
    ): string {
        $rolesText = $roles->isEmpty()
            ? '(nenhum papel cadastrado nesta organização)'
            : $roles->map(fn (Role $role) => "id={$role->id}: {$role->name}")->implode(', ');

        $graphJson = json_encode($currentGraph, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
            Você é um assistente especialista em modelagem de processos de trabalho (workflows) de empresas.
            Sua função é AJUSTAR / REFINAR um processo existente com base nas instruções de alteração fornecidas pelo usuário.

            Informações do processo atual:
            - Nome do Workflow: {$currentWorkflowName}
            - Descrição atual: {$currentDescription}

            Grafo atual do processo (JSON com etapas, atividades e transições existentes):
            {$graphJson}

            Papéis disponíveis nesta organização (use "id" em assignee_role_id, ou se for um papel novo forneça assignee_role_name):
            {$rolesText}

            Instruções do usuário para ajuste/refinamento:
            """
            {$instructions}
            """

            Regras obrigatórias para a resposta:
            1. Analise o grafo atual e aplique as modificações solicitadas pelo usuário (adicionar, alterar, remover etapas/atividades/transições ou alterar responsáveis/SLA/configurações).
            2. Mantenha a estrutura consistente e funcional:
               - Exatamente UMA atividade deve ter "is_start": true.
               - Pelo menos uma atividade deve ter "is_end": true.
            3. Tipos de atividade válidos: "task" (tarefa manual), "form" (formulário), "automated_action" (ação automática), "condition" (decisão).
            4. Atribuição de responsáveis (para "task" ou "form"):
               - Se o papel citado existir na lista de papéis da organização, use "assignee_type": "role" e "assignee_role_id": <ID>.
               - Se o papel citado for NOVO e não estiver na lista, use "assignee_type": "role", "assignee_role_id": null e "assignee_role_name": "<Nome do Papel>".
               - Se a instrução remover a responsabilidade ou não indicar responsável, use "assignee_type": null, "assignee_role_id": null, "assignee_role_name": null.
            5. Cada atividade precisa de um "tmp_id" único (ex: "a1", "a2", ...).
            6. Transições usam "condition_type": "always" (sequencial/paralelo) ou "expression" (decisão condicional).
            7. Responda SOMENTE em JSON estruturado com o seguinte formato:

            {
              "is_workflow_description": true,
              "refusal_reason": null,
              "steps": [
                {
                  "name": "string",
                  "activities": [
                    {
                      "tmp_id": "string",
                      "name": "string",
                      "type": "task|form|automated_action|condition",
                      "assignee_type": "role|null",
                      "assignee_role_id": "integer|null",
                      "assignee_role_name": "string|null",
                      "config": {},
                      "sla_hours": "integer|null",
                      "is_start": true|false,
                      "is_end": true|false
                    }
                  ]
                }
              ],
              "transitions": [
                {
                  "from_tmp_id": "string",
                  "to_tmp_id": "string",
                  "condition_type": "always|expression",
                  "condition_expression": "objeto ou null",
                  "label": "string ou null"
                }
              ]
            }

            Se as instruções do usuário forem completamente inválidas ou ofensivas/sem relação com o processo, responda:
            {"is_workflow_description": false, "refusal_reason": "Motivo..."}

            Responda em português do Brasil, apenas com o JSON solicitado, sem blocos Markdown ao redor.
            PROMPT;
    }
}
