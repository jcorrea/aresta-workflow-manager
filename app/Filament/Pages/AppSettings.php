<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Página singleton (não um Resource — não existe "criar outra configuração") pra editar
 * `AppSetting` (docs/specs/05-identidade-visual.md §2/§7.2, decisão revista em 2026-08-07).
 * Restrita a `platform-staff`: é config de instância inteira, não organizacional, mesmo
 * critério de `OrganizationPolicy`.
 */
class AppSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $navigationLabel = 'Identidade visual';

    protected static ?string $title = 'Identidade visual da aplicação';

    protected string $view = 'filament.pages.app-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isPlatformStaff() ?? false;
    }

    public function mount(): void
    {
        $setting = AppSetting::current();

        $this->form->fill([
            'app_name' => $setting->app_name,
            'primary_color' => $setting->resolvedPrimaryColor(),
            'logo_path' => $setting->logo_path,
            'favicon_path' => $setting->favicon_path,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('app_name')
                    ->label('Nome da aplicação')
                    ->placeholder(config('app.name'))
                    ->helperText('Aparece no título da página, no rodapé e no painel administrativo.')
                    ->maxLength(255),
                ColorPicker::make('primary_color')
                    ->label('Cor principal')
                    ->helperText('Usada nos botões e destaques do painel administrativo e do restante da aplicação.')
                    ->required(),
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->helperText('Aparece no topo do painel administrativo e na navegação da aplicação. Deixe em branco para usar o logo padrão da Aresta.')
                    ->image()
                    ->disk('public')
                    ->directory('branding')
                    ->visibility('public'),
                FileUpload::make('favicon_path')
                    ->label('Favicon')
                    ->helperText('Ícone exibido na aba do navegador. Deixe em branco para usar o favicon padrão da Aresta.')
                    ->image()
                    ->disk('public')
                    ->directory('branding')
                    ->visibility('public'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        AppSetting::current()->update($data);

        Notification::make()
            ->success()
            ->title('Configurações salvas')
            ->send();
    }
}
