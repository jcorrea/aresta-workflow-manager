<?php

namespace App\Services;

use App\Enums\GraphIssueSeverity;

/**
 * Um problema encontrado por `WorkflowGraphValidator` — pensado para alimentar o painel "N
 * problemas encontrados" do editor visual (03-editor-visual.md §6), por isso carrega os ids
 * dos nós envolvidos (não só uma mensagem genérica) para o editor conseguir destacá-los.
 */
final class GraphIssue
{
    /**
     * @param  array<int, int>  $activityIds
     */
    public function __construct(
        public readonly string $code,
        public readonly GraphIssueSeverity $severity,
        public readonly string $message,
        public readonly array $activityIds = [],
    ) {}
}
