<?php

namespace App\Enums;

/**
 * Log de publish/rollback (01-modelo-de-dados.md §2.2.2) — substitui a necessidade de um
 * status "archived" preservando o histórico de quem publicou/reverteu o quê e quando.
 */
enum ActivationAction: string
{
    case Publish = 'publish';
    case Rollback = 'rollback';
}
