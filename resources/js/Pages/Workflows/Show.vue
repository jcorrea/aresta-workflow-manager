<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppFooter from '@/Components/AppFooter.vue';
import AppNav from '@/Components/AppNav.vue';
import EmptyState from '@/Components/EmptyState.vue';

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
</script>

<template>
    <Head :title="workflow.name" />

    <div class="flex min-h-screen flex-col bg-canvas">
        <AppNav active="workflows" />

        <div class="mx-auto w-full max-w-4xl flex-1 p-6 space-y-6">
            <!-- Navegação / Breadcrumb -->
            <div>
                <Link
                    :href="route('workflows.index')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-ink/60 hover:text-ink transition-colors"
                >
                    <span>&larr;</span>
                    <span>Voltar para Workflows</span>
                </Link>
            </div>

            <!-- Card principal de detalhes do Workflow (padrão detalhes.html do starter kit) -->
            <div class="rounded-xl border border-ink/10 bg-panel p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-3.5 min-w-0 flex-1">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent/15 text-sm font-bold text-success mt-0.5">
                            {{ workflow.name.trim().charAt(0).toUpperCase() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <h1 class="text-xl font-bold tracking-tight text-ink break-words">{{ workflow.name }}</h1>
                            <p v-if="workflow.description" class="mt-1 text-sm text-ink/70 leading-relaxed max-w-2xl break-words">
                                {{ workflow.description }}
                            </p>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span v-if="workflow.currentPublishedVersionNumber" class="aresta-badge aresta-badge--success">
                                    Vigente: v{{ workflow.currentPublishedVersionNumber }}
                                </span>
                                <span v-else class="aresta-badge aresta-badge--neutral">
                                    Sem versão publicada
                                </span>

                                <span v-if="workflow.draftVersionId" class="aresta-badge aresta-badge--warning">
                                    Rascunho em edição
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Botão de Ação isolado para nunca colidir/encostar nos textos -->
                    <div class="shrink-0 sm:pt-0.5">
                        <Link
                            v-if="workflow.draftVersionId"
                            :href="route('workflows.versions.edit', [workflow.id, workflow.draftVersionId])"
                            class="inline-flex items-center justify-center rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink transition-opacity hover:opacity-90 shadow-sm whitespace-nowrap"
                        >
                            Continuar editando rascunho
                        </Link>
                        <button
                            v-else
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink transition-opacity hover:opacity-90 shadow-sm whitespace-nowrap"
                            @click="editAgain"
                        >
                            Editar de novo
                        </button>
                    </div>
                </div>

                <!-- Grid de Metadados (padrão detalhes.html) -->
                <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-ink/10 pt-4 sm:grid-cols-4">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-ink/50">Organização</dt>
                        <dd class="mt-1 text-xs font-medium text-ink">{{ workflow.organizationName ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-ink/50">Criado Por</dt>
                        <dd class="mt-1 text-xs font-medium text-ink">{{ workflow.createdByName ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-ink/50">Criado Em</dt>
                        <dd class="mt-1 text-xs font-medium text-ink">{{ workflow.createdAt ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-ink/50">Instâncias Executadas</dt>
                        <dd class="mt-1 text-xs font-medium text-ink">{{ workflow.instancesCount }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Seção de Histórico de Versões Publicadas -->
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-ink/80">Histórico de versões publicadas</h2>
                    <span v-if="publishedVersions.length" class="text-xs text-ink/50 font-mono">
                        {{ publishedVersions.length }} versão(ões)
                    </span>
                </div>

                <div v-if="publishedVersions.length" class="rounded-xl border border-ink/10 bg-panel p-6 shadow-sm">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="px-3 pb-3 text-left text-xs font-semibold uppercase tracking-wider text-ink/60">Versão</th>
                                <th class="px-3 pb-3 text-left text-xs font-semibold uppercase tracking-wider text-ink/60">Publicada em</th>
                                <th class="px-3 pb-3 text-left text-xs font-semibold uppercase tracking-wider text-ink/60">Status</th>
                                <th class="px-3 pb-3 text-right text-xs font-semibold uppercase tracking-wider text-ink/60">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="version in publishedVersions" :key="version.id">
                                <!-- Identificador numérico em fonte mono (DNA da marca) -->
                                <td class="border-t border-ink/10 px-3 py-3 font-mono text-sm font-semibold text-ink">
                                    v{{ version.version_number }}
                                </td>
                                <td class="border-t border-ink/10 px-3 py-3 text-sm text-ink/70">{{ version.published_at }}</td>
                                <td class="border-t border-ink/10 px-3 py-3 text-sm">
                                    <span
                                        v-if="version.id === workflow.currentPublishedVersionId"
                                        class="aresta-badge aresta-badge--success"
                                    >
                                        vigente
                                    </span>
                                </td>
                                <td class="whitespace-nowrap border-t border-ink/10 px-3 py-3 text-right text-sm">
                                    <button
                                        v-if="version.id !== workflow.currentPublishedVersionId"
                                        type="button"
                                        class="font-semibold text-accent hover:underline"
                                        @click="rollbackTo(version.id)"
                                    >
                                        Reverter para esta versão
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="mt-4 text-[13px] text-ink/60">
                        Mostrando 1–{{ publishedVersions.length }} de {{ publishedVersions.length }}
                    </div>
                </div>

                <EmptyState
                    v-else
                    title="Nenhuma versão publicada ainda"
                    text="Publique o rascunho no editor visual para a primeira versão aparecer aqui."
                />
            </div>
        </div>

        <AppFooter />
    </div>
</template>
