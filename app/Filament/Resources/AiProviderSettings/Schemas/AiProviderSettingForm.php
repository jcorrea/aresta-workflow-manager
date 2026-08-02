<?php

namespace App\Filament\Resources\AiProviderSettings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AiProviderSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('provider')
                    ->label('Provedor')
                    ->disabled()
                    ->dehydrated(false)
                    ->maxLength(255),
                Toggle::make('enabled')
                    ->label('Habilitado')
                    ->default(true),
                TextInput::make('priority')
                    ->label('Prioridade (Ordem de tentativa)')
                    ->numeric()
                    ->required()
                    ->default(10)
                    ->helperText('Menor valor = maior prioridade. Provedores ativados são tentados nesta ordem.'),
                TextInput::make('model')
                    ->label('Modelo (Override)')
                    ->placeholder('Deixar em branco para usar o padrão do .env')
                    ->helperText('Exemplo para Gemini: gemini-2.5-flash ou gemini-2.5-pro')
                    ->maxLength(255),
            ]);
    }
}
