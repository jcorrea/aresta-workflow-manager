<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Models\Role;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Mesmo guard de RolesTable — acessar /edit direto pela URL não pode contornar a
            // regra de "não excluir papel em uso" (01-modelo-de-dados.md §2.6.1).
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
        ];
    }
}
