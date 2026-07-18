<?php

namespace App\Services;

use App\Enums\AssigneeType;
use App\Enums\ConditionType;
use App\Enums\GraphIssueSeverity;
use App\Enums\WorkflowActivityType;
use App\Models\WorkflowActivity;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use Illuminate\Support\Collection;

/**
 * Valida se uma `WorkflowVersion` pode ser publicada (03-editor-visual.md §6). A regra mais
 * pesada — pareamento fork/join — é uma análise de pós-dominância (02-motor-de-execucao.md
 * §3.3.1): sem ela, o AND-join "ingênuo" do `WorkflowEngine` pode travar uma instância em
 * produção esperando uma chegada que nunca acontece. Bloquear isso aqui, na publicação, é o
 * que permite o motor de execução ser trivial em runtime — ver `WorkflowEngine::advance()`.
 *
 * Simplificação assumida (documentada, não escondida): o pareamento fork/join valida
 * dominador/pós-dominador + contagem exata de ramos + contenção dos predecessores diretos do
 * join dentro do bloco. Não faz a verificação exaustiva de "nenhuma aresta escapa do bloco em
 * nenhum ponto do caminho" — cobre o caso motivador da spec (decisão que não reconverge antes
 * do join externo) e vazamentos diretos no join, mas um vazamento a vários saltos de distância
 * dentro do bloco, sem nunca tocar o join diretamente por uma aresta espúria, não é detectado.
 */
class WorkflowGraphValidator
{
    private const START = 0;

    private const EXIT = -1;

    /**
     * @return Collection<int, GraphIssue>
     */
    public function validate(WorkflowVersion $version): Collection
    {
        $activities = WorkflowActivity::query()
            ->whereHas('workflowStep', fn ($query) => $query->where('workflow_version_id', $version->id))
            ->with('assigneeRole')
            ->get()
            ->keyBy('id');

        $transitions = WorkflowTransition::query()
            ->where('workflow_version_id', $version->id)
            ->get();

        $successors = $transitions->groupBy('from_activity_id');
        $predecessors = $transitions->groupBy('to_activity_id');

        return collect()
            ->merge($this->validateStartAndEnd($activities, $successors))
            ->merge($this->validateConditionNodesHaveOutgoing($activities, $successors))
            ->merge($this->validateAssignees($activities))
            ->merge($this->validateUnreachableNodes($activities, $predecessors))
            ->merge($this->validateForkJoinPairing($activities, $successors, $predecessors));
    }

    public function isPublishable(WorkflowVersion $version): bool
    {
        return $this->validate($version)->doesntContain(
            fn (GraphIssue $issue) => $issue->severity === GraphIssueSeverity::Error,
        );
    }

    /**
     * @param  Collection<int, WorkflowActivity>  $activities
     * @param  Collection<int|string, Collection<int, WorkflowTransition>>  $successors
     * @return Collection<int, GraphIssue>
     */
    private function validateStartAndEnd(Collection $activities, Collection $successors): Collection
    {
        $issues = collect();

        $startIds = $activities->filter(fn (WorkflowActivity $a) => $a->is_start)->keys();
        $endIds = $activities->filter(fn (WorkflowActivity $a) => $a->is_end)->keys();

        if ($startIds->isEmpty()) {
            $issues->push(new GraphIssue(
                'missing_start',
                GraphIssueSeverity::Error,
                'A versão precisa de pelo menos um nó inicial (is_start).',
            ));
        }

        if ($endIds->isEmpty()) {
            $issues->push(new GraphIssue(
                'missing_end',
                GraphIssueSeverity::Error,
                'A versão precisa de pelo menos um nó final (is_end).',
            ));
        }

        foreach ($startIds as $startId) {
            if ($endIds->isNotEmpty() && ! $this->canReachAny($startId, $endIds->all(), $successors)) {
                $issues->push(new GraphIssue(
                    'start_cannot_reach_end',
                    GraphIssueSeverity::Error,
                    "O nó inicial #{$startId} não alcança nenhum nó final.",
                    [$startId],
                ));
            }
        }

        return $issues;
    }

    /**
     * @param  Collection<int|string, Collection<int, WorkflowTransition>>  $successors
     * @param  array<int, int>  $targets
     */
    private function canReachAny(int $from, array $targets, Collection $successors): bool
    {
        $visited = [];
        $stack = [$from];

        while ($stack) {
            $node = array_pop($stack);

            if (in_array($node, $targets, true)) {
                return true;
            }

            if (isset($visited[$node])) {
                continue;
            }
            $visited[$node] = true;

            foreach ($successors->get($node, collect()) as $transition) {
                $stack[] = $transition->to_activity_id;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, WorkflowActivity>  $activities
     * @param  Collection<int|string, Collection<int, WorkflowTransition>>  $successors
     * @return Collection<int, GraphIssue>
     */
    private function validateConditionNodesHaveOutgoing(Collection $activities, Collection $successors): Collection
    {
        return $activities
            ->filter(fn (WorkflowActivity $a) => $a->type === WorkflowActivityType::Condition)
            ->filter(fn (WorkflowActivity $a) => $successors->get($a->id, collect())->isEmpty())
            ->map(fn (WorkflowActivity $a) => new GraphIssue(
                'condition_without_outgoing',
                GraphIssueSeverity::Error,
                "O nó de decisão #{$a->id} não tem nenhuma transição de saída — o motor prenderia a instância ali.",
                [$a->id],
            ))
            ->values();
    }

    /**
     * @param  Collection<int, WorkflowActivity>  $activities
     * @return Collection<int, GraphIssue>
     */
    private function validateAssignees(Collection $activities): Collection
    {
        $issues = collect();

        foreach ($activities->whereIn('type', [WorkflowActivityType::Task, WorkflowActivityType::Form]) as $activity) {
            if ($activity->assignee_type === null) {
                $issues->push(new GraphIssue(
                    'missing_assignee',
                    GraphIssueSeverity::Error,
                    "A atividade #{$activity->id} não tem responsável definido.",
                    [$activity->id],
                ));

                continue;
            }

            if ($activity->assignee_type === AssigneeType::Role && ! $activity->assigneeRole?->active) {
                $issues->push(new GraphIssue(
                    'inactive_assignee_role',
                    GraphIssueSeverity::Error,
                    "A atividade #{$activity->id} está atribuída a um papel desativado.",
                    [$activity->id],
                ));
            }
        }

        return $issues;
    }

    /**
     * @param  Collection<int, WorkflowActivity>  $activities
     * @param  Collection<int|string, Collection<int, WorkflowTransition>>  $predecessors
     * @return Collection<int, GraphIssue>
     */
    private function validateUnreachableNodes(Collection $activities, Collection $predecessors): Collection
    {
        return $activities
            ->filter(fn (WorkflowActivity $a) => ! $a->is_start && $predecessors->get($a->id, collect())->isEmpty())
            ->map(fn (WorkflowActivity $a) => new GraphIssue(
                'unreachable_node',
                GraphIssueSeverity::Warning,
                "O nó #{$a->id} não tem nenhuma conexão de entrada — pode estar em construção.",
                [$a->id],
            ))
            ->values();
    }

    /**
     * @param  Collection<int, WorkflowActivity>  $activities
     * @param  Collection<int|string, Collection<int, WorkflowTransition>>  $successors
     * @param  Collection<int|string, Collection<int, WorkflowTransition>>  $predecessors
     * @return Collection<int, GraphIssue>
     */
    private function validateForkJoinPairing(Collection $activities, Collection $successors, Collection $predecessors): Collection
    {
        $issues = collect();

        $startIds = $activities->filter(fn (WorkflowActivity $a) => $a->is_start)->keys()->all();
        $sinkIds = $activities->filter(fn (WorkflowActivity $a) => $successors->get($a->id, collect())->isEmpty())->keys()->all();

        $forwardIdom = $this->computeDominators(
            self::START,
            fn ($node) => $node === self::START ? $startIds : $successors->get($node, collect())->pluck('to_activity_id')->all(),
            fn ($node) => $this->forwardPredecessorsOf($node, $predecessors, $startIds),
        );

        $postIdom = $this->computeDominators(
            self::EXIT,
            fn ($node) => $node === self::EXIT ? $sinkIds : $predecessors->get($node, collect())->pluck('from_activity_id')->all(),
            fn ($node) => $this->reversedPredecessorsOf($node, $successors, $sinkIds),
        );

        $forks = $activities->filter(
            fn (WorkflowActivity $a) => $successors->get($a->id, collect())->where('condition_type', ConditionType::Always)->count() >= 2,
        );

        foreach ($forks as $fork) {
            $alwaysBranches = $successors->get($fork->id, collect())->where('condition_type', ConditionType::Always);

            if (! isset($postIdom[$fork->id])) {
                $issues->push(new GraphIssue(
                    'fork_without_postdominator',
                    GraphIssueSeverity::Error,
                    "O fork #{$fork->id} não alcança nenhum fim do processo de forma bem formada.",
                    [$fork->id],
                ));

                continue;
            }

            $joinId = $postIdom[$fork->id];

            if ($joinId === self::EXIT) {
                $issues->push(new GraphIssue(
                    'fork_without_join',
                    GraphIssueSeverity::Error,
                    "O fork #{$fork->id} não tem um nó de junção correspondente — os ramos convergem só no fim do processo.",
                    [$fork->id],
                ));

                continue;
            }

            $joinIncoming = $predecessors->get($joinId, collect());

            if ($joinIncoming->count() < 2) {
                $issues->push(new GraphIssue(
                    'fork_postdominator_not_a_join',
                    GraphIssueSeverity::Error,
                    "O pós-dominador do fork #{$fork->id} (nó #{$joinId}) não é um nó de junção.",
                    [$fork->id, $joinId],
                ));

                continue;
            }

            if ($joinIncoming->count() !== $alwaysBranches->count()) {
                $issues->push(new GraphIssue(
                    'fork_join_branch_count_mismatch',
                    GraphIssueSeverity::Error,
                    "O fork #{$fork->id} tem {$alwaysBranches->count()} ramo(s), mas o join #{$joinId} tem {$joinIncoming->count()} chegada(s) — não há correspondência de 1 para 1.",
                    [$fork->id, $joinId],
                ));

                continue;
            }

            foreach ($joinIncoming as $incoming) {
                $predecessorId = $incoming->from_activity_id;

                $containedForward = $this->dominates($fork->id, $predecessorId, $forwardIdom);
                $containedBackward = $this->dominates($joinId, $predecessorId, $postIdom);

                if (! $containedForward || ! $containedBackward) {
                    $issues->push(new GraphIssue(
                        'fork_join_leaks_outside_block',
                        GraphIssueSeverity::Error,
                        "Um caminho entre o fork #{$fork->id} e o join #{$joinId} passa por fora do bloco (nó #{$predecessorId}).",
                        [$fork->id, $joinId, $predecessorId],
                    ));

                    break;
                }
            }
        }

        return $issues;
    }

    /**
     * @param  Collection<int|string, Collection<int, WorkflowTransition>>  $predecessors
     * @param  array<int, int>  $startIds
     * @return array<int, int>
     */
    private function forwardPredecessorsOf(int $node, Collection $predecessors, array $startIds): array
    {
        $preds = $predecessors->get($node, collect())->pluck('from_activity_id')->all();

        if (in_array($node, $startIds, true)) {
            $preds[] = self::START;
        }

        return $preds;
    }

    /**
     * @param  Collection<int|string, Collection<int, WorkflowTransition>>  $successors
     * @param  array<int, int>  $sinkIds
     * @return array<int, int>
     */
    private function reversedPredecessorsOf(int $node, Collection $successors, array $sinkIds): array
    {
        $preds = $successors->get($node, collect())->pluck('to_activity_id')->all();

        if (in_array($node, $sinkIds, true)) {
            $preds[] = self::EXIT;
        }

        return $preds;
    }

    /**
     * Algoritmo de Cooper, Harvey & Kennedy ("A Simple, Fast Dominance Algorithm") — itera
     * sobre reverse-postorder até convergir, sem precisar da formulação clássica de
     * Lengauer-Tarjan para os tamanhos de grafo esperados aqui (dezenas de nós por processo).
     *
     * @param  callable(int): array<int, int>  $successorsFn
     * @param  callable(int): array<int, int>  $predecessorsFn
     * @return array<int, int> mapa nó => seu dominador imediato
     */
    private function computeDominators(int $root, callable $successorsFn, callable $predecessorsFn): array
    {
        $postorder = [];
        $visited = [];
        $this->dfsPostorder($root, $successorsFn, $visited, $postorder);

        $postorderIndex = array_flip($postorder);
        $idom = [$root => $root];

        $changed = true;
        while ($changed) {
            $changed = false;

            for ($i = count($postorder) - 2; $i >= 0; $i--) {
                $node = $postorder[$i];
                $preds = array_values(array_filter($predecessorsFn($node), fn ($p) => isset($idom[$p])));

                if (empty($preds)) {
                    continue;
                }

                $newIdom = array_shift($preds);
                foreach ($preds as $p) {
                    $newIdom = $this->intersect($newIdom, $p, $idom, $postorderIndex);
                }

                if (! isset($idom[$node]) || $idom[$node] !== $newIdom) {
                    $idom[$node] = $newIdom;
                    $changed = true;
                }
            }
        }

        return $idom;
    }

    /**
     * @param  callable(int): array<int, int>  $successorsFn
     * @param  array<int, bool>  $visited
     * @param  array<int, int>  $postorder
     */
    private function dfsPostorder(int $node, callable $successorsFn, array &$visited, array &$postorder): void
    {
        $visited[$node] = true;

        foreach ($successorsFn($node) as $successor) {
            if (! isset($visited[$successor])) {
                $this->dfsPostorder($successor, $successorsFn, $visited, $postorder);
            }
        }

        $postorder[] = $node;
    }

    /**
     * @param  array<int, int>  $idom
     * @param  array<int, int>  $postorderIndex
     */
    private function intersect(int $a, int $b, array $idom, array $postorderIndex): int
    {
        while ($a !== $b) {
            while ($postorderIndex[$a] < $postorderIndex[$b]) {
                $a = $idom[$a];
            }
            while ($postorderIndex[$b] < $postorderIndex[$a]) {
                $b = $idom[$b];
            }
        }

        return $a;
    }

    /**
     * `$a` domina/pós-domina `$b` se aparece na cadeia de dominadores imediatos de `$b` (ou é
     * o próprio `$b`).
     *
     * @param  array<int, int>  $idom
     */
    private function dominates(int $a, int $b, array $idom): bool
    {
        if (! isset($idom[$b])) {
            return false;
        }

        $current = $b;

        while (true) {
            if ($current === $a) {
                return true;
            }

            if (! isset($idom[$current]) || $idom[$current] === $current) {
                return $current === $a;
            }

            $current = $idom[$current];
        }
    }
}
