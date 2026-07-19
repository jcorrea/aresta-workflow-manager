<script setup>
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    instances: { type: Array, required: true },
});
</script>

<template>
    <Head title="Instâncias" />

    <div class="min-h-screen bg-canvas">
        <div class="mx-auto max-w-3xl p-6">
            <h1 class="mb-4 text-lg font-semibold text-ink">Instâncias em andamento</h1>

            <ul class="divide-y divide-ink/10 rounded border border-ink/10 bg-panel">
                <li v-for="instance in instances" :key="instance.id" class="flex items-center justify-between px-4 py-3 text-sm">
                    <div>
                        <p class="font-medium text-ink">{{ instance.name }}</p>
                        <p class="text-xs text-ink/60">{{ instance.workflowName }} — {{ instance.code }} — {{ instance.status }}</p>
                    </div>
                    <a :href="route('process-instances.show', instance.code)" class="text-xs text-accent hover:underline">
                        Acompanhar
                    </a>
                </li>
                <li v-if="!instances.length" class="px-4 py-3 text-sm text-ink/60">Nenhuma instância ainda.</li>
            </ul>
        </div>
    </div>
</template>
