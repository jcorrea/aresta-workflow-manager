<?php

namespace App\Enums;

/**
 * Só dois status existem — uma versão publicada nunca muda de status de novo (não existe
 * "arquivada"); "vigente" é só o ponteiro `workflows.current_published_version_id`
 * (01-modelo-de-dados.md §2.2, decisão fechada em 00-visao-geral.md §2.16).
 */
enum WorkflowVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
