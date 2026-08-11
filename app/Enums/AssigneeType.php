<?php

namespace App\Enums;

/**
 * Nullable no schema (01-modelo-de-dados.md §2.4) — só usado quando a atividade tem
 * responsável humano (`task`/`form`); `automated_action`/`condition` não têm este campo
 * preenchido.
 *
 * `External` marca uma atividade humana que nunca é de um usuário do Aresta (ex.: o candidato
 * que aceita/recusa uma proposta) — só completável via API pública, nunca pela Inbox
 * (`ProcessInstanceActivityPolicy`), e sem checagem de papel/usuário no
 * `WorkflowGraphValidator`.
 */
enum AssigneeType: string
{
    case Role = 'role';
    case User = 'user';
    case External = 'external';
}
