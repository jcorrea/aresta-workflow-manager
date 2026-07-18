<?php

namespace App\Services;

/**
 * Avalia `workflow_transitions.condition_expression` (02-motor-de-execucao.md §4) — JSON
 * estruturado (`field`/`operator`/`value`, composto via `all`/`any`), nunca uma linguagem de
 * expressão textual/`eval`: quem desenha o processo é administrador de negócio, não
 * desenvolvedor (CLAUDE.md, "Condição sem eval/expression-language").
 */
class ConditionEvaluator
{
    /**
     * @param  array<string, mixed>  $expression
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $result
     */
    public function evaluate(array $expression, array $context, array $result): bool
    {
        if (isset($expression['all'])) {
            foreach ($expression['all'] as $sub) {
                if (! $this->evaluate($sub, $context, $result)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($expression['any'])) {
            foreach ($expression['any'] as $sub) {
                if ($this->evaluate($sub, $context, $result)) {
                    return true;
                }
            }

            return false;
        }

        $field = $expression['field'] ?? null;
        $operator = $expression['operator'] ?? null;

        if ($field === null || $operator === null) {
            return false;
        }

        $actual = $this->resolveField($field, $context, $result);
        $expected = $expression['value'] ?? null;

        return match ($operator) {
            '=', '==' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
            default => false,
        };
    }

    /**
     * `field` sempre referencia um caminho dentro de `context` (variáveis do processo) ou
     * `result` (saída da atividade anterior), nunca código arbitrário (§4).
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $result
     */
    private function resolveField(string $field, array $context, array $result): mixed
    {
        [$root, $path] = array_pad(explode('.', $field, 2), 2, null);

        $source = match ($root) {
            'context' => $context,
            'result' => $result,
            default => [],
        };

        return data_get($source, $path);
    }
}
