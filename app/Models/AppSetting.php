<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Configuração de identidade visual da instância (nome, logo, favicon, cor principal) —
 * singleton de infraestrutura, análogo a `AiProviderSetting` (plataforma inteira, não
 * organizacional). Substitui a marca Aresta fixa em código por um valor editável em
 * `/admin`, decisão tomada a pedido do usuário em 2026-08-07 — ver
 * `docs/specs/05-identidade-visual.md` §2/§7.2/§10 pra contexto da decisão anterior que isso
 * substitui.
 */
class AppSetting extends Model
{
    public const DEFAULT_PRIMARY_COLOR = '#00FF66';

    protected $fillable = ['app_name', 'primary_color', 'logo_path', 'favicon_path'];

    /**
     * Sempre a linha `id=1` — não existe "criar outra configuração". Tolerante à tabela ainda
     * não existir (migrations não rodadas) pra não quebrar o boot do `AdminPanelProvider`, que
     * chama isto em todo request, inclusive comandos artisan sem banco disponível.
     */
    public static function current(): self
    {
        try {
            if (! Schema::hasTable('app_settings')) {
                return static::withDefaults();
            }

            return static::query()->firstOrCreate(['id' => 1]);
        } catch (Throwable) {
            return static::withDefaults();
        }
    }

    protected static function withDefaults(): self
    {
        return new static;
    }

    public function resolvedName(): string
    {
        return $this->app_name ?: config('app.name');
    }

    public function resolvedPrimaryColor(): string
    {
        return $this->primary_color ?: self::DEFAULT_PRIMARY_COLOR;
    }

    /**
     * Diferencia "nunca customizado" de "customizado com a mesma cor do padrão" — usado pelas
     * telas Inertia/Blade pra só sobrescrever `--color-accent` (e sua variante clara calibrada
     * AA) quando o admin de fato mexeu na cor, nunca a partir de um valor implícito.
     */
    public function hasCustomPrimaryColor(): bool
    {
        return filled($this->primary_color);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function faviconUrl(): ?string
    {
        return $this->favicon_path ? Storage::disk('public')->url($this->favicon_path) : null;
    }

    /**
     * Cor de texto/ícone sobre um fundo preenchido com `primary_color` — luminância relativa
     * simples (não a fórmula WCAG completa, mas suficiente pra decidir preto/branco), já que o
     * admin escolhe uma cor livre e não dá pra assumir contraste como nos tons fixos do manual.
     */
    public function accentInkColor(): string
    {
        $hex = ltrim($this->resolvedPrimaryColor(), '#');

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '#0D0D0D';
        }

        [$r, $g, $b] = array_map(fn (string $part) => hexdec($part), str_split($hex, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;

        return $luminance > 0.6 ? '#0D0D0D' : '#FFFFFF';
    }
}
