<script setup>
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    instances: { type: Array, required: true },
});
</script>

<template>
    <Head title="Instâncias" />

    <div class="mx-auto max-w-3xl p-6">
        <h1 class="mb-4 text-lg font-semibold text-gray-800">Instâncias em andamento</h1>

        <ul class="divide-y rounded border bg-white">
            <li v-for="instance in instances" :key="instance.id" class="flex items-center justify-between px-4 py-3 text-sm">
                <div>
                    <p class="font-medium text-gray-800">{{ instance.name }}</p>
                    <p class="text-xs text-gray-500">{{ instance.workflowName }} — {{ instance.code }} — {{ instance.status }}</p>
                </div>
                <a :href="route('process-instances.show', instance.code)" class="text-xs text-indigo-600 hover:underline">
                    Acompanhar
                </a>
            </li>
            <li v-if="!instances.length" class="px-4 py-3 text-sm text-gray-500">Nenhuma instância ainda.</li>
        </ul>
    </div>
</template>
