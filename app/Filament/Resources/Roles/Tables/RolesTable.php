<?php

namespace App\Filament\Resources\Roles\Tables;

use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class RolesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('organization.name')
                    ->label('Organização')
                    ->sortable()
                    ->searchable()
                    ->visible(fn () => Auth::user()->isPlatformStaff()),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('Membros')
                    ->counts('users')
                    ->badge(),
                TextColumn::make('workflow_activities_count')
                    ->label('Em uso')
                    ->counts('workflowActivities')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "{$state} atividade(s)" : 'Não'),
                ToggleColumn::make('active')
                    ->label('Ativo'),
                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Role $record): bool => $record->workflowActivities()->doesntExist())
                    ->before(function (Role $record, DeleteAction $action): void {
                        if ($record->workflowActivities()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Este papel está em uso')
                                ->body('Desative em vez de excluir — está referenciado por pelo menos uma atividade de workflow.')
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }
}
