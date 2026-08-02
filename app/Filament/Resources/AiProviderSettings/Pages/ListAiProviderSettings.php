<?php

namespace App\Filament\Resources\AiProviderSettings\Pages;

use App\Filament\Resources\AiProviderSettings\AiProviderSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListAiProviderSettings extends ListRecords
{
    protected static string $resource = AiProviderSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
