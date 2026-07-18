<?php

namespace App\Enums;

/**
 * 01-modelo-de-dados.md §3.1.
 */
enum ProcessInstanceStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
