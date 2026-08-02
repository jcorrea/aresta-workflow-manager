<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\Organization;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organization_id')
                    ->label('Organização')
                    ->options(fn () => Auth::user()->isPlatformStaff()
                        ? Organization::query()->orderBy('name')->pluck('name', 'id')
                        : Auth::user()->organizations()->orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => Auth::user()->organizations()->count() === 1
                        ? Auth::user()->organizations()->first()?->id
                        : null)
                    // Só dá pra escolher organização quando há mais de uma opção (platform-staff,
                    // ou usuário em várias organizações) — o caso comum (dono de uma organização
                    // só) nem mostra escolha, já vem preenchido. Nunca editável depois de criado —
                    // papel não migra de organização.
                    ->disabled(fn (string $operation) => $operation === 'edit'
                        || (! Auth::user()->isPlatformStaff() && Auth::user()->organizations()->count() <= 1))
                    ->dehydrated()
                    ->required()
                    ->native(false),
                TextInput::make('name')
                    ->label('Nome')
                    ->placeholder('Ex.: Aprovador Financeiro')
                    ->required()
                    ->maxLength(255)
                    ->unique(
                        table: 'roles',
                        column: 'name',
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule, $get) => $rule->where('organization_id', $get('organization_id')),
                    ),
                Toggle::make('active')
                    ->label('Ativo')
                    ->default(true)
                    ->helperText('Papéis inativos somem do seletor de responsável no editor visual para novas atividades, mas continuam valendo para o que já os referencia.'),
            ]);
    }
}
