<?php

namespace App\Enums;

/**
 * Nullable no schema (01-modelo-de-dados.md §2.4) — só usado quando a atividade tem
 * responsável humano (`task`/`form`); `automated_action`/`condition` não têm este campo
 * preenchido.
 */
enum AssigneeType: string
{
    case Role = 'role';
    case User = 'user';
}
