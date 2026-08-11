<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppFooter from '@/Components/AppFooter.vue';
import AppNav from '@/Components/AppNav.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    workflow: { type: Object, required: true },
    publishedVersions: { type: Array, required: true },
});

function editAgain() {
    router.post(route('workflows.versions.store', props.workflow.id));
}

function rollbackTo(versionId) {
    router.post(route('workflows.rollback', props.workflow.id), { workflow_version_id: versionId });
}

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
</script>

<template>
    <Head :title="workflow.name" />

    <div class="flex min-h-screen flex-col bg-canvas text-ink">
        <AppNav active="workflows" />

        <main class="mx-auto w-full max-w-6xl flex-1 p-6 space-y-6">
            <!-- Navegação / Breadcrumb & Ações secundárias -->
            <div class="flex items-center justify-between">
                <Link
                    :href="route('workflows.index')"
                    class="inline-flex items-center gap-2 text-xs font-medium text-ink/60 hover:text-ink transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    <span>Voltar para a lista de Workflows</span>
                </Link>

                <Link
                    :href="route('workflows.integration-instructions', workflow.id)"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-accent hover:underline"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                    <span>Instruções de API / Integração</span>
                </Link>
            </div>

            <!-- Header Hero de Detalhes do Workflow -->
            <div class="relative overflow-hidden rounded-2xl border border-ink/10 bg-panel p-6 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-start gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-accent/15 text-xl font-bold text-accent shadow-inner">
                            {{ workflow.name.trim().charAt(0).toUpperCase() }}
                        </div>
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h1 class="text-2xl font-bold tracking-tight text-ink">{{ workflow.name }}</h1>
                                <span v-if="workflow.currentPublishedVersionNumber" class="aresta-badge aresta-badge--success">
                                    Vigente: v{{ workflow.currentPublishedVersionNumber }}
                                </span>
                                <span v-else class="aresta-badge aresta-badge--neutral">
                                    Sem versão publicada
                                </span>
                            </div>
                            <p v-if="workflow.description" class="text-sm text-ink/70 leading-relaxed max-w-3xl">
                                {{ workflow.description }}
                            </p>
                            <div class="flex flex-wrap items-center gap-4 text-xs text-ink/50 pt-1">
                                <span v-if="workflow.organizationName" class="flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    {{ workflow.organizationName }}
                                </span>
                                <span v-if="workflow.createdByName" class="flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                    Criado por {{ workflow.createdByName }}
                                </span>
                                <span v-if="workflow.createdAt" class="flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    Criado em {{ workflow.createdAt }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Botões Principais de Ação -->
                    <div class="flex flex-wrap items-center md:flex-col lg:flex-row gap-3 shrink-0">
                        <button
                            v-if="workflow.currentPublishedVersionNumber"
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-ink/15 bg-canvas px-5 py-2.5 text-sm font-semibold text-ink transition-colors hover:bg-ink/5 shadow-sm"
                            @click="openStartModal"
                        >
                            <svg class="h-4 w-4 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd" />
                            </svg>
                            <span>Iniciar instância (teste)</span>
                        </button>

                        <Link
                            v-if="workflow.draftVersionId"
                            :href="route('workflows.versions.edit', [workflow.id, workflow.draftVersionId])"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink transition-all hover:opacity-95 shadow-sm hover:shadow"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            <span>Continuar editando rascunho</span>
                        </Link>
                        <button
                            v-else
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink transition-all hover:opacity-95 shadow-sm hover:shadow"
                            @click="editAgain"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Criar novo rascunho</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Banner Alerta de Rascunho Ativo -->
            <div v-if="workflow.draftVersionId" class="flex items-center justify-between gap-4 rounded-xl border border-amber-500/20 bg-amber-500/10 p-4 text-amber-900 dark:text-amber-200">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-600 dark:text-amber-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold">Existe um rascunho em edição para este workflow</h4>
                        <p class="text-xs opacity-80">As alterações do rascunho não afetam as instâncias em andamento até que seja publicado.</p>
                    </div>
                </div>
                <Link
                    :href="route('workflows.versions.edit', [workflow.id, workflow.draftVersionId])"
                    class="shrink-0 rounded-lg bg-amber-500 px-3.5 py-1.5 text-xs font-bold text-amber-950 hover:bg-amber-400 transition-colors"
                >
                    Abrir Editor Visual
                </Link>
            </div>

            <!-- Grid de Métricas do Workflow -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="rounded-xl border border-ink/10 bg-panel p-5 space-y-1 shadow-sm">
                    <span class="text-xs font-semibold text-ink/60 uppercase tracking-wider">Instâncias Executadas</span>
                    <div class="text-2xl font-black text-ink">{{ workflow.instancesCount }}</div>
                    <p class="text-xs text-ink/50">Total de processos iniciados</p>
                </div>

                <div class="rounded-xl border border-ink/10 bg-panel p-5 space-y-1 shadow-sm">
                    <span class="text-xs font-semibold text-ink/60 uppercase tracking-wider">Versão Vigente</span>
                    <div class="text-2xl font-black text-ink">
                        {{ workflow.currentPublishedVersionNumber ? `v${workflow.currentPublishedVersionNumber}` : 'Nenhuma' }}
                    </div>
                    <p class="text-xs text-ink/50">Utilizada para novas instâncias</p>
                </div>

                <div class="rounded-xl border border-ink/10 bg-panel p-5 space-y-1 shadow-sm">
                    <span class="text-xs font-semibold text-ink/60 uppercase tracking-wider">Publicações</span>
                    <div class="text-2xl font-black text-ink">{{ publishedVersions.length }}</div>
                    <p class="text-xs text-ink/50">Versões no histórico</p>
                </div>
            </div>

            <!-- Histórico de Versões & Detalhes -->
            <div class="rounded-2xl border border-ink/10 bg-panel p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-ink/10 pb-4">
                    <div>
                        <h2 class="text-lg font-bold text-ink">Histórico de Versões Publicadas</h2>
                        <p class="text-xs text-ink/60">Todas as versões publicadas deste processo com a opção de rollback.</p>
                    </div>
                </div>

                <div v-if="publishedVersions.length > 0" class="space-y-3">
                    <div
                        v-for="version in publishedVersions"
                        :key="version.id"
                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-ink/10 bg-canvas/50 p-4 transition-colors hover:bg-canvas"
                    >
                        <div class="flex items-center gap-3.5">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                                :class="version.id === workflow.currentPublishedVersionId ? 'bg-success/20 text-success' : 'bg-ink/10 text-ink/70'"
                            >
                                v{{ version.version_number }}
                            </span>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-sm text-ink">Versão {{ version.version_number }}</span>
                                    <span v-if="version.id === workflow.currentPublishedVersionId" class="aresta-badge aresta-badge--success text-[10px]">
                                        Vigente Atual
                                    </span>
                                </div>
                                <p class="text-xs text-ink/60 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                    <span>Publicada em {{ version.published_at || 'Data não disponível' }}</span>
                                    <span v-if="version.published_by_name" class="text-ink/40">•</span>
                                    <span v-if="version.published_by_name" class="font-medium text-ink/70">
                                        por {{ version.published_by_name }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 self-end sm:self-auto">
                            <button
                                v-if="version.id !== workflow.currentPublishedVersionId"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-ink/15 px-3 py-1.5 text-xs font-semibold text-ink hover:bg-ink/5 transition-colors"
                                @click="rollbackTo(version.id)"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                </svg>
                                <span>Restaurar esta versão</span>
                            </button>
                        </div>
                    </div>
                </div>

                <EmptyState
                    v-else
                    title="Nenhuma versão publicada"
                    description="Edite o rascunho deste workflow e publique a primeira versão para permitir a criação de instâncias."
                />
            </div>
        </main>

        <!-- Modal de Iniciar Instância de Teste (Padrão NATIVO da Aplicação) -->
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
                Cria uma instância real a partir da versão vigente (v{{ workflow.currentPublishedVersionNumber }}), pra você
                acompanhar o andamento e concluir as atividades pela tela de Minhas Tarefas.
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

        <AppFooter />
    </div>
</template>
