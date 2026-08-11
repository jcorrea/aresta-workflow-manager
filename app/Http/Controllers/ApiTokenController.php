<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Token Sanctum pessoal, self-service, pra um usuário chamar a API pública
 * (04-integracao-e-notificacoes.md §5) descrita em Workflows/IntegrationInstructions.vue —
 * distinto do token de `ExternalSystem` (por aplicação cliente, provisionado manualmente).
 * Nasce escopado a UMA organização via ability `org:{id}` — sem isso, qualquer usuário logado
 * poderia trocar `organization_id` no corpo do request e ler/escrever dados de outra
 * organização, já que um token Sanctum sozinho não carrega esse limite por padrão
 * (ProcessInstanceController::activeOrganization confere essa ability pra tokens de User).
 */
class ApiTokenController extends Controller
{
    private const TOKEN_NAME = 'workflow-manager-ui';

    private const TTL_DAYS = 90;

    public function store(Request $request, Workflow $workflow): JsonResponse
    {
        Gate::authorize('view', $workflow);

        $user = $request->user();
        $ability = "org:{$workflow->organization_id}";

        // Regenerar em vez de acumular: só um token ativo por (usuário, organização) — quem
        // gerar de novo invalida o anterior, sem precisar de tela de gestão de tokens.
        $user->tokens()
            ->get()
            ->filter(fn ($token) => in_array($ability, $token->abilities ?? [], true))
            ->each->delete();

        $expiresAt = now()->addDays(self::TTL_DAYS);
        $token = $user->createToken(self::TOKEN_NAME, [$ability], $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'expiresAt' => $expiresAt->format('d/m/Y'),
        ]);
    }
}
