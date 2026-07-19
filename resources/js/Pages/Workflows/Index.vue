<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppNav from '@/Components/AppNav.vue';

const props = defineProps({
    workflows: { type: Array, required: true },
    organizations: { type: Array, required: true },
});

const showForm = ref(false);
const form = useForm({
    organization_id: props.organizations[0]?.id ?? null,
    name: '',
});

function submit() {
    form.post(route('workflows.store'));
}
</script>

<template>
    <Head title="Workflows" />

    <div class="min-h-screen bg-canvas">
        <AppNav active="workflows" />

        <div class="mx-auto max-w-3xl p-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-lg font-semibold text-ink">Workflows</h1>
                <button
                    type="button"
                    class="rounded bg-accent px-3 py-1 text-sm font-medium text-accent-ink hover:opacity-90"
                    @click="showForm = !showForm"
                >
                    Novo workflow
                </button>
            </div>

            <form v-if="showForm" class="mb-6 rounded border border-ink/10 bg-panel p-4" @submit.prevent="submit">
                <label class="mb-2 block text-sm text-ink">
                    Nome
                    <input v-model="form.name" class="mt-1 w-full rounded border border-ink/15 bg-canvas px-2 py-1 text-ink" required />
                </label>
                <label v-if="organizations.length > 1" class="mb-2 block text-sm text-ink">
                    Organização
                    <select v-model="form.organization_id" class="mt-1 w-full rounded border border-ink/15 bg-canvas px-2 py-1 text-ink">
                        <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                    </select>
                </label>
                <button
                    type="submit"
                    class="mt-2 rounded bg-accent px-3 py-1 text-sm font-medium text-accent-ink hover:opacity-90 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    Criar
                </button>
            </form>

            <ul class="divide-y divide-ink/10 rounded border border-ink/10 bg-panel">
                <li v-for="workflow in workflows" :key="workflow.id" class="flex items-center justify-between px-4 py-3">
                    <div>
                        <p class="text-sm font-medium text-ink">{{ workflow.name }}</p>
                        <p class="text-xs text-ink/60">
                            {{ workflow.hasPublishedVersion ? 'Publicado' : 'Sem versão publicada' }}
                        </p>
                    </div>
                    <a :href="route('workflows.show', workflow.id)" class="text-sm text-accent hover:underline">Ver</a>
                </li>
                <li v-if="!workflows.length" class="px-4 py-3 text-sm text-ink/60">Nenhum workflow ainda.</li>
            </ul>
        </div>
    </div>
</template>
