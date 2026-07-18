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
}
