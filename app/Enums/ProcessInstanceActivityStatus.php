<?php

namespace App\Enums;

/**
 * 01-modelo-de-dados.md §3.3.
 */
enum ProcessInstanceActivityStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
