<script setup>
import { computed, ref, toRaw } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
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
});

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
    return null;
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
    lines.push(
        'Só é permitido para atividades do tipo "Ação automática" — atividades humanas (tarefa/formulário) são concluídas pela tela de Minhas Tarefas, não pela API.',
    );

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

        <div class="mx-auto w-full max-w-4xl flex-1 p-6 space-y-6">
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
                    seu software — ela consegue identificar os pontos de integração e como chamar a API pública de acordo
                    com a sua stack.
                </p>
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
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-ink/80">Texto pronto pra enviar pra IA</h2>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-accent/30 bg-accent/10 px-4 py-2 text-xs font-semibold text-accent transition-colors hover:bg-accent/15"
                        @click="copyInstructions"
                    >
                        {{ copied ? 'Copiado!' : 'Copiar tudo' }}
                    </button>
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
