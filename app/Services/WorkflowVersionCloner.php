<?php

namespace App\Services;

use App\Enums\WorkflowVersionStatus;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowActivity;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;

/**
 * Clona step/activity/transition de uma `WorkflowVersion` para um novo `draft`
 * (01-modelo-de-dados.md §2.2.1, item 2: "editar de novo" sempre parte da versão
 * atualmente publicada, não necessariamente a de maior `version_number`). Parametrizado por
 * `$targetWorkflow` porque a mesma mecânica serve, no futuro, para clonagem entre
 * organizações (§7, item em aberto) — só quem chama muda, não implementado ainda.
 *
 * Limitação conhecida para esse futuro uso entre organizações: `assignee_role_id` é copiado
 * como está, mas um `Role` só existe na organização de origem — clonar para outra organização
 * exigiria remapear ou limpar essa referência, não implementado aqui porque só o caminho
 * "editar de novo" (mesma organização) está em uso.
 */
class WorkflowVersionCloner
{
    public function cloneAsDraft(WorkflowVersion $source, Workflow $targetWorkflow, User $createdBy): WorkflowVersion
    {
        return DB::transaction(function () use ($source, $targetWorkflow, $createdBy) {
            $source->loadMissing(['steps.activities', 'transitions']);

            $newVersion = WorkflowVersion::create([
                'workflow_id' => $targetWorkflow->id,
                'status' => WorkflowVersionStatus::Draft,
                'canvas_json' => $source->canvas_json,
                'created_by' => $createdBy->id,
                'draft_lock_workflow_id' => $targetWorkflow->id,
            ]);

            $activityIdMap = [];

            foreach ($source->steps as $step) {
                $newStep = WorkflowStep::create([
                    'workflow_version_id' => $newVersion->id,
                    'name' => $step->name,
                    'sla_days' => $step->sla_days,
                    'position_x' => $step->position_x,
                    'position_y' => $step->position_y,
                    'width' => $step->width,
                    'height' => $step->height,
                    'sort_order' => $step->sort_order,
                ]);

                foreach ($step->activities as $activity) {
                    $newActivity = WorkflowActivity::create([
                        'workflow_step_id' => $newStep->id,
                        'name' => $activity->name,
                        'type' => $activity->type,
                        'assignee_type' => $activity->assignee_type,
                        'assignee_role_id' => $activity->assignee_role_id,
                        'assignee_user_id' => $activity->assignee_user_id,
                        'config' => $activity->config,
                        'sla_hours' => $activity->sla_hours,
                        'position_x' => $activity->position_x,
                        'position_y' => $activity->position_y,
                        'is_start' => $activity->is_start,
                        'is_end' => $activity->is_end,
                    ]);

                    $activityIdMap[$activity->id] = $newActivity->id;
                }
            }

            foreach ($source->transitions as $transition) {
                WorkflowTransition::create([
                    'workflow_version_id' => $newVersion->id,
                    'from_activity_id' => $activityIdMap[$transition->from_activity_id],
                    'to_activity_id' => $activityIdMap[$transition->to_activity_id],
                    'condition_type' => $transition->condition_type,
                    'condition_expression' => $transition->condition_expression,
                    'label' => $transition->label,
                    'sort_order' => $transition->sort_order,
                ]);
            }

            return $newVersion;
        });
    }
}
