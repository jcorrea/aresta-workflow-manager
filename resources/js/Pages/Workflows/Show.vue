<script setup>
import { Head, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

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

    <div class="min-h-screen bg-canvas">
        <div class="mx-auto max-w-3xl p-6">
            <a :href="route('workflows.index')" class="text-xs text-accent hover:underline">&larr; Workflows</a>

            <div class="mt-2 mb-4 flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-semibold text-ink">{{ workflow.name }}</h1>
                    <p v-if="workflow.description" class="text-sm text-ink/60">{{ workflow.description }}</p>
                </div>

                <a
                    v-if="workflow.draftVersionId"
                    :href="route('workflows.versions.edit', [workflow.id, workflow.draftVersionId])"
                    class="rounded bg-accent px-3 py-1 text-sm font-medium text-accent-ink hover:opacity-90"
                >
                    Continuar editando rascunho
                </a>
                <button
                    v-else
                    type="button"
                    class="rounded bg-accent px-3 py-1 text-sm font-medium text-accent-ink hover:opacity-90"
                    @click="editAgain"
                >
                    Editar de novo
                </button>
            </div>

            <h2 class="mb-2 text-sm font-semibold text-ink/80">Histórico de versões publicadas</h2>
            <ul class="divide-y divide-ink/10 rounded border border-ink/10 bg-panel">
                <li v-for="version in publishedVersions" :key="version.id" class="flex items-center justify-between px-4 py-3 text-sm">
                    <div>
                        <span class="font-medium text-ink">Versão {{ version.version_number }}</span>
                        <span class="ml-2 text-xs text-ink/60">{{ version.published_at }}</span>
                        <span v-if="version.id === workflow.currentPublishedVersionId" class="ml-2 rounded bg-accent/15 px-1 text-xs text-accent">
                            vigente
                        </span>
                    </div>
                    <button
                        v-if="version.id !== workflow.currentPublishedVersionId"
                        type="button"
                        class="text-xs text-accent hover:underline"
                        @click="rollbackTo(version.id)"
                    >
                        Reverter para esta versão
                    </button>
                </li>
                <li v-if="!publishedVersions.length" class="px-4 py-3 text-sm text-ink/60">Nenhuma versão publicada ainda.</li>
            </ul>
        </div>
    </div>
</template>
