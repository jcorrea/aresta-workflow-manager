<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import ActivityNode from '@/Components/Workflow/ActivityNode.vue';
import StepNode from '@/Components/Workflow/StepNode.vue';
import AppNav from '@/Components/AppNav.vue';

const props = defineProps({
    instance: { type: Object, required: true },
    graph: { type: Object, required: true },
    tasks: { type: Array, required: true },
});

const { fitView } = useVueFlow();

const selectedNodeId = ref(null);

const nodes = computed(() =>
    props.graph.nodes.map((node) => ({
        ...node,
        selected: node.id === selectedNodeId.value,
    })),
);

// Arestas percorridas ficam destacadas com o acento da marca; as não percorridas do desenho
// ficam em cinza neutro, fora da paleta de marca (03-editor-visual.md §7,
// 05-identidade-visual.md §7.3).
const edges = computed(() =>
    props.graph.edges.map((edge) => ({
        ...edge,
        animated: edge.data.traversed,
        style: edge.data.traversed
            ? { stroke: 'var(--color-accent)', strokeWidth: 2 }
            : { stroke: '#D4D4D4', strokeWidth: 1 },
    })),
);

// Sidebar direita (04-integracao-e-notificacoes.md — acompanhamento de instância): lista as
// tarefas na ordem em que foram executadas de fato, não na ordem de desenho. Clicar numa tarefa
// foca o nó correspondente no diagrama (`nodeId` casado com `activity-{id}` do grafo).
function selectTask(task) {
    selectedNodeId.value = task.nodeId;

    nextTick(() => {
        fitView({ nodes: [task.nodeId], duration: 400, padding: 0.4, maxZoom: 1.2 });
    });
}

const typeIcons = {
    task: '👤',
    form: '📝',
    automated_action: '⚡',
    condition: '◆',
};

const statusLabels = {
    pending: 'Pendente',
    in_progress: 'Em andamento',
    completed: 'Concluída',
    skipped: 'Pulada',
};

const statusBadgeClass = {
    pending: 'bg-ink/10 text-ink/60',
    in_progress: 'bg-accent/15 text-accent',
    completed: 'bg-success/15 text-success',
    skipped: 'bg-ink/10 text-ink/40',
};

// Avançar tarefa direto pela sidebar, sem passar pelo Inbox — mesma rota/policy
// (`ProcessInstanceActivityController`), mesmo padrão de "assumir antes de concluir" da tela
// "Minhas tarefas" (`Inbox/Index.vue`): só oferece o formulário de conclusão depois que a
// atividade já está atribuída a alguém (o próprio usuário, via `canClaim`/`canComplete`
// calculados no servidor pela policy).
const formData = reactive({});

function isOpen(task) {
    return task.status === 'pending' || task.status === 'in_progress';
}

function isActionable(task) {
    return isOpen(task) && (task.isQueued ? task.canClaim : task.canComplete);
}

function claim(task) {
    router.post(route('process-instance-activities.claim', task.id), {}, { preserveScroll: true });
}

function complete(task) {
    const fields = task.fields ?? [];
    const payload = fields.length ? { form_data: formData[task.id] ?? {} } : {};

    router.post(route('process-instance-activities.complete', task.id), payload, { preserveScroll: true });
}

// Acompanhamento em tempo real (00-visao-geral.md §3.5 / 03-editor-visual.md §7): o projeto
// ainda não tem infraestrutura de broadcasting (Reverb/Pusher), então o "tempo real" aqui é
// polling simples via reload parcial do Inertia — reaproveita a mesma requisição de sempre, só
// atualiza `graph`/`tasks`/`instance` sem recarregar a página. Para de fazer polling assim que a
// instância sai de `running` (não há mais nada que mude).
const POLL_INTERVAL_MS = 4000;
let pollTimer = null;

function poll() {
    router.reload({
        only: ['graph', 'tasks', 'instance'],
        preserveScroll: true,
        preserveState: true,
    });
}

function startPolling() {
    if (pollTimer || props.instance.status !== 'running') {
        return;
    }

    pollTimer = setInterval(poll, POLL_INTERVAL_MS);
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

watch(
    () => props.instance.status,
    (status) => {
        if (status === 'running') {
            startPolling();
        } else {
            stopPolling();
        }
    },
);

onMounted(startPolling);
onBeforeUnmount(stopPolling);
</script>

<template>
    <Head :title="`Acompanhar ${instance.name}`" />

    <div class="flex h-screen flex-col bg-canvas">
        <AppNav active="instances" />

        <header class="flex items-center justify-between border-b border-ink/10 bg-panel px-4 py-2">
            <div>
                <h1 class="text-sm font-semibold text-ink">{{ instance.name }}</h1>
                <p class="text-xs text-ink/60">{{ instance.code }} — {{ instance.status }}</p>
            </div>
        </header>

        <div class="flex flex-1 overflow-hidden">
            <div class="flex-1">
                <VueFlow :nodes="nodes" :edges="edges" :nodes-draggable="false" :nodes-connectable="false" :elements-selectable="false">
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

            <!-- Sidebar de tarefas: clicar foca o nó correspondente no diagrama à esquerda. -->
            <aside class="flex w-80 shrink-0 flex-col border-l border-ink/10 bg-panel">
                <div class="border-b border-ink/10 px-4 py-3">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-ink/60">Tarefas</h2>
                </div>

                <div v-if="tasks.length" class="flex-1 overflow-y-auto">
                    <div
                        v-for="task in tasks"
                        :key="task.id"
                        class="border-b border-ink/10 px-4 py-3"
                        :class="task.nodeId === selectedNodeId ? 'bg-accent/10' : ''"
                    >
                        <button type="button" class="flex w-full flex-col gap-1 text-left" @click="selectTask(task)">
                            <div class="flex items-center gap-1.5">
                                <span class="text-sm leading-none">{{ typeIcons[task.type] ?? typeIcons.task }}</span>
                                <span class="truncate text-sm font-medium text-ink">{{ task.name }}</span>
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="statusBadgeClass[task.status]"
                                >
                                    {{ statusLabels[task.status] ?? task.status }}
                                </span>
                                <span v-if="task.assignedUserName" class="text-[11px] text-ink/60">{{ task.assignedUserName }}</span>
                                <span v-else-if="task.isQueued" class="text-[11px] text-ink/60">na fila</span>
                            </div>
                        </button>

                        <div v-if="isActionable(task)" class="mt-2 space-y-2">
                            <button
                                v-if="task.isQueued"
                                type="button"
                                class="w-full rounded-lg border border-ink/10 px-3 py-1.5 text-xs font-semibold text-accent transition-colors hover:bg-accent/10"
                                @click="claim(task)"
                            >
                                Assumir
                            </button>

                            <template v-else>
                                <label v-for="field in task.fields" :key="field.key" class="aresta-label block">
                                    {{ field.label }}
                                    <input
                                        :type="field.type === 'number' ? 'number' : 'text'"
                                        :required="field.required"
                                        class="aresta-input mt-1"
                                        @input="formData[task.id] = { ...(formData[task.id] ?? {}), [field.key]: $event.target.value }"
                                    />
                                </label>

                                <button
                                    type="button"
                                    class="w-full rounded-lg bg-accent px-3 py-1.5 text-xs font-semibold text-accent-ink transition-opacity hover:opacity-90"
                                    @click="complete(task)"
                                >
                                    Concluir
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <p v-else class="flex-1 px-4 py-3 text-xs text-ink/60">Nenhuma tarefa iniciada ainda.</p>
            </aside>
        </div>
    </div>
</template>

<style>
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
@import '@vue-flow/controls/dist/style.css';

/* Controles do canvas (zoom/fit/lock) na cor da marca — 05-identidade-visual.md §7.3 */
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
