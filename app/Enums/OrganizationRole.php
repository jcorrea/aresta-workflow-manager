<?php

namespace App\Enums;

/**
 * Papéis RBAC escopados por organização (00-visao-geral.md §6, item 2 /
 * 04-integracao-e-notificacoes.md §2.1) — via o recurso de teams do
 * spatie/laravel-permission, `organization_id` como team key. Distinto do `platform-staff`
 * (global, sem organização) e do `roles` de domínio (papel de negócio, não RBAC).
 */
enum OrganizationRole: string
{
    case Admin = 'workflow-admin';
    case Editor = 'workflow-editor';
    case Viewer = 'workflow-viewer';
}
