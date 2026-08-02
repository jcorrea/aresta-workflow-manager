<script setup>
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppFooter from '@/Components/AppFooter.vue';
import AppNav from '@/Components/AppNav.vue';
import EmptyState from '@/Components/EmptyState.vue';

defineProps({
    instances: { type: Array, required: true },
});

// Badges no padrão do starter kit: verde = em andamento, neutro = concluída, vermelho = cancelada.
function statusBadgeClass(status) {
    if (status === 'completed') {
        return 'bg-ink/10 text-ink/60';
    }
    if (status === 'cancelled') {
        return 'bg-danger/15 text-danger';
    }
    return 'bg-success/15 text-success';
}
</script>

<template>
    <Head title="Instâncias" />

    <div class="flex min-h-screen flex-col bg-canvas">
        <AppNav active="instances" />

        <!-- Padrão de listagem do starter kit (docs/brand/components/listagem.html). -->
        <div class="mx-auto w-full max-w-3xl flex-1 p-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-bold tracking-tight text-ink">Instâncias em andamento</h1>
            </div>

            <div v-if="instances.length" class="rounded-xl border border-ink/10 bg-panel p-6 shadow-sm">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="px-3 pb-3 text-left text-xs font-semibold uppercase tracking-wider text-ink/60">Nome</th>
                            <th class="px-3 pb-3 text-left text-xs font-semibold uppercase tracking-wider text-ink/60">Código</th>
                            <th class="px-3 pb-3 text-left text-xs font-semibold uppercase tracking-wider text-ink/60">Status</th>
                            <th class="px-3 pb-3 text-right text-xs font-semibold uppercase tracking-wider text-ink/60">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="instance in instances" :key="instance.id">
                            <td class="border-t border-ink/10 px-3 py-3 text-sm text-ink">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-[10px] bg-accent/15 text-xs font-bold text-success">
                                        {{ instance.name.trim().charAt(0).toUpperCase() }}
                                    </span>
                                    <div>
                                        <p class="font-medium">{{ instance.name }}</p>
                                        <p class="text-xs text-ink/60">{{ instance.workflowName }}</p>
                                    </div>
                                </div>
                            </td>
                            <!-- Identificador de sistema em fonte mono (DNA da marca). -->
                            <td class="border-t border-ink/10 px-3 py-3 font-mono text-xs text-ink/70">{{ instance.code }}</td>
                            <td class="border-t border-ink/10 px-3 py-3 text-sm">
                                <span
                                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="statusBadgeClass(instance.status)"
                                >
                                    {{ instance.status }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap border-t border-ink/10 px-3 py-3 text-right text-sm">
                                <a :href="route('process-instances.show', instance.code)" class="font-semibold text-accent hover:underline">
                                    Acompanhar
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-4 text-[13px] text-ink/60">
                    Mostrando 1–{{ instances.length }} de {{ instances.length }}
                </div>
            </div>

            <EmptyState
                v-else
                title="Nenhuma instância ainda"
                text="Quando um processo for iniciado a partir de um workflow publicado, a instância aparece aqui."
            />
        </div>

        <AppFooter />
    </div>
</template>
