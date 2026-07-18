<?php

namespace App\Enums;

/**
 * Um único tipo de nó "atividade" cobre os 4 casos (01-modelo-de-dados.md §4 /
 * 00-visao-geral.md §2 item 6) — o campo `config` (JSON) muda de formato conforme o valor
 * deste enum.
 */
enum WorkflowActivityType: string
{
    case Task = 'task';
    case AutomatedAction = 'automated_action';
    case Condition = 'condition';
    case Form = 'form';
}
