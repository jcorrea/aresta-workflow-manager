<?php

namespace Tests\Unit;

use App\Services\ConditionEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 02-motor-de-execucao.md §4 — JSON estruturado (field/operator/value, all/any), sem
 * eval/expression-language. Unitário puro (sem banco): não depende de nenhum model.
 */
class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = new ConditionEvaluator;
    }

    public function test_evaluates_a_simple_field_operator_value_expression(): void
    {
        $expression = ['field' => 'context.valor_proposta', 'operator' => '>', 'value' => 50000];

        $this->assertTrue($this->evaluator->evaluate($expression, ['valor_proposta' => 100000], []));
        $this->assertFalse($this->evaluator->evaluate($expression, ['valor_proposta' => 1000], []));
    }

    public function test_reads_from_result_instead_of_context(): void
    {
        $expression = ['field' => 'result.aprovado', 'operator' => '=', 'value' => true];

        $this->assertTrue($this->evaluator->evaluate($expression, [], ['aprovado' => true]));
        $this->assertFalse($this->evaluator->evaluate($expression, [], ['aprovado' => false]));
    }

    public function test_supports_nested_field_paths(): void
    {
        $expression = ['field' => 'context.cliente.tipo', 'operator' => '=', 'value' => 'vip'];

        $this->assertTrue($this->evaluator->evaluate($expression, ['cliente' => ['tipo' => 'vip']], []));
    }

    /**
     * @return array<string, array{0: string, 1: mixed, 2: mixed, 3: bool}>
     */
    public static function operatorProvider(): array
    {
        return [
            '=' => ['=', 10, 10, true],
            '!= verdadeiro' => ['!=', 10, 20, true],
            '!= falso' => ['!=', 10, 10, false],
            '>=' => ['>=', 10, 10, true],
            '<' => ['<', 5, 10, true],
            '<=' => ['<=', 10, 10, true],
        ];
    }

    #[DataProvider('operatorProvider')]
    public function test_supports_all_documented_operators(string $operator, mixed $actual, mixed $expected, bool $shouldMatch): void
    {
        $expression = ['field' => 'context.v', 'operator' => $operator, 'value' => $expected];

        $this->assertSame($shouldMatch, $this->evaluator->evaluate($expression, ['v' => $actual], []));
    }

    public function test_all_requires_every_sub_expression_to_match(): void
    {
        $expression = [
            'all' => [
                ['field' => 'context.valor', 'operator' => '>', 'value' => 1000],
                ['field' => 'context.cliente_vip', 'operator' => '=', 'value' => true],
            ],
        ];

        $this->assertTrue($this->evaluator->evaluate($expression, ['valor' => 2000, 'cliente_vip' => true], []));
        $this->assertFalse($this->evaluator->evaluate($expression, ['valor' => 2000, 'cliente_vip' => false], []));
        $this->assertFalse($this->evaluator->evaluate($expression, ['valor' => 500, 'cliente_vip' => true], []));
    }

    public function test_any_requires_at_least_one_sub_expression_to_match(): void
    {
        $expression = [
            'any' => [
                ['field' => 'result.aprovado', 'operator' => '=', 'value' => true],
                ['field' => 'context.cliente_vip', 'operator' => '=', 'value' => true],
            ],
        ];

        $this->assertTrue($this->evaluator->evaluate($expression, ['cliente_vip' => true], ['aprovado' => false]));
        $this->assertTrue($this->evaluator->evaluate($expression, ['cliente_vip' => false], ['aprovado' => true]));
        $this->assertFalse($this->evaluator->evaluate($expression, ['cliente_vip' => false], ['aprovado' => false]));
    }

    public function test_unknown_operator_or_missing_field_does_not_match(): void
    {
        $this->assertFalse($this->evaluator->evaluate(
            ['field' => 'context.v', 'operator' => 'contains', 'value' => 1],
            ['v' => 1],
            [],
        ));

        $this->assertFalse($this->evaluator->evaluate([], ['v' => 1], []));
    }
}
