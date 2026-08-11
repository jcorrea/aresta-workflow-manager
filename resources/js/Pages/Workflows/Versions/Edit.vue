<script setup>
import { ref, computed, watch, toRaw } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import { route } from 'ziggy-js';
import axios from 'axios';
import ActivityNode from '@/Components/Workflow/ActivityNode.vue';
import StepNode from '@/Components/Workflow/StepNode.vue';
import AiRefineModal from '@/Components/Workflow/AiRefineModal.vue';
import AppNav from '@/Components/AppNav.vue';
import Modal from '@/Components/Modal.vue';

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

const showStartModal = ref(false);
const startForm = useForm({
    name: '',
    context: '',
});

function openStartModal() {
    showStartModal.value = true;
}

function closeStartModal() {
    showStartModal.value = false;
    startForm.reset();
    startForm.clearErrors();
}

function submitStart() {
    startForm.post(route('process-instances.store', props.workflow.id));
}

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

// Pré-preenche o modal de IA com os erros do WorkflowGraphValidator quando existem, pra virar um
// atalho de correção em vez de só refinamento livre — mas o texto continua editável, o usuário
// confirma antes de enviar (ver docs/specs/06-refinamento-ia-editor.md).
const suggestedFixInstructions = computed(() => {
    if (!errorIssues.value.length) return '';

    const header = 'Corrija os seguintes problemas estruturais do processo, sem alterar o restante do fluxo além do necessário para resolvê-los:';
    const lines = errorIssues.value.map((issue) => `- ${issue.message}`);

    let text = `${header}\n${lines.join('\n')}`;
    const MAX_LENGTH = 1900; // backend valida instructions com max:2000 (WorkflowVersionController)

    if (text.length > MAX_LENGTH) {
        let truncated = [];
        let length = header.length + 1;
        for (const line of lines) {
            if (length + line.length + 1 > MAX_LENGTH) break;
            truncated.push(line);
            length += line.length + 1;
        }
        text = `${header}\n${truncated.join('\n')}\n- ...e mais ${lines.length - truncated.length} problema(s).`;
    }

    return text;
});

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

function addActivityField() {
    const fields = [...(selectedActivity.value.data.config?.fields ?? []), { key: '', label: '', type: 'text', required: false }];
    updateActivity({ config: { ...selectedActivity.value.data.config, fields } });
}

function updateActivityField(index, patch) {
    const fields = [...(selectedActivity.value.data.config?.fields ?? [])];
    fields[index] = { ...fields[index], ...patch };
    updateActivity({ config: { ...selectedActivity.value.data.config, fields } });
}

function removeActivityField(index) {
    const fields = (selectedActivity.value.data.config?.fields ?? []).filter((_, i) => i !== index);
    updateActivity({ config: { ...selectedActivity.value.data.config, fields } });
}

async function updateTransition(patch) {
    const transition = selectedTransition.value;
    if (!transition) return;

    // `transition.data` guarda as chaves em camelCase (mesmo formato de `toGraphPayload()`), mas
    // a API espera snake_case — traduzir aqui, num único lugar, evita que cada @change do
    // template precise acertar as duas grafias (era exatamente esse descompasso que fazia o
    // patch gravar em `data.condition_expression`, uma chave nova, enquanto os inputs liam de
    // `data.conditionExpression`: parecia não salvar, e cada edição subsequente reconstruía o
    // JSON a partir da cópia camelCase nunca atualizada, apagando o que tinha sido digitado antes).
    const apiPatch = {};
    if ('label' in patch) apiPatch.label = patch.label;
    if ('conditionType' in patch) apiPatch.condition_type = patch.conditionType;
    if ('conditionExpression' in patch) apiPatch.condition_expression = patch.conditionExpression;

    await axios.patch(route('workflow-transitions.update', transition.data.id), apiPatch);
    Object.assign(transition.data, patch);
    if ('label' in patch) transition.label = patch.label;
}

// O `<select>` de Operador mostra "= igual" pré-selecionado mesmo quando nada foi salvo ainda —
// sem esse default explícito, editar só Campo ou só Valor grava um `condition_expression` sem
// `operator`, e o motor trata `operator` ausente como "nunca bate" (ConditionEvaluator::evaluate).
// Mesclar os defaults antes do que já foi salvo garante que a primeira edição de qualquer um dos
// três campos já grava os três, refletindo o que a tela mostra.
function conditionExpressionOf(transition) {
    return { field: '', operator: '=', value: '', ...(transition?.data?.conditionExpression ?? {}) };
}

function onConditionTypeChange(value) {
    const patch = { conditionType: value };
    if (value === 'expression' && !selectedTransition.value?.data?.conditionExpression) {
        patch.conditionExpression = conditionExpressionOf(selectedTransition.value);
    }
    updateTransition(patch);
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
                    v-if="workflow.currentPublishedVersionNumber"
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg border border-ink/10 px-3 py-1.5 text-xs font-semibold text-ink transition-colors hover:bg-ink/5"
                    @click="openStartModal"
                >
                    <span>▶️</span>
                    <span>Iniciar instância (teste)</span>
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="errorIssues.length
                        ? 'border-danger/30 bg-danger/10 text-danger hover:bg-danger/20'
                        : 'border-accent/30 bg-accent/10 text-accent hover:bg-accent/20'"
                    @click="showAiModal = true"
                >
                    <span>{{ errorIssues.length ? '🛠️' : '✨' }}</span>
                    <span>{{ errorIssues.length ? 'Corrigir Erros com IA' : 'Ajustar com IA' }}</span>
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
                                <option value="external">Externo (fora do Aresta)</option>
                            </select>
                        </label>
                        <p v-if="selectedActivity.data.assigneeType === 'external'" class="mb-3 text-xs text-ink/60">
                            Só pode ser concluída via API por um sistema externo — ninguém assume ou
                            completa pela Inbox do Aresta.
                        </p>
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

                    <template v-if="selectedActivity.data.type === 'form'">
                        <p class="aresta-label mb-2">Campos do formulário</p>
                        <div
                            v-for="(field, index) in selectedActivity.data.config?.fields ?? []"
                            :key="index"
                            class="mb-2 space-y-1.5 rounded-lg border border-ink/10 p-2"
                        >
                            <input
                                class="aresta-input"
                                placeholder="Chave (ex.: decisao)"
                                :value="field.key"
                                @change="updateActivityField(index, { key: $event.target.value })"
                            />
                            <input
                                class="aresta-input"
                                placeholder="Rótulo (ex.: Decisão)"
                                :value="field.label"
                                @change="updateActivityField(index, { label: $event.target.value })"
                            />
                            <div class="flex items-center gap-2">
                                <select
                                    class="aresta-input"
                                    :value="field.type ?? 'text'"
                                    @change="updateActivityField(index, { type: $event.target.value })"
                                >
                                    <option value="text">Texto</option>
                                    <option value="number">Número</option>
                                </select>
                                <label class="flex items-center gap-1 text-xs text-ink/70">
                                    <input
                                        type="checkbox"
                                        :checked="field.required"
                                        @change="updateActivityField(index, { required: $event.target.checked })"
                                    />
                                    Obrigatório
                                </label>
                                <button type="button" class="text-xs text-danger hover:underline" @click="removeActivityField(index)">
                                    Remover
                                </button>
                            </div>
                        </div>
                        <button type="button" class="mb-3 text-xs text-accent hover:underline" @click="addActivityField">+ Campo</button>
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
                            @change="onConditionTypeChange($event.target.value)"
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
                                :value="conditionExpressionOf(selectedTransition).field"
                                @change="updateTransition({ conditionExpression: { ...conditionExpressionOf(selectedTransition), field: $event.target.value } })"
                            />
                        </label>
                        <label class="aresta-label mb-3">
                            Operador
                            <select
                                class="aresta-input mt-1"
                                :value="conditionExpressionOf(selectedTransition).operator"
                                @change="updateTransition({ conditionExpression: { ...conditionExpressionOf(selectedTransition), operator: $event.target.value } })"
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
                                :value="conditionExpressionOf(selectedTransition).value"
                                @change="updateTransition({ conditionExpression: { ...conditionExpressionOf(selectedTransition), value: $event.target.value } })"
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
            :initial-instructions="suggestedFixInstructions"
            @close="showAiModal = false"
        />

        <!-- Modal de Iniciar Instância de Teste — mesmo padrão de Workflows/Show.vue -->
        <Modal :show="showStartModal" maxWidth="lg" @close="closeStartModal">
            <div class="mb-5 flex items-center justify-between">
                <h2 class="text-lg font-bold text-ink">Iniciar instância (teste)</h2>
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-ink/60 hover:bg-ink/5 hover:text-ink transition-colors"
                    aria-label="Fechar"
                    @click="closeStartModal"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <p class="mb-4 text-xs text-ink/60">
                Cria uma instância real a partir da versão vigente (v{{ workflow.currentPublishedVersionNumber }}), não do
                rascunho que você está editando agora — alterações do rascunho só valem depois de publicadas.
            </p>

            <form @submit.prevent="submitStart">
                <label class="aresta-label mb-4">
                    Nome da instância <span class="font-normal text-ink/50">(opcional)</span>
                    <input v-model="startForm.name" class="aresta-input mt-1.5" :placeholder="workflow.name" autofocus />
                </label>
                <p v-if="startForm.errors.name" class="aresta-error-text mb-3">{{ startForm.errors.name }}</p>

                <label class="aresta-label mb-4">
                    Dados iniciais <span class="font-normal text-ink/50">(JSON opcional, usado por condições)</span>
                    <textarea
                        v-model="startForm.context"
                        rows="4"
                        class="aresta-input mt-1.5 font-mono text-xs"
                        placeholder='Ex.: {"valor": 500}'
                    ></textarea>
                </label>
                <p v-if="startForm.errors.context" class="aresta-error-text mb-3">{{ startForm.errors.context }}</p>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-lg border border-ink/15 px-4 py-2.5 text-sm font-semibold text-ink hover:bg-ink/5 transition-colors"
                        @click="closeStartModal"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink transition-opacity hover:opacity-90 disabled:opacity-50"
                        :disabled="startForm.processing"
                    >
                        Iniciar
                    </button>
                </div>
            </form>
        </Modal>
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
