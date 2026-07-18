<?php

namespace App\Models;

use Database\Factories\ExternalSystemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Ator autenticado da API pública (04-integracao-e-notificacoes.md §5) — um sistema GIITS
 * externo (giits-propostas, giits-api), nunca um usuário final. Extends o Authenticatable
 * base do Eloquent (não `App\Models\User`) porque token Sanctum é "por aplicação cliente",
 * distinto de login humano — mas precisa implementar o contrato pra funcionar com
 * `$request->user()`/`auth:sanctum` como qualquer outro model autenticável.
 */
class ExternalSystem extends Authenticatable
{
    /** @use HasFactory<ExternalSystemFactory> */
    use HasApiTokens, HasFactory;

    protected $fillable = ['name', 'active'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
