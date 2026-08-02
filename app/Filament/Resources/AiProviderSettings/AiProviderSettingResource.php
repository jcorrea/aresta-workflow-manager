<?php

namespace App\Filament\Resources\AiProviderSettings;

use App\Filament\Resources\AiProviderSettings\Pages\EditAiProviderSetting;
use App\Filament\Resources\AiProviderSettings\Pages\ListAiProviderSettings;
use App\Filament\Resources\AiProviderSettings\Schemas\AiProviderSettingForm;
use App\Filament\Resources\AiProviderSettings\Tables\AiProviderSettingsTable;
use App\Models\AiProviderSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AiProviderSettingResource extends Resource
{
    protected static ?string $model = AiProviderSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $navigationLabel = 'Configurações de IA';

    protected static ?string $modelLabel = 'Provedor de IA';

    protected static ?string $pluralModelLabel = 'Configurações de IA';

    public static function form(Schema $schema): Schema
    {
        return AiProviderSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiProviderSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiProviderSettings::route('/'),
            'edit' => EditAiProviderSetting::route('/{record}/edit'),
        ];
    }
}
