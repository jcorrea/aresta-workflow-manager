<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppFooter from '@/Components/AppFooter.vue';
import AppNav from '@/Components/AppNav.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    workflows: { type: Array, required: true },
    organizations: { type: Array, required: true },
});

const showFormModal = ref(false);
const form = useForm({
    organization_id: props.organizations[0]?.id ?? null,
    name: '',
    description: '',
});

const showDeleteModal = ref(false);
const workflowToDelete = ref(null);
const confirmCascadeCheckbox = ref(false);

const deleteForm = useForm({
    confirm_cascade: false,
});

function openModal() {
    showFormModal.value = true;
}

function closeModal() {
    showFormModal.value = false;
    form.reset();
    form.clearErrors();
}

function submit() {
    form.post(route('workflows.store'), {
        onSuccess: () => {
            closeModal();
        },
    });
}

function openDeleteModal(workflow) {
    workflowToDelete.value = workflow;
    confirmCascadeCheckbox.value = false;
    deleteForm.reset();
    deleteForm.clearErrors();
    showDeleteModal.value = true;
}

function closeDeleteModal() {
    showDeleteModal.value = false;
    workflowToDelete.value = null;
    confirmCascadeCheckbox.value = false;
    deleteForm.reset();
    deleteForm.clearErrors();
}

function confirmDelete() {
    if (!workflowToDelete.value) return;

    deleteForm
        .transform(() => ({
            confirm_cascade: confirmCascadeCheckbox.value || (workflowToDelete.value.instancesCount === 0),
        }))
        .delete(route('workflows.destroy', workflowToDelete.value.id), {
            onSuccess: () => {
                closeDeleteModal();
            },
        });
}
</script>

<template>
    <Head title="Workflows" />

    <div class="flex min-h-screen flex-col bg-canvas">
        <AppNav active="workflows" />

        <!-- Padrão de listagem do starter kit (docs/brand/components/listagem.html):
             título e "+ Novo" fora do card; tabela com avatar, badge e ações dentro. -->
        <div class="mx-auto w-full max-w-3xl flex-1 p-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-xl font-bold tracking-tight text-ink">Workflows</h1>
                <button
                    type="button"
                    class="rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink transition-opacity hover:opacity-90"
                    @click="openModal"
                >
                    + Novo workflow
                </button>
            </div>

            <Modal :show="showFormModal" @close="closeModal">
                <div class="mb-5 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-ink">Novo Workflow</h2>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-ink/60 hover:bg-ink/5 hover:text-ink transition-colors"
                        aria-label="Fechar"
                        @click="closeModal"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submit">
                    <label class="aresta-label mb-4">
                        Nome
                        <input v-model="form.name" class="aresta-input mt-1.5" placeholder="Ex.: Reembolso de Despesas" required autofocus />
                    </label>
                    <p v-if="form.errors.name" class="aresta-error-text mb-3">{{ form.errors.name }}</p>

                    <label v-if="organizations.length > 1" class="aresta-label mb-4">
                        Organização
                        <select v-model="form.organization_id" class="aresta-input mt-1.5">
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </label>
                    <p v-if="form.errors.organization_id" class="aresta-error-text mb-3">{{ form.errors.organization_id }}</p>

                    <label class="aresta-label mb-4">
                        Descrição <span class="font-normal text-ink/50">(opcional)</span>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="aresta-input mt-1.5"
                            placeholder="Ex.: processo de aprovação de reembolso de despesas, do envio do recibo até o pagamento."
                        ></textarea>
                        <span class="mt-1 block text-xs font-normal text-ink/60">
                            Se preenchida, a IA tenta montar um rascunho inicial das etapas do processo com base nesta
                            descrição (você pode ajustar tudo depois no editor). Deixe em branco para começar do zero.
                        </span>
                    </label>
                    <p v-if="form.errors.description" class="aresta-error-text mb-3">{{ form.errors.description }}</p>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-ink/15 px-4 py-2.5 text-sm font-semibold text-ink hover:bg-ink/5 transition-colors"
                            @click="closeModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink transition-opacity hover:opacity-90 disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{ form.processing && form.description ? 'Gerando processo com IA...' : 'Criar' }}
                        </button>
                    </div>
                </form>
            </Modal>

            <div v-if="workflows.length" class="rounded-xl border border-ink/10 bg-panel p-6 shadow-sm">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th class="px-3 pb-3 text-left text-xs font-semibold uppercase tracking-wider text-ink/60">Nome</th>
                            <th class="px-3 pb-3 text-left text-xs font-semibold uppercase tracking-wider text-ink/60">Status</th>
                            <th class="px-3 pb-3 text-right text-xs font-semibold uppercase tracking-wider text-ink/60">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="workflow in workflows" :key="workflow.id">
                            <td class="border-t border-ink/10 px-3 py-3 text-sm text-ink">
                                <div class="flex items-center gap-2.5">
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-[10px] bg-accent/15 text-xs font-bold text-success">
                                        {{ workflow.name.trim().charAt(0).toUpperCase() }}
                                    </span>
                                    <span class="font-medium">{{ workflow.name }}</span>
                                </div>
                            </td>
                            <td class="border-t border-ink/10 px-3 py-3 text-sm">
                                <span
                                    v-if="workflow.hasPublishedVersion"
                                    class="inline-flex items-center rounded-full bg-success/15 px-2.5 py-0.5 text-xs font-semibold text-success"
                                >
                                    Publicado
                                </span>
                                <span v-else class="inline-flex items-center rounded-full bg-ink/10 px-2.5 py-0.5 text-xs font-semibold text-ink/60">
                                    Sem versão publicada
                                </span>
                            </td>
                            <td class="whitespace-nowrap border-t border-ink/10 px-3 py-3 text-right text-sm">
                                <div class="flex items-center justify-end gap-3">
                                    <Link :href="route('workflows.show', workflow.id)" class="font-semibold text-accent hover:underline">Ver</Link>
                                    <button
                                        type="button"
                                        class="font-semibold text-danger hover:underline"
                                        @click="openDeleteModal(workflow)"
                                    >
                                        Excluir
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-4 text-[13px] text-ink/60">
                    Mostrando 1–{{ workflows.length }} de {{ workflows.length }}
                </div>
            </div>

            <EmptyState v-else title="Nenhum workflow ainda" text="Assim que um workflow for criado, ele aparece aqui.">
                <button
                    type="button"
                    class="rounded-lg bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink transition-opacity hover:opacity-90"
                    @click="openModal"
                >
                    + Novo workflow
                </button>
            </EmptyState>

            <Modal :show="showDeleteModal" @close="closeDeleteModal">
                <div v-if="workflowToDelete">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-ink">Excluir Workflow</h2>
                        <button
                            type="button"
                            class="rounded-lg p-1.5 text-ink/60 hover:bg-ink/5 hover:text-ink transition-colors"
                            aria-label="Fechar"
                            @click="closeDeleteModal"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div v-if="workflowToDelete.instancesCount > 0" class="mb-4 rounded-xl border border-warning/30 bg-warning/10 p-3.5 text-xs text-ink">
                        <div class="flex items-start gap-2.5">
                            <span class="text-base leading-none">⚠️</span>
                            <div>
                                <p class="font-bold text-warning-ink">Atenção: Este workflow possui instâncias executadas!</p>
                                <p class="mt-1 text-ink/80">
                                    Existem <strong>{{ workflowToDelete.instancesCount }}</strong> instância(s) de processo executadas ou em andamento associadas a este workflow.
                                </p>
                            </div>
                        </div>
                    </div>

                    <p class="mb-4 text-sm text-ink/80">
                        Tem certeza que deseja excluir o workflow <strong class="text-ink">{{ workflowToDelete.name }}</strong>?
                    </p>

                    <div v-if="workflowToDelete.instancesCount > 0" class="mb-4 rounded-lg border border-ink/10 bg-canvas p-3 text-xs">
                        <label class="flex items-start gap-2 cursor-pointer select-none">
                            <input
                                v-model="confirmCascadeCheckbox"
                                type="checkbox"
                                class="mt-0.5 rounded border-ink/20 text-accent focus:ring-accent"
                            />
                            <span class="text-ink/90 font-medium">
                                Confirmar exclusão completa incluindo todo o histórico de {{ workflowToDelete.instancesCount }} instância(s) executada(s).
                            </span>
                        </label>
                    </div>

                    <p v-if="deleteForm.errors.confirm_cascade" class="aresta-error-text mb-3 text-xs text-danger font-medium">
                        {{ deleteForm.errors.confirm_cascade }}
                    </p>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-ink/15 px-4 py-2.5 text-sm font-semibold text-ink hover:bg-ink/5 transition-colors"
                            :disabled="deleteForm.processing"
                            @click="closeDeleteModal"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            class="rounded-lg bg-danger px-5 py-2.5 text-sm font-semibold text-white transition-opacity hover:opacity-90 disabled:opacity-50"
                            :disabled="deleteForm.processing || (workflowToDelete.instancesCount > 0 && !confirmCascadeCheckbox)"
                            @click="confirmDelete"
                        >
                            {{ deleteForm.processing ? 'Excluindo...' : (workflowToDelete.instancesCount > 0 ? 'Excluir com Histórico' : 'Excluir Workflow') }}
                        </button>
                    </div>
                </div>
            </Modal>
        </div>

        <AppFooter />
    </div>
</template>
