<script setup>
import { ref, computed, watch, toRaw } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { route } from 'ziggy-js';
import axios from 'axios';
import ActivityNode from '@/Components/Workflow/ActivityNode.vue';
import StepNode from '@/Components/Workflow/StepNode.vue';
import AiRefineModal from '@/Components/Workflow/AiRefineModal.vue';
import AppNav from '@/Components/AppNav.vue';

const props = defineProps({
    workflow: { type: Object, required: true },
    version: { type: Object, required: true },
    graph: { type: Object, required: true },
    issues: { type: Array, required: true },
    roles: { type: Array, required: true },
    users: { type: Array, required: true },
});

// `props` é reativo (Proxy do Vue) — structuredClone() nativo do navegador não consegue
// clonar um Proxy (DataCloneError), mesmo em array vazio. toRaw() desembrulha antes de clonar.
const nodes = ref(structuredClone(toRaw(props.graph.nodes)));
const edges = ref(structuredClone(toRaw(props.graph.edges)));
const selected = ref(null); // { kind: 'step' | 'activity' | 'transition', id: number }
const publishing = ref(false);
const publishErrors = ref([]);
const showAiModal = ref(false);

watch(
    () => props.graph,
    (newGraph) => {
        nodes.value = structuredClone(toRaw(newGraph.nodes));
        edges.value = structuredClone(toRaw(newGraph.edges));
    },
    { deep: true },
);

const { onConnect: onVueFlowConnect } = useVueFlow();

const activityNodeId = (dbId) => `activity-${dbId}`;
const stepNodeId = (dbId) => `step-${dbId}`;
const dbIdFromNodeId = (nodeId) => Number(nodeId.split('-').pop());

const errorIssues = computed(() => props.issues.filter((i) => i.severity === 'error'));
const warningIssues = computed(() => props.issues.filter((i) => i.severity === 'warning'));

const selectedStep = computed(() => {
    if (selected.value?.kind !== 'step') return null;
    return nodes.value.find((n) => n.type === 'step' && n.data.id === selected.value.id) ?? null;
});
const selectedActivity = computed(() => {
    if (selected.value?.kind !== 'activity') return null;
    return nodes.value.find((n) => n.type !== 'step' && n.data.id === selected.value.id) ?? null;
});
const selectedTransition = computed(() => {
    if (selected.value?.kind !== 'transition') return null;
    return edges.value.find((e) => e.data.id === selected.value.id) ?? null;
});

function onNodeClick({ node }) {
    selected.value = { kind: node.type === 'step' ? 'step' : 'activity', id: node.data.id };
}

function onEdgeClick({ edge }) {
    selected.value = { kind: 'transition', id: edge.data.id };
}

function onPaneClick() {
    selected.value = null;
}

async function onNodeDragStop({ node }) {
    const position = { position_x: Math.round(node.position.x), position_y: Math.round(node.position.y) };

    if (node.type === 'step') {
        await axios.patch(route('workflow-steps.update', node.data.id), position);
    } else {
        await axios.patch(route('workflow-activities.update', node.data.id), position);
    }
}

async function onStepResizeEnd({ id, width, height, positionX, positionY }) {
    await axios.patch(route('workflow-steps.update', id), {
        width,
        height,
        position_x: positionX,
        position_y: positionY,
    });
}

async function addStep() {
    const { data } = await axios.post(route('workflow-steps.store', [props.workflow.id, props.version.id]), {
        name: 'Nova etapa',
        position_x: 40,
        position_y: 40,
        width: 320,
        height: 220,
        sort_order: nodes.value.filter((n) => n.type === 'step').length,
    });

    nodes.value.push({
        id: stepNodeId(data.id),
        type: 'step',
        position: { x: 40, y: 40 },
        style: { width: '320px', height: '220px' },
        data: { id: data.id, name: 'Nova etapa', slaDays: null, sortOrder: 0 },
    });
}

async function addActivity(stepDbId) {
    const { data } = await axios.post(route('workflow-activities.store', [props.workflow.id, props.version.id]), {
        workflow_step_id: stepDbId,
        name: 'Nova atividade',
        type: 'task',
        config: {},
        position_x: 20,
        position_y: 48,
    });

    nodes.value.push({
        id: activityNodeId(data.id),
        type: 'task',
        position: { x: 20, y: 48 },
        parentNode: stepNodeId(stepDbId),
        extent: 'parent',
        data: {
            id: data.id,
            workflowStepId: stepDbId,
            name: 'Nova atividade',
            type: 'task',
            assigneeType: null,
            assigneeRoleId: null,
            assigneeRoleName: null,
            assigneeUserId: null,
            assigneeUserName: null,
            config: {},
            slaHours: null,
            isStart: false,
            isEnd: false,
        },
    });
    selected.value = { kind: 'activity', id: data.id };
}

onVueFlowConnect(async ({ source, target }) => {
    const fromId = dbIdFromNodeId(source);
    const toId = dbIdFromNodeId(target);

    // Uma transição saindo de um nó de Decisão quase sempre é um caminho exclusivo, não um
    // fork paralelo — nascer como "always" obriga o usuário a lembrar de trocar pra
    // "expression" manualmente, e esquecer disso é o motivo mais comum dos erros de
    // pareamento fork/join no publish (WorkflowGraphValidator::validateForkJoinPairing).
    const sourceNode = nodes.value.find((n) => n.id === source);
    const conditionType = sourceNode?.data?.type === 'condition' ? 'expression' : 'always';

    const { data } = await axios.post(route('workflow-transitions.store', [props.workflow.id, props.version.id]), {
        from_activity_id: fromId,
        to_activity_id: toId,
        condition_type: conditionType,
        sort_order: 0,
    });

    edges.value.push({
        id: `transition-${data.id}`,
        source,
        target,
        label: null,
        data: { id: data.id, conditionType, conditionExpression: null, sortOrder: 0 },
    });
    selected.value = { kind: 'transition', id: data.id };
});

async function updateStep(patch) {
    const step = selectedStep.value;
    if (!step) return;

    await axios.patch(route('workflow-steps.update', step.data.id), patch);
    Object.assign(step.data, patch);
    if (patch.width) step.style = { ...step.style, width: `${patch.width}px` };
    if (patch.height) step.style = { ...step.style, height: `${patch.height}px` };
}

async function updateActivity(patch) {
    const activity = selectedActivity.value;
    if (!activity) return;

    await axios.patch(route('workflow-activities.update', activity.data.id), patch);
    Object.assign(activity.data, patch);

    if ('assignee_type' in patch) {
        activity.data.assigneeType = patch.assignee_type;
        if (!patch.assignee_type) {
            activity.data.assigneeRoleId = null;
            activity.data.assigneeRoleName = null;
            activity.data.assigneeUserId = null;
            activity.data.assigneeUserName = null;
        }
    }

    if ('assignee_role_id' in patch || 'assigneeRoleId' in patch) {
        const roleId = patch.assignee_role_id ?? patch.assigneeRoleId;
        activity.data.assigneeRoleId = roleId;
        const role = props.roles.find((r) => r.id === roleId);
        activity.data.assigneeRoleName = role?.name ?? null;
        if (roleId) {
            activity.data.assigneeUserId = null;
            activity.data.assigneeUserName = null;
        }
    }

    if ('assignee_user_id' in patch || 'assigneeUserId' in patch) {
        const userId = patch.assignee_user_id ?? patch.assigneeUserId;
        activity.data.assigneeUserId = userId;
        const user = props.users.find((u) => u.id === userId);
        activity.data.assigneeUserName = user?.name ?? null;
        if (userId) {
            activity.data.assigneeRoleId = null;
            activity.data.assigneeRoleName = null;
        }
    }

    if (patch.type) activity.type = patch.type;
}

async function updateTransition(patch) {
    const transition = selectedTransition.value;
    if (!transition) return;

    await axios.patch(route('workflow-transitions.update', transition.data.id), patch);
    Object.assign(transition.data, patch);
    if ('label' in patch) transition.label = patch.label;
}

async function deleteSelected() {
    if (!selected.value) return;
    const { kind, id } = selected.value;

    if (kind === 'step') {
        await axios.delete(route('workflow-steps.destroy', id));
        nodes.value = nodes.value.filter((n) => !(n.type === 'step' && n.data.id === id) && n.parentNode !== stepNodeId(id));
        edges.value = edges.value.filter((e) => nodes.value.some((n) => n.id === e.source) && nodes.value.some((n) => n.id === e.target));
    } else if (kind === 'activity') {
        await axios.delete(route('workflow-activities.destroy', id));
        nodes.value = nodes.value.filter((n) => !(n.type !== 'step' && n.data.id === id));
        edges.value = edges.value.filter((e) => e.source !== activityNodeId(id) && e.target !== activityNodeId(id));
    } else if (kind === 'transition') {
        await axios.delete(route('workflow-transitions.destroy', id));
        edges.value = edges.value.filter((e) => e.data.id !== id);
    }

    selected.value = null;
}

function publish() {
    publishing.value = true;
    publishErrors.value = [];

    router.post(
        route('workflows.versions.publish', [props.workflow.id, props.version.id]),
        {},
        {
            onError: (errors) => {
                // `errors.graph` pode vir como string (achatado pelo Inertia) ou array
                // (mensagens do WorkflowGraphValidator) — nunca iterar sem normalizar
                // pra array, senão um v-for sobre string quebra caractere por caractere.
                publishErrors.value = errors.graph ? [].concat(errors.graph) : ['Não foi possível publicar.'];
            },
            onFinish: () => {
                publishing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="`Editar ${workflow.name}`" />

    <div class="flex h-screen flex-col bg-canvas">
        <AppNav active="workflows" />

        <header class="flex items-center justify-between border-b border-ink/10 bg-panel px-4 py-2">
            <div>
                <h1 class="text-sm font-semibold text-ink">{{ workflow.name }}</h1>
                <p class="text-xs text-ink/60">Rascunho — {{ nodes.filter((n) => n.type !== 'step').length }} atividade(s)</p>
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg border border-accent/30 bg-accent/10 px-3 py-1.5 text-xs font-semibold text-accent transition-colors hover:bg-accent/20"
                    @click="showAiModal = true"
                >
                    <span>✨</span>
                    <span>Ajustar com IA</span>
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-ink/10 px-3 py-1.5 text-xs font-semibold text-ink transition-colors hover:bg-ink/5"
                    @click="addStep"
                >
                    + Etapa
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-accent px-3 py-1.5 text-xs font-semibold text-accent-ink transition-opacity hover:opacity-90 disabled:opacity-50"
                    :disabled="publishing"
                    @click="publish"
                >
                    Publicar
                </button>
            </div>
        </header>

        <div v-if="errorIssues.length || warningIssues.length || publishErrors.length" class="border-b border-ink/10 bg-panel px-4 py-2 text-xs">
            <p v-for="(msg, i) in publishErrors" :key="`pub-${i}`" class="text-danger">{{ msg }}</p>
            <p v-for="issue in errorIssues" :key="issue.code + issue.activityIds.join(',')" class="text-danger">⛔ {{ issue.message }}</p>
            <p v-for="issue in warningIssues" :key="issue.code + issue.activityIds.join(',')" class="text-warning">⚠️ {{ issue.message }}</p>
        </div>

        <div class="flex flex-1 overflow-hidden">
            <div class="flex-1">
                <VueFlow
                    v-model:nodes="nodes"
                    v-model:edges="edges"
                    fit-view-on-init
                    @node-click="onNodeClick"
                    @edge-click="onEdgeClick"
                    @pane-click="onPaneClick"
                    @node-drag-stop="onNodeDragStop"
                >
                    <Background pattern-color="rgba(13, 13, 13, 0.08)" :gap="20" />
                    <Controls />

                    <template #node-step="nodeProps">
                        <StepNode v-bind="nodeProps" @add-activity="addActivity" @resize-end="onStepResizeEnd" />
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

            <aside v-if="selected" class="w-80 overflow-y-auto border-l border-ink/10 bg-panel p-4 text-sm">
                <div v-if="selectedStep">
                    <h2 class="mb-2 font-semibold text-ink">Etapa</h2>
                    <label class="aresta-label mb-3">
                        Nome
                        <input
                            class="aresta-input mt-1"
                            :value="selectedStep.data.name"
                            @change="updateStep({ name: $event.target.value })"
                        />
                    </label>
                    <label class="aresta-label mb-3">
                        SLA (dias)
                        <input
                            type="number"
                            class="aresta-input mt-1"
                            :value="selectedStep.data.slaDays"
                            @change="updateStep({ sla_days: $event.target.value ? Number($event.target.value) : null })"
                        />
                    </label>
                    <button type="button" class="mt-2 text-xs text-danger hover:underline" @click="deleteSelected">Excluir etapa</button>
                </div>

                <div v-else-if="selectedActivity">
                    <h2 class="mb-2 font-semibold text-ink">Atividade</h2>
                    <label class="aresta-label mb-3">
                        Nome
                        <input
                            class="aresta-input mt-1"
                            :value="selectedActivity.data.name"
                            @change="updateActivity({ name: $event.target.value })"
                        />
                    </label>
                    <label class="aresta-label mb-3">
                        Tipo
                        <select
                            class="aresta-input mt-1"
                            :value="selectedActivity.data.type"
                            @change="updateActivity({ type: $event.target.value })"
                        >
                            <option value="task">Tarefa</option>
                            <option value="form">Formulário</option>
                            <option value="automated_action">Ação automática</option>
                            <option value="condition">Decisão</option>
                        </select>
                    </label>

                    <template v-if="['task', 'form'].includes(selectedActivity.data.type)">
                        <label class="aresta-label mb-3">
                            Responsável
                            <select
                                class="aresta-input mt-1"
                                :value="selectedActivity.data.assigneeType ?? ''"
                                @change="updateActivity({ assignee_type: $event.target.value || null })"
                            >
                                <option value="">Nenhum</option>
                                <option value="role">Papel</option>
                                <option value="user">Usuário</option>
                            </select>
                        </label>
                        <label v-if="selectedActivity.data.assigneeType === 'role'" class="aresta-label mb-3">
                            Papel
                            <select
                                class="aresta-input mt-1"
                                :value="selectedActivity.data.assigneeRoleId ?? ''"
                                @change="updateActivity({ assignee_role_id: Number($event.target.value) })"
                            >
                                <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                            </select>
                        </label>
                        <label v-if="selectedActivity.data.assigneeType === 'user'" class="aresta-label mb-3">
                            Usuário
                            <select
                                class="aresta-input mt-1"
                                :value="selectedActivity.data.assigneeUserId ?? ''"
                                @change="updateActivity({ assignee_user_id: Number($event.target.value) })"
                            >
                                <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                            </select>
                        </label>
                        <label class="aresta-label mb-3">
                            SLA (horas)
                            <input
                                type="number"
                                class="aresta-input mt-1"
                                :value="selectedActivity.data.slaHours"
                                @change="updateActivity({ sla_hours: $event.target.value ? Number($event.target.value) : null })"
                            />
                        </label>
                    </template>

                    <template v-if="selectedActivity.data.type === 'automated_action'">
                        <label class="aresta-label mb-3">
                            Ação
                            <select
                                class="aresta-input mt-1"
                                :value="selectedActivity.data.config?.action ?? ''"
                                @change="updateActivity({ config: { ...selectedActivity.data.config, action: $event.target.value } })"
                            >
                                <option value="webhook">Webhook</option>
                                <option value="send_email">Enviar e-mail</option>
                                <option value="generate_document">Gerar documento</option>
                            </select>
                        </label>
                        <label v-if="selectedActivity.data.config?.action === 'webhook'" class="aresta-label mb-3">
                            URL
                            <input
                                class="aresta-input mt-1"
                                :value="selectedActivity.data.config?.url ?? ''"
                                @change="updateActivity({ config: { ...selectedActivity.data.config, url: $event.target.value, method: selectedActivity.data.config?.method ?? 'POST' } })"
                            />
                        </label>
                    </template>

                    <div class="mb-2 flex gap-4 text-xs">
                        <label class="flex items-center gap-1 text-ink">
                            <input
                                type="checkbox"
                                :checked="selectedActivity.data.isStart"
                                @change="updateActivity({ is_start: $event.target.checked })"
                            />
                            Início
                        </label>
                        <label class="flex items-center gap-1 text-ink">
                            <input
                                type="checkbox"
                                :checked="selectedActivity.data.isEnd"
                                @change="updateActivity({ is_end: $event.target.checked })"
                            />
                            Fim
                        </label>
                    </div>

                    <button type="button" class="mt-2 text-xs text-danger hover:underline" @click="deleteSelected">Excluir atividade</button>
                </div>

                <div v-else-if="selectedTransition">
                    <h2 class="mb-2 font-semibold text-ink">Transição</h2>
                    <label class="aresta-label mb-3">
                        Rótulo
                        <input
                            class="aresta-input mt-1"
                            :value="selectedTransition.label ?? ''"
                            @change="updateTransition({ label: $event.target.value || null })"
                        />
                    </label>
                    <label class="aresta-label mb-3">
                        Tipo
                        <select
                            class="aresta-input mt-1"
                            :value="selectedTransition.data.conditionType"
                            @change="updateTransition({ condition_type: $event.target.value })"
                        >
                            <option value="always">Sempre (paralelo/sequencial)</option>
                            <option value="expression">Condição</option>
                        </select>
                    </label>

                    <template v-if="selectedTransition.data.conditionType === 'expression'">
                        <p class="mb-1 text-[11px] text-ink/60">
                            Condição simples: campo, operador e valor — para combinar várias condições, edite via API por
                            enquanto (fora do escopo do MVP do builder visual).
                        </p>
                        <label class="aresta-label mb-3">
                            Campo
                            <input
                                class="aresta-input mt-1"
                                placeholder="context.valor_proposta"
                                :value="selectedTransition.data.conditionExpression?.field ?? ''"
                                @change="updateTransition({ condition_expression: { ...selectedTransition.data.conditionExpression, field: $event.target.value } })"
                            />
                        </label>
                        <label class="aresta-label mb-3">
                            Operador
                            <select
                                class="aresta-input mt-1"
                                :value="selectedTransition.data.conditionExpression?.operator ?? '='"
                                @change="updateTransition({ condition_expression: { ...selectedTransition.data.conditionExpression, operator: $event.target.value } })"
                            >
                                <option value="=">= igual</option>
                                <option value="!=">≠ diferente</option>
                                <option value=">">&gt; maior</option>
                                <option value=">=">&gt;= maior ou igual</option>
                                <option value="<">&lt; menor</option>
                                <option value="<=">&lt;= menor ou igual</option>
                            </select>
                        </label>
                        <label class="aresta-label mb-3">
                            Valor
                            <input
                                class="aresta-input mt-1"
                                :value="selectedTransition.data.conditionExpression?.value ?? ''"
                                @change="updateTransition({ condition_expression: { ...selectedTransition.data.conditionExpression, value: $event.target.value } })"
                            />
                        </label>
                    </template>

                    <button type="button" class="mt-2 text-xs text-danger hover:underline" @click="deleteSelected">Excluir transição</button>
                </div>
            </aside>
        </div>

        <AiRefineModal
            :show="showAiModal"
            :workflow="workflow"
            :version="version"
            @close="showAiModal = false"
        />
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
