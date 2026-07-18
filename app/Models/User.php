<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'azure_id', 'avatar_url'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user');
    }

    /**
     * MVP (00-visao-geral.md §6): qualquer usuário com pelo menos uma organização acessa
     * o painel; `platform-staff` acessa mesmo sem organização (suporte/operação). Permissões
     * mais finas (workflow-admin/editor/viewer por recurso) chegam a partir da Fase 1, junto
     * com os models que protegem.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('platform-staff') || $this->organizations()->exists();
    }

    public function isPlatformStaff(): bool
    {
        return $this->hasRole('platform-staff');
    }

    /**
     * `workflow-admin`/`workflow-editor`/`workflow-viewer` são provisionados por organização
     * (`OrganizationObserver`, `organization_id` como team do spatie/laravel-permission) —
     * como o contexto de team default da aplicação é `0` (`AppServiceProvider::boot()`, ainda
     * sem um "trocador de organização" no MVP), checar um papel escopado a uma organização
     * específica precisa trocar o team temporariamente, nunca confiar no team ativo da
     * sessão (00-visao-geral.md §9, item em aberto).
     */
    public function hasOrganizationRole(int $organizationId, string $role): bool
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId($organizationId);
        $this->unsetRelation('roles');

        $has = $this->hasRole($role);

        $registrar->setPermissionsTeamId($previousTeamId);
        $this->unsetRelation('roles');

        return $has;
    }
}
