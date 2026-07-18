<?php

namespace App\Enums;

/**
 * `Active` = tem >=1 atividade em andamento nele (01-modelo-de-dados.md §3.2).
 */
enum ProcessInstanceStepStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
}
