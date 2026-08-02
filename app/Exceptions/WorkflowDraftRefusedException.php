<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Cobre os três motivos pelos quais `AiWorkflowDraftGenerator::generate()` não consegue
 * produzir um rascunho: a IA classificou a descrição como não sendo um processo de trabalho
 * (`is_workflow_description: false`), a resposta veio malformada/incompleta, ou a chamada
 * HTTP falhou nos dois provedores. `WorkflowController::store()` trata os três da mesma forma
 * — vira `ValidationException` no campo `description`, nada é criado.
 */
class WorkflowDraftRefusedException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
