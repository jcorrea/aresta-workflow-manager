<script setup>
import { computed, ref, toRaw } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { VueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { route } from 'ziggy-js';
import AppNav from '@/Components/AppNav.vue';
import ActivityNode from '@/Components/Workflow/ActivityNode.vue';
import StepNode from '@/Components/Workflow/StepNode.vue';

const props = defineProps({
    workflow: { type: Object, required: true },
    version: { type: Object, required: true },
    graph: { type: Object, required: true },
    apiBaseUrl: { type: String, required: true },
    apiToken: { type: Object, required: true },
});

const hasActiveToken = ref(props.apiToken.hasActiveToken);
const tokenExpiresAt = ref(props.apiToken.expiresAt);
const revealedToken = ref(null);
const generatingToken = ref(false);
const tokenError = ref(null);
const tokenCopied = ref(false);

async function generateToken() {
    generatingToken.value = true;
    tokenError.value = null;
    try {
        const { data } = await axios.post(route('workflows.api-token.store', props.workflow.id));
        revealedToken.value = data.token;
        tokenExpiresAt.value = data.expiresAt;
        hasActiveToken.value = true;
    } catch {
        tokenError.value = 'Não foi possível gerar o token agora. Tente de novo em instantes.';
    } finally {
        generatingToken.value = false;
    }
}

async function copyToken() {
    await navigator.clipboard.writeText(revealedToken.value);
    tokenCopied.value = true;
    setTimeout(() => (tokenCopied.value = false), 2000);
}

function dismissRevealedToken() {
    revealedToken.value = null;
    tokenCopied.value = false;
}

const nodes = ref(structuredClone(toRaw(props.graph.nodes)));
const edges = ref(structuredClone(toRaw(props.graph.edges)));

const ACTIVITY_TYPE_LABELS = {
    task: 'Tarefa (humana)',
    form: 'Formulário (humano)',
    automated_action: 'Ação automática',
    condition: 'Condição',
};

const activityById = computed(() => {
    const map = new Map();
    for (const node of props.graph.nodes) {
        if (node.type !== 'step') map.set(node.data.id, node.data);
    }
    return map;
});

function assigneeLabel(activity) {
    if (activity.assigneeType === 'role') return `papel "${activity.assigneeRoleName ?? activity.assigneeRoleId}"`;
    if (activity.assigneeType === 'user') return `pessoa "${activity.assigneeUserName ?? activity.assigneeUserId}"`;
    if (activity.assigneeType === 'external') return 'humano externo (concluído via API, sem usuário no Aresta)';
    return null;
}

const STACK_OPTIONS = [
    { value: 'http', label: 'HTTP puro' },
    { value: 'php-native', label: 'PHP nativo (SDK)' },
    { value: 'php-laravel', label: 'PHP Laravel (SDK)' },
];

const stack = ref('http');

const automatedActivity = computed(
    () => props.graph.nodes.find((n) => n.type !== 'step' && n.data.type === 'automated_action')?.data,
);

const humanActivity = computed(
    () =>
        props.graph.nodes.find((n) => n.type !== 'step' && ['task', 'form'].includes(n.data.type) && n.data.assigneeType === 'external')
            ?.data ?? props.graph.nodes.find((n) => n.type !== 'step' && ['task', 'form'].includes(n.data.type))?.data,
);

function pushHttpSection(lines) {
    lines.push('## API pública para integrar (Laravel Sanctum)');
    lines.push('');
    lines.push(
        'Autenticação: Bearer token Sanctum emitido pra sua aplicação (peça o token a quem administra este workspace — não é o mesmo login de usuário). Toda chamada abaixo exige `organization_id` explícito no corpo/query.',
    );
    lines.push('');
    lines.push(`Base URL: \`${props.apiBaseUrl}\``);
    lines.push(`Slug deste workflow: \`${props.workflow.slug}\``);
    lines.push(`organization_id desta organização: \`${props.workflow.organizationId}\``);
    lines.push('');
    lines.push('1. Iniciar uma instância do processo:');
    lines.push('```');
    lines.push(`POST ${props.apiBaseUrl}/workflows/${props.workflow.slug}/instances`);
    lines.push('Authorization: Bearer {token}');
    lines.push('Content-Type: application/json');
    lines.push('');
    lines.push(
        JSON.stringify(
            { organization_id: props.workflow.organizationId, name: 'opcional', context: { exemplo: 'dados iniciais' } },
            null,
            2,
        ),
    );
    lines.push('```');
    lines.push('Resposta: `{ id, code, status }` — guarde o `code` pra consultar depois.');
    lines.push('');
    lines.push('2. Consultar status da instância:');
    lines.push('```');
    lines.push(`GET ${props.apiBaseUrl}/instances/{code}?organization_id=${props.workflow.organizationId}`);
    lines.push('```');
    lines.push('Resposta: `{ id, code, name, status, context, started_at, completed_at, active_activities: [...] }`.');
    lines.push('');
    lines.push('3. Listar atividades da instância:');
    lines.push('```');
    lines.push(`GET ${props.apiBaseUrl}/instances/{code}/activities?organization_id=${props.workflow.organizationId}`);
    lines.push('```');
    lines.push('Resposta: `{ activities: [{ id, name, type, status, started_at, completed_at, result }, ...] }`.');
    lines.push('');
    lines.push('4. Completar uma atividade do tipo "Ação automática" (`automated_action`) via API:');
    lines.push('```');
    lines.push(`POST ${props.apiBaseUrl}/instances/{code}/activities/{activityId}/complete`);
    lines.push('Authorization: Bearer {token}');
    lines.push('Content-Type: application/json');
    lines.push('');
    lines.push(JSON.stringify({ organization_id: props.workflow.organizationId, result: { exemplo: 'dados de saída' } }, null, 2));
    lines.push('```');
    lines.push('');
    lines.push(
        '5. Completar uma atividade humana ("Tarefa"/"Formulário") via API — use quando quem fez o trabalho ' +
            'trabalhou no seu sistema, não na Inbox do Aresta. Exige `completed_by` identificando quem fez:',
    );
    lines.push('```');
    lines.push(`POST ${props.apiBaseUrl}/instances/{code}/activities/{activityId}/complete`);
    lines.push('Authorization: Bearer {token}');
    lines.push('Content-Type: application/json');
    lines.push('');
    lines.push(
        JSON.stringify(
            {
                organization_id: props.workflow.organizationId,
                completed_by: { name: 'Nome de quem fez', email: 'email@exemplo.com' },
                result: { exemplo: 'dados preenchidos' },
            },
            null,
            2,
        ),
    );
    lines.push('```');
    lines.push(completedByBehaviorNote());
}

function completedByBehaviorNote() {
    return (
        'Se o e-mail bater com um usuário do Aresta vinculado ao papel/pessoa responsável pela atividade, a ' +
        'conclusão fica limpa como se ele tivesse concluído pela Inbox. Se não bater (ou não existir ainda), a ' +
        'atividade completa normalmente mesmo assim e o admin da organização recebe um aviso pra revisar o ' +
        'vínculo — nunca trava o processo por causa de um cadastro pendente. Atividades marcadas como ' +
        '"Externo" no desenho (ex.: uma etapa feita pelo cliente/candidato, que nunca vai ter usuário no ' +
        'Aresta) sempre usam esse caminho, sem tentar casar com usuário nenhum.'
    );
}

function pushSdkCompleteActivitySteps(lines, { clientExpr, startNumber }) {
    let step = startNumber;
    const automated = automatedActivity.value;
    const human = humanActivity.value;

    if (automated) {
        lines.push('');
        lines.push(`${step}. Completar a atividade "Ação automática" ("${automated.name}") quando seu sistema terminar o trabalho dela:`);
        lines.push('```php');
        lines.push(`$activityId = ${clientExpr}->findActivityId($result['code'], '${automated.name}');`);
        lines.push('if ($activityId) {');
        lines.push(`    ${clientExpr}->completeActivity($result['code'], $activityId, ['exemplo' => 'dados de saída']);`);
        lines.push('}');
        lines.push('```');
        step += 1;
    }

    if (human) {
        const isExternal = human.assigneeType === 'external';
        lines.push('');
        lines.push(
            `${step}. Completar a atividade humana ("${human.name}") quando ela é feita fora da Inbox do Aresta` +
                (isExternal
                    ? ' — no desenho ela está marcada como "Externo", então esse é o único jeito de concluí-la:'
                    : ' — exige informar quem fez (`completed_by`):'),
        );
        lines.push('```php');
        lines.push(`$activityId = ${clientExpr}->findActivityId($result['code'], '${human.name}');`);
        lines.push('if ($activityId) {');
        lines.push(`    ${clientExpr}->completeActivity($result['code'], $activityId, ['exemplo' => 'dados preenchidos'], [`);
        lines.push("        'name' => 'Nome de quem fez',");
        lines.push("        'email' => 'email@exemplo.com',");
        lines.push('    ]);');
        lines.push('}');
        lines.push('```');
        lines.push('');
        lines.push(completedByBehaviorNote());
        step += 1;
    }
}

function pushPhpNativeSection(lines) {
    lines.push('## Integração via SDK PHP (`its-group/aresta-workflow-sdk`)');
    lines.push('');
    lines.push(
        'Pacote Composer com os mesmos endpoints da API pública acima, sem lidar com HTTP na mão — cobre iniciar ' +
            'instância, consultar status, listar atividades e completar atividade, mais um helper ' +
            '(`findActivityId`) pra localizar a atividade pendente pelo nome.',
    );
    lines.push('');
    lines.push('1. Instalar:');
    lines.push('```');
    lines.push('composer require its-group/aresta-workflow-sdk');
    lines.push('```');
    lines.push('');
    lines.push('2. Configurar o client:');
    lines.push('```php');
    lines.push("use Aresta\\WorkflowSdk\\Config;");
    lines.push("use Aresta\\WorkflowSdk\\WorkflowClient;");
    lines.push('');
    lines.push('$client = new WorkflowClient(new Config(');
    lines.push(`    baseUrl: '${props.apiBaseUrl}',`);
    lines.push("    token: getenv('ARESTA_WORKFLOW_TOKEN'), // peça o token a quem administra este workspace");
    lines.push(`    organizationId: ${props.workflow.organizationId},`);
    lines.push('));');
    lines.push('```');
    lines.push('');
    lines.push('3. Iniciar uma instância do processo:');
    lines.push('```php');
    lines.push(`$result = $client->startInstance('${props.workflow.slug}', [`);
    lines.push("    'exemplo' => 'dados iniciais',");
    lines.push(']);');
    lines.push("// ['id' => ..., 'code' => 'abc123', 'status' => 'running'] — guarde o 'code' pra consultar depois");
    lines.push('```');
    lines.push('');
    lines.push('4. Consultar status e listar atividades da instância:');
    lines.push('```php');
    lines.push("$instance = $client->getInstance($result['code']);");
    lines.push("$activities = $client->listActivities($result['code']);");
    lines.push('```');

    pushSdkCompleteActivitySteps(lines, { clientExpr: '$client', startNumber: 5 });

    lines.push('');
    lines.push(
        'Todos os métodos retornam `null` em caso de falha (e logam, se você passar um `Psr\\Log\\LoggerInterface` ' +
            'como terceiro argumento do construtor) — nenhum lança exceção, quem chama decide se uma falha de ' +
            'integração deve travar o fluxo ou só seguir.',
    );
    lines.push('');
    lines.push(
        'Pra guardar a referência entre um registro local e o `code` da instância remota, crie uma tabela própria ' +
            '(esse pacote não roda migration fora do Laravel) — schema sugerido:',
    );
    lines.push('```sql');
    lines.push('CREATE TABLE aresta_workflow_instances (');
    lines.push('    id INT AUTO_INCREMENT PRIMARY KEY,');
    lines.push('    workflow_slug VARCHAR(255) NOT NULL,');
    lines.push('    code VARCHAR(255) NOT NULL UNIQUE,');
    lines.push('    trackable_type VARCHAR(255) NOT NULL,');
    lines.push('    trackable_id BIGINT UNSIGNED NOT NULL,');
    lines.push('    status VARCHAR(255) NULL,');
    lines.push('    created_at TIMESTAMP NULL,');
    lines.push('    updated_at TIMESTAMP NULL,');
    lines.push('    INDEX aresta_workflow_instances_trackable_index (trackable_type, trackable_id)');
    lines.push(');');
    lines.push('```');
}

function pushPhpLaravelSection(lines) {
    lines.push('## Integração via SDK PHP — Laravel (`its-group/aresta-workflow-sdk`)');
    lines.push('');
    lines.push(
        'O pacote se auto-registra via package discovery e já sai resolvível pelo container. Cobre os mesmos ' +
            'endpoints da API pública acima, mais um model (`WorkflowInstance`) pra guardar o vínculo entre um ' +
            'registro local e a instância remota.',
    );
    lines.push('');
    lines.push('1. Instalar, publicar a config e migrar:');
    lines.push('```');
    lines.push('composer require its-group/aresta-workflow-sdk');
    lines.push('php artisan vendor:publish --tag=aresta-workflow-config');
    lines.push('php artisan migrate');
    lines.push('```');
    lines.push('');
    lines.push('2. Configurar o `.env`:');
    lines.push('```');
    lines.push(`ARESTA_WORKFLOW_BASE_URL=${props.apiBaseUrl}`);
    lines.push('ARESTA_WORKFLOW_TOKEN=...');
    lines.push(`ARESTA_WORKFLOW_ORGANIZATION_ID=${props.workflow.organizationId}`);
    lines.push('```');
    lines.push('');
    lines.push('3. Injetar o client onde for chamar (resolvível via container):');
    lines.push('```php');
    lines.push('use Aresta\\WorkflowSdk\\WorkflowClient;');
    lines.push('');
    lines.push('public function __construct(private WorkflowClient $client)');
    lines.push('{');
    lines.push('}');
    lines.push('```');
    lines.push('');
    lines.push('4. Iniciar uma instância e guardar a referência com o registro local:');
    lines.push('```php');
    lines.push('use Aresta\\WorkflowSdk\\Laravel\\WorkflowInstance;');
    lines.push('');
    lines.push(`$result = $this->client->startInstance('${props.workflow.slug}', [`);
    lines.push("    'exemplo' => 'dados iniciais',");
    lines.push(']);');
    lines.push('');
    lines.push('WorkflowInstance::create([');
    lines.push(`    'workflow_slug' => '${props.workflow.slug}',`);
    lines.push("    'code' => \$result['code'],");
    lines.push("    'trackable_type' => SeuModel::class, // troque pelo model do seu domínio");
    lines.push("    'trackable_id' => \$registro->id,");
    lines.push(']);');
    lines.push('```');
    lines.push('');
    lines.push('5. Recuperar depois, em outro request, e consultar status/atividades:');
    lines.push('```php');
    lines.push("$instance = WorkflowInstance::where('trackable_type', SeuModel::class)");
    lines.push("    ->where('trackable_id', \$registro->id)");
    lines.push('    ->first();');
    lines.push('');
    lines.push('$activities = $this->client->listActivities($instance->code);');
    lines.push('```');

    pushSdkCompleteActivitySteps(lines, { clientExpr: '$this->client', startNumber: 6 });

    lines.push('');
    lines.push(
        'Todos os métodos retornam `null` em caso de falha (e logam via o logger padrão do Laravel) — nenhum ' +
            'lança exceção, quem chama decide se uma falha de integração deve travar o fluxo ou só seguir.',
    );
}

const instructionsText = computed(() => {
    const lines = [];

    lines.push(`# Integração com o processo "${props.workflow.name}"`);
    lines.push('');
    if (props.workflow.description) {
        lines.push(props.workflow.description);
        lines.push('');
    }
    lines.push(
        `Versão do desenho usada abaixo: v${props.version.versionNumber ?? '—'} (status: ${props.version.status}).`,
    );
    lines.push('');
    lines.push('## Etapas e atividades do fluxo');

    for (const step of props.graph.nodes.filter((n) => n.type === 'step')) {
        lines.push('');
        lines.push(`### Etapa: ${step.data.name}`);
        const activities = props.graph.nodes.filter((n) => n.type !== 'step' && n.data.workflowStepId === step.data.id);
        for (const activity of activities) {
            const parts = [`- ${activity.data.name} (${ACTIVITY_TYPE_LABELS[activity.data.type] ?? activity.data.type})`];
            const assignee = assigneeLabel(activity.data);
            if (assignee) parts.push(`responsável: ${assignee}`);
            if (activity.data.slaHours) parts.push(`SLA: ${activity.data.slaHours}h`);
            if (activity.data.isStart) parts.push('início do processo');
            if (activity.data.isEnd) parts.push('fim do processo');
            lines.push(parts.join(' — '));
        }
    }

    lines.push('');
    lines.push('## Transições entre atividades');
    for (const edge of props.graph.edges) {
        const from = activityById.value.get(Number(edge.source.replace('activity-', '')));
        const to = activityById.value.get(Number(edge.target.replace('activity-', '')));
        const condition =
            edge.data.conditionType === 'expression'
                ? `condição: ${edge.data.conditionExpression}`
                : 'sempre (ramo paralelo)';
        lines.push(`- ${from?.name ?? '?'} → ${to?.name ?? '?'} (${condition})`);
    }

    lines.push('');
    if (stack.value === 'php-native') {
        pushPhpNativeSection(lines);
    } else if (stack.value === 'php-laravel') {
        pushPhpLaravelSection(lines);
    } else {
        pushHttpSection(lines);
    }

    return lines.join('\n');
});

const copied = ref(false);

async function copyInstructions() {
    await navigator.clipboard.writeText(instructionsText.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
}
</script>

<template>
    <Head :title="`Integração de IA — ${workflow.name}`" />

    <div class="flex min-h-screen flex-col bg-canvas">
        <AppNav active="workflows" />

        <div class="mx-auto w-full max-w-6xl flex-1 p-6 space-y-6">
            <div>
                <Link
                    :href="route('workflows.show', workflow.id)"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink/60 hover:text-ink transition-colors"
                >
                    <span>&larr;</span>
                    <span>Voltar para {{ workflow.name }}</span>
                </Link>
            </div>

            <div>
                <h1 class="text-xl font-bold tracking-tight text-ink">Instruções de integração (IA)</h1>
                <p class="mt-1 text-sm text-ink/70 leading-relaxed">
                    Descrição do processo e o modelo do diagrama, prontos pra você colar na IA que usa na implementação do
                    seu software — ela consegue identificar os pontos de integração. Escolha a stack abaixo pra já vir
                    com o jeito certo de chamar (API pública crua, ou as funções do SDK PHP).
                </p>
            </div>

            <div class="rounded-xl border border-ink/10 bg-panel p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-ink/80">Seu token de acesso</h2>
                <p class="mt-1 text-xs text-ink/60 leading-relaxed">
                    Token pessoal (Bearer) pra testar a API pública desta organização — vale pra qualquer workflow que você
                    já acessa aqui, não só este, e expira em 90 dias.
                </p>

                <div v-if="revealedToken" class="mt-3 space-y-2">
                    <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-800">
                        Copie agora — depois de sair ou recarregar esta tela não dá mais pra ver o valor de novo. Cole só no
                        seu <code>.env</code> ou gerenciador de segredos; nunca cole isso numa conversa com a IA nem dentro
                        do texto abaixo.
                    </div>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 overflow-x-auto rounded-lg border border-ink/10 bg-canvas px-3 py-2 text-xs text-ink/80">{{
                            revealedToken
                        }}</code>
                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center justify-center rounded-lg border border-accent/30 bg-accent/10 px-4 py-2 text-xs font-semibold text-accent transition-colors hover:bg-accent/15"
                            @click="copyToken"
                        >
                            {{ tokenCopied ? 'Copiado!' : 'Copiar' }}
                        </button>
                    </div>
                    <button type="button" class="text-xs font-semibold text-ink/50 underline hover:text-ink/70" @click="dismissRevealedToken">
                        Ok, guardei
                    </button>
                </div>

                <div v-else class="mt-3 flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-accent/30 bg-accent/10 px-4 py-2 text-xs font-semibold text-accent transition-colors hover:bg-accent/15 disabled:opacity-50"
                        :disabled="generatingToken"
                        @click="generateToken"
                    >
                        {{ generatingToken ? 'Gerando...' : hasActiveToken ? 'Gerar novo token' : 'Gerar meu token' }}
                    </button>
                    <span v-if="hasActiveToken" class="text-xs text-ink/60">Token ativo, expira em {{ tokenExpiresAt }}.</span>
                </div>

                <p v-if="tokenError" class="mt-2 text-xs text-red-600">{{ tokenError }}</p>
            </div>

            <div class="rounded-xl border border-ink/10 bg-panel p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-ink/80">Diagrama do fluxo</h2>
                <div class="mt-3 h-[420px] overflow-hidden rounded-lg border border-ink/10">
                    <VueFlow :nodes="nodes" :edges="edges" fit-view-on-init nodes-draggable="false" nodes-connectable="false">
                        <Background pattern-color="rgba(13, 13, 13, 0.08)" :gap="20" />
                        <Controls :show-interactive="false" />

                        <template #node-step="nodeProps">
                            <StepNode v-bind="nodeProps" readonly />
                        </template>
                        <template #node-task="nodeProps">
                            <ActivityNode v-bind="nodeProps" />
                        </template>
                        <template #node-form="nodeProps">
                            <ActivityNode v-bind="nodeProps" />
                        </template>
                        <template #node-automated_action="nodeProps">
                            <ActivityNode v-bind="nodeProps" />
                        </template>
                        <template #node-condition="nodeProps">
                            <ActivityNode v-bind="nodeProps" />
                        </template>
                    </VueFlow>
                </div>
            </div>

            <div class="rounded-xl border border-ink/10 bg-panel p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-ink/80">Texto pronto pra enviar pra IA</h2>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-accent/30 bg-accent/10 px-4 py-2 text-xs font-semibold text-accent transition-colors hover:bg-accent/15"
                        @click="copyInstructions"
                    >
                        {{ copied ? 'Copiado!' : 'Copiar tudo' }}
                    </button>
                </div>

                <div class="mt-4">
                    <span class="text-xs font-semibold text-ink/60">Stack de integração</span>
                    <div class="mt-1.5 inline-flex rounded-lg border border-ink/10 bg-canvas p-1">
                        <button
                            v-for="option in STACK_OPTIONS"
                            :key="option.value"
                            type="button"
                            class="rounded-md px-3 py-1.5 text-xs font-semibold transition-colors"
                            :class="
                                stack === option.value
                                    ? 'bg-accent text-white'
                                    : 'text-ink/60 hover:text-ink'
                            "
                            @click="stack = option.value"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <pre class="mt-3 max-h-[480px] overflow-auto rounded-lg border border-ink/10 bg-canvas p-4 text-xs leading-relaxed text-ink/80 whitespace-pre-wrap">{{ instructionsText }}</pre>
            </div>
        </div>
    </div>
</template>

<style>
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
@import '@vue-flow/controls/dist/style.css';

.vue-flow__controls {
    box-shadow: 0 0 0 1px rgba(13, 13, 13, 0.08);
    border-radius: 8px;
    overflow: hidden;
}
.vue-flow__controls-button {
    background: var(--color-panel);
    border-bottom: 1px solid rgba(13, 13, 13, 0.08);
}
.vue-flow__controls-button:hover {
    background: color-mix(in srgb, var(--color-accent) 15%, var(--color-panel));
}
.vue-flow__controls-button svg {
    fill: var(--color-ink);
}
</style>
