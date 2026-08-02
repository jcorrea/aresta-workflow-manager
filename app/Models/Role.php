<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Papel de negócio (ex.: "Aprovador Financeiro", 01-modelo-de-dados.md §2.6) — distinto de
 * `Spatie\Permission\Models\Role` (RBAC administrativo, tabela `permission_roles`). Mutável
 * a qualquer momento (renomear é livre, sem snapshot), mas nunca apagado fisicamente quando
 * referenciado — ver `active` e `RolePolicy::delete` (§2.6.1).
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * Ponto de partida editável pra organização nova (OrganizationObserver) e pro comando
     * app:seed-default-domain-roles (organização já existente) — mesma lista nos dois
     * lugares, não uma cópia solta em cada um.
     */
    public const DEFAULT_NAMES = ['Solicitante', 'Gestor', 'Aprovador', 'Financeiro', 'RH', 'Diretoria'];

    protected $fillable = ['organization_id', 'name', 'active'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    public function workflowActivities(): HasMany
    {
        return $this->hasMany(WorkflowActivity::class, 'assignee_role_id');
    }

    /**
     * Camada de aplicação da regra "nunca excluir fisicamente um role referenciado"
     * (01-modelo-de-dados.md §2.6.1, item 2) — dá o erro claro antes de a constraint do banco
     * (`assignee_role_id` com `restrictOnDelete()`) sequer ser testada. A tela (Filament
     * `EditRole`) já esconde a ação de excluir nesse caso; isto aqui é a rede de segurança
     * pra qualquer outro caminho de exclusão (tinker, comando futuro, etc.).
     */
    protected static function booted(): void
    {
        static::deleting(function (self $role): void {
            if ($role->workflowActivities()->exists()) {
                throw new \RuntimeException(
                    "O papel \"{$role->name}\" está em uso por pelo menos uma atividade de workflow — desative em vez de excluir.",
                );
            }
        });
    }
}
