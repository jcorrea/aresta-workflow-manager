<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    activities: { type: Array, required: true },
});

const formData = reactive({});

function fieldsFor(activity) {
    return activity.workflowActivity.type === 'form' ? (activity.workflowActivity.config?.fields ?? []) : [];
}

function claim(activity) {
    router.post(route('process-instance-activities.claim', activity.id));
}

function complete(activity) {
    const fields = fieldsFor(activity);
    const payload = fields.length ? { form_data: formData[activity.id] ?? {} } : {};

    router.post(route('process-instance-activities.complete', activity.id), payload);
}
</script>

<template>
    <Head title="Minhas tarefas" />

    <div class="min-h-screen bg-canvas">
        <div class="mx-auto max-w-3xl p-6">
            <h1 class="mb-4 text-lg font-semibold text-ink">Minhas tarefas</h1>

            <ul class="space-y-3">
                <li v-for="activity in activities" :key="activity.id" class="rounded border border-ink/10 bg-panel p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium text-ink">{{ activity.workflowActivity.name }}</p>
                            <p class="text-xs text-ink/60">
                                {{ activity.processInstance.name }} ({{ activity.processInstance.code }})
                            </p>
                            <p v-if="activity.dueAt" class="text-xs text-ink/40">Prazo: {{ activity.dueAt }}</p>
                            <span v-if="activity.isQueued" class="mt-1 inline-block rounded bg-amber-500/15 px-1 text-[10px] text-amber-600">
                                na fila
                            </span>
                        </div>

                        <button
                            v-if="activity.isQueued"
                            type="button"
                            class="rounded border border-ink/15 px-2 py-1 text-xs text-accent hover:bg-accent/10"
                            @click="claim(activity)"
                        >
                            Assumir
                        </button>
                    </div>

                    <template v-if="!activity.isQueued">
                        <div v-if="fieldsFor(activity).length" class="mt-3 space-y-2">
                            <label v-for="field in fieldsFor(activity)" :key="field.key" class="block text-xs text-ink">
                                {{ field.label }}
                                <input
                                    :type="field.type === 'number' ? 'number' : 'text'"
                                    :required="field.required"
                                    class="mt-1 w-full rounded border border-ink/15 bg-canvas px-2 py-1 text-ink"
                                    @input="
                                        formData[activity.id] = { ...(formData[activity.id] ?? {}), [field.key]: $event.target.value }
                                    "
                                />
                            </label>
                        </div>

                        <button
                            type="button"
                            class="mt-3 rounded bg-accent px-3 py-1 text-xs font-medium text-accent-ink hover:opacity-90"
                            @click="complete(activity)"
                        >
                            Concluir
                        </button>
                    </template>
                </li>

                <li v-if="!activities.length" class="rounded border border-ink/10 bg-panel p-4 text-sm text-ink/60">
                    Nenhuma tarefa pendente.
                </li>
            </ul>
        </div>
    </div>
</template>
