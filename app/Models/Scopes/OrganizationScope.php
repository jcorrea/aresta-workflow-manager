<?php

namespace App\Models\Scopes;

use App\Models\ExternalSystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Fronteira de isolamento entre organizações (01-modelo-de-dados.md §5.3, item 1):
 * um usuário só enxerga registros das organizações a que pertence. `platform-staff`
 * (ITS Group) atravessa essa fronteira para suporte/operação.
 */
class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Comandos artisan/seeders operam sem usuário autenticado e sem escopo de
        // organização — exceto durante os testes automatizados, que precisam exercitar
        // o isolamento de verdade mesmo rodando via CLI.
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        $user = Auth::user();

        if (! $user) {
            $builder->whereRaw('1 = 0');

            return;
        }

        // Sistema GIITS autenticado via Sanctum (04-integracao-e-notificacoes.md §5.4): não
        // é membro de nenhuma `organization_user`, mas é confiável para atuar em qualquer
        // organização ativa — a organização alvo é validada explicitamente no controller a
        // partir do payload, não por este scope.
        if ($user instanceof ExternalSystem) {
            return;
        }

        if ($user->hasRole('platform-staff')) {
            return;
        }

        $builder->whereIn(
            $model->qualifyColumn('organization_id'),
            $user->organizations()->pluck('organizations.id')
        );
    }
}
