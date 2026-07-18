<?php

namespace App\Exceptions;

use App\Models\Workflow;
use RuntimeException;

/**
 * `WorkflowEngine::start()` exige `workflow.current_published_version_id`
 * (02-motor-de-execucao.md §2, item 1) — não existe "iniciar a partir de um draft".
 */
class WorkflowNotPublishedException extends RuntimeException
{
    public function __construct(Workflow $workflow)
    {
        parent::__construct("Workflow #{$workflow->id} ({$workflow->name}) não tem versão publicada.");
    }
}
