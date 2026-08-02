<?php

namespace App\Models;

use App\Services\AiWorkflowDraftGenerator;
use Illuminate\Database\Eloquent\Model;

/**
 * Configuração global de infraestrutura (como `Organization` — não organizacional, plataforma
 * inteira) de quais provedores de IA o `AiWorkflowDraftGenerator` tenta, em que ordem
 * (`priority`) e com qual modelo. A credencial de fato (URL/key) continua vindo só do `.env`
 * (`config/services.php`) — um provedor com `enabled=true` aqui mas sem credencial configurada
 * é ignorado em runtime, nunca chega a ser chamado.
 */
class AiProviderSetting extends Model
{
    protected $fillable = ['provider', 'enabled', 'priority', 'model'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function isConfigured(): bool
    {
        return AiWorkflowDraftGenerator::isProviderConfigured($this->provider);
    }
}
