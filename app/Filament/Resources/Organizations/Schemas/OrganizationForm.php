<?php

namespace App\Filament\Resources\Organizations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),
                TextInput::make('external_code')
                    ->label('Código externo')
                    ->helperText('Identificador do mesmo cliente em outros sistemas GIITS, se houver.')
                    ->maxLength(255),
                Toggle::make('active')
                    ->label('Ativa')
                    ->default(true),
            ]);
    }
}
