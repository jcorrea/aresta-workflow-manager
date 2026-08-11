<script setup>
import { reactive } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppFooter from '@/Components/AppFooter.vue';
import AppNav from '@/Components/AppNav.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    activities: { type: Array, required: true },
});

const formData = reactive({});

function selectOption(activity, field, value) {
    formData[activity.id] = { ...(formData[activity.id] ?? {}), [field.key]: value };
}

function claim(activity) {
    router.post(route('process-instance-activities.claim', activity.id));
}

function complete(activity) {
    const fields = activity.workflowActivity.fields;
    const payload = fields.length ? { form_data: formData[activity.id] ?? {} } : {};

    router.post(route('process-instance-activities.complete', activity.id), payload);
}
</script>

<template>
    <Head title="Minhas tarefas" />

    <div class="flex min-h-screen flex-col bg-canvas">
        <AppNav active="inbox" />

        <!-- Cards no vocabulário do starter kit (raio de card, sombra suave, badges pill,
             código de instância em mono) — tarefa com formulário embutido não vira tabela. -->
        <div class="mx-auto w-full max-w-6xl flex-1 p-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-bold tracking-tight text-ink">Minhas tarefas</h1>
            </div>

            <ul v-if="activities.length" class="space-y-3">
                <li v-for="activity in activities" :key="activity.id" class="rounded-xl border border-ink/10 bg-panel p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-ink">{{ activity.workflowActivity.name }}</p>
                            <p class="text-xs text-ink/60">
                                {{ activity.processInstance.name }}
                                <span class="font-mono text-[0.6875rem] text-ink/50">({{ activity.processInstance.code }})</span>
                            </p>
                            <p v-if="activity.dueAt" class="text-xs text-ink/40">Prazo: {{ activity.dueAt }}</p>
                            <span
                                v-if="activity.isQueued"
                                class="mt-1.5 inline-flex items-center rounded-full bg-warning-raw/15 px-2.5 py-0.5 text-xs font-semibold text-warning"
                            >
                                na fila
                            </span>
                        </div>

                        <button
                            v-if="activity.isQueued"
                            type="button"
                            class="rounded-lg border border-ink/10 px-3 py-1.5 text-xs font-semibold text-accent transition-colors hover:bg-accent/10"
                            @click="claim(activity)"
                        >
                            Assumir
                        </button>
                    </div>

                    <template v-if="!activity.isQueued">
                        <div v-if="activity.workflowActivity.fields.length" class="mt-4 space-y-3">
                            <div v-for="field in activity.workflowActivity.fields" :key="field.key" class="aresta-label">
                                {{ field.label }}

                                <div v-if="field.options" class="mt-1.5 flex flex-wrap gap-2">
                                    <button
                                        v-for="option in field.options"
                                        :key="option.value"
                                        type="button"
                                        class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors"
                                        :class="
                                            formData[activity.id]?.[field.key] === option.value
                                                ? 'border-accent bg-accent text-accent-ink'
                                                : 'border-ink/10 text-ink hover:bg-ink/5'
                                        "
                                        @click="selectOption(activity, field, option.value)"
                                    >
                                        {{ option.label }}
                                    </button>
                                </div>

                                <input
                                    v-else
                                    :type="field.type === 'number' ? 'number' : 'text'"
                                    :required="field.required"
                                    class="aresta-input mt-1.5"
                                    @input="
                                        formData[activity.id] = { ...(formData[activity.id] ?? {}), [field.key]: $event.target.value }
                                    "
                                />
                            </div>
                        </div>

                        <button
                            type="button"
                            class="mt-4 rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink transition-opacity hover:opacity-90"
                            @click="complete(activity)"
                        >
                            Concluir
                        </button>
                    </template>
                </li>
            </ul>

            <EmptyState
                v-else
                title="Nenhuma tarefa pendente"
                text="Quando uma atividade for atribuída a você ou à sua função, ela aparece aqui."
            />
        </div>

        <AppFooter />
    </div>
</template>
