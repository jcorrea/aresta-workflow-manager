<?php

namespace App\Models;

use App\Enums\AssigneeType;
use App\Enums\ConditionType;
use App\Enums\WorkflowActivityType;
use App\Models\Scopes\WorkflowActivityOrganizationScope;
use Database\Factories\WorkflowActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * O nó de fato do grafo (01-modelo-de-dados.md §2.4). `is_end` substitui o magic number
 * `idprocessdestiny === 4` do legado por uma flag explícita nomeada.
 *
 * `is_and_join` não é `$fillable` de propósito: nunca é desenhado pelo usuário, é calculado por
 * `WorkflowGraphValidator::validAndJoinIds()` e persistido só na publicação
 * (`WorkflowVersionController::publish()`) — ver `02-motor-de-execucao.md` §3.3.1.
 */
class WorkflowActivity extends Model
{
    /** @use HasFactory<WorkflowActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'workflow_step_id',
        'name',
        'type',
        'assignee_type',
        'assignee_role_id',
        'assignee_user_id',
        'config',
        'sla_hours',
        'position_x',
        'position_y',
        'is_start',
        'is_end',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new WorkflowActivityOrganizationScope);
    }

    protected function casts(): array
    {
        return [
            'type' => WorkflowActivityType::class,
            'assignee_type' => AssigneeType::class,
            'config' => 'array',
            'is_start' => 'boolean',
            'is_end' => 'boolean',
            'is_and_join' => 'boolean',
        ];
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function assigneeRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'assignee_role_id');
    }

    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function outgoingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'from_activity_id');
    }

    public function incomingTransitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class, 'to_activity_id');
    }

    /**
     * `config.fields` de um nó `form`, com `options` anexado nos campos de texto que uma
     * transição de saída testa por igualdade (ex.: um campo `decisao` seguido de duas
     * transições `context.decisao = "aprovado"` / `"reprovado"`) — permite a tela de conclusão
     * trocar o `<input>` livre por botões/select com os valores que o desenho já define,
     * em vez do usuário ter que adivinhar/digitar o valor esperado.
     */
    public function fieldsWithOptions(): array
    {
        if ($this->type !== WorkflowActivityType::Form) {
            return [];
        }

        $decisionOptions = $this->decisionOptionsByField();

        return collect($this->config['fields'] ?? [])
            ->map(function (array $field) use ($decisionOptions) {
                if (($field['type'] ?? 'text') === 'text' && isset($decisionOptions[$field['key']])) {
                    $field['options'] = $decisionOptions[$field['key']];
                }

                return $field;
            })
            ->all();
    }

    /**
     * Varre as transições `expression` que de fato decidem o próximo passo a partir daqui por
     * condições `field = value` (`context.*`/`result.*`, os únicos prefixos que
     * `ConditionEvaluator::resolveField()` resolve) e agrupa os valores literais por nome de
     * campo. Só cobre o caso flat que o builder visual produz hoje (`03-editor-visual.md` §8) —
     * composição `all`/`any` não é considerada.
     *
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    private function decisionOptionsByField(): array
    {
        $options = [];

        foreach ($this->expressionTransitionsAhead() as $transition) {
            $expression = $transition->condition_expression ?? [];
            $field = $expression['field'] ?? null;
            $operator = $expression['operator'] ?? null;
            $value = $expression['value'] ?? null;

            if (! is_string($field) || ! is_string($value) || ! in_array($operator, ['=', '=='], true)) {
                continue;
            }

            [$root, $key] = array_pad(explode('.', $field, 2), 2, null);

            if (! in_array($root, ['context', 'result'], true) || $key === null) {
                continue;
            }

            $options[$key] ??= [];

            if (! collect($options[$key])->contains('value', $value)) {
                $options[$key][] = ['value' => $value, 'label' => $transition->label ?: $value];
            }
        }

        return $options;
    }

    /**
     * As transições `expression` que decidem o próximo passo a partir deste nó: as dele mesmo,
     * ou, se ele só tem uma única transição `always` levando a um nó `condition` (o "losango"
     * que se autocompleta sozinho com `result = []` só ao ser alcançado —
     * `WorkflowEngine::activateActivity()` — e nunca tem `config.fields` próprio), as desse nó
     * `condition` (recursivo, para permitir uma cadeia de condições). É o padrão "atividade de
     * decisão no meio do grafo": um `form` alimenta `context`, e é o `condition` logo depois que
     * de fato ramifica. Não desce por mais de uma `always` de cada vez (isso seria um fork, não
     * uma decisão única) nem revisita um nó já visitado (grafo pode ter ciclo).
     *
     * @param  list<int>  $visited
     * @return Collection<int, WorkflowTransition>
     */
    private function expressionTransitionsAhead(array $visited = []): Collection
    {
        if (in_array($this->id, $visited, true)) {
            return collect();
        }

        $transitions = $this->outgoingTransitions;
        $expressionTransitions = $transitions->where('condition_type', ConditionType::Expression);

        if ($expressionTransitions->isNotEmpty()) {
            return $expressionTransitions;
        }

        $alwaysTransitions = $transitions->where('condition_type', ConditionType::Always);

        if ($alwaysTransitions->count() !== 1) {
            return collect();
        }

        $next = $alwaysTransitions->first()->toActivity;

        if (! $next || $next->type !== WorkflowActivityType::Condition) {
            return collect();
        }

        return $next->expressionTransitionsAhead([...$visited, $this->id]);
    }
}
