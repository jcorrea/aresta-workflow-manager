<?php

namespace App\Enums;

/**
 * `Always` = aresta incondicional (fork paralelo); `Expression` é avaliada em ordem
 * (`sort_order`), primeira que bater vence — semântica XOR (01-modelo-de-dados.md §2.5).
 */
enum ConditionType: string
{
    case Always = 'always';
    case Expression = 'expression';
}
