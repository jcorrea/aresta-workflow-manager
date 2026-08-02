<?php

namespace App\Filament\Resources\AiProviderSettings\Tables;

use App\Models\AiProviderSetting;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiProviderSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority', 'asc')
            ->columns([
                TextColumn::make('priority')
                    ->label('Prioridade')
                    ->sortable(),
                TextColumn::make('provider')
                    ->label('Provedor')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'ollama' => 'Ollama (Local)',
                        'azure' => 'Azure OpenAI',
                        'gemini' => 'Google Gemini',
                        'openrouter' => 'OpenRouter',
                        'anthropic' => 'Anthropic (Claude)',
                        default => ucfirst($state),
                    })
                    ->searchable()
                    ->sortable(),
                IconColumn::make('enabled')
                    ->label('Habilitado')
                    ->boolean(),
                TextColumn::make('status_env')
                    ->label('Credencial no .env')
                    ->badge()
                    ->state(fn (AiProviderSetting $record): string => $record->isConfigured() ? 'Configurado' : 'Não configurado')
                    ->color(fn (AiProviderSetting $record): string => $record->isConfigured() ? 'success' : 'danger'),
                TextColumn::make('model')
                    ->label('Modelo Override')
                    ->placeholder('Padrão do .env')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
