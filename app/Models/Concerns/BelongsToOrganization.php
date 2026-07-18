<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aplicada a todo model organizacional (`Workflow`, `ProcessInstance`, `Role` de domínio,
 * a partir da Fase 1) — reforça o isolamento entre organizações via global scope, camada
 * 1 do mecanismo de 3 camadas descrito em 01-modelo-de-dados.md §5.3. Não substitui a
 * policy (camada 2): o scope garante "não aparece na lista", a policy garante "não é
 * possível acessar diretamente pela URL/ID mesmo sabendo que existe".
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
