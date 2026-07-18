<?php

namespace App\Enums;

/**
 * `Error` bloqueia `draft → published`; `Warning` só sinaliza no editor (ex.: nó sem conexão
 * de entrada, "pode ser um nó em construção") — 03-editor-visual.md §6.
 */
enum GraphIssueSeverity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
