<?php

namespace App\Filament\Resources\Roles\RelationManagers;

use App\Models\Role;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quem pertence a este papel (`role_user`, 01-modelo-de-dados.md §2.6) — elegibilidade
 * resolvida ao vivo por essa composição sempre que alguém tenta assumir uma atividade em fila
 * (§2.6.1, item 3). Anexar só permite usuários da MESMA organização do papel — mesma regra
 * que a spec já exige da aplicação pra `role_user` (organização não tem coluna própria na
 * pivô, então a validação precisa acontecer aqui).
 */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Membros';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            // Sem isso, o Filament adivinha a relação inversa em User como `roles()` — que é
            // do Spatie (RBAC), não a `role_user` de papel de negócio (ver User::domainRoles).
            ->inverseRelationship('domainRoles')
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelectOptionsQuery(function (Builder $query) {
                        /** @var Role $role */
                        $role = $this->getOwnerRecord();

                        return $query->whereHas(
                            'organizations',
                            fn (Builder $q) => $q->where('organizations.id', $role->organization_id),
                        );
                    })
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
            ]);
    }
}
