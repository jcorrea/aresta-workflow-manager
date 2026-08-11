<script setup>
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    workflow: { type: Object, required: true },
    version: { type: Object, required: true },
    initialInstructions: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const form = useForm({
    description: props.workflow.description ?? '',
    instructions: '',
});

watch(
    () => props.show,
    (isShown) => {
        if (isShown) {
            form.description = props.workflow.description ?? '';
            form.instructions = props.initialInstructions || '';
            form.clearErrors();
        }
    }
);

function closeModal() {
    emit('close');
}

function submit() {
    form.post(route('workflows.versions.refine-ai', [props.workflow.id, props.version.id]), {
        onSuccess: () => {
            closeModal();
        },
    });
}
</script>

<template>
    <Modal :show="show" @close="closeModal">
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-accent/15 text-accent font-bold">✨</span>
                <div>
                    <h2 class="text-base font-bold text-ink">{{ initialInstructions ? 'Corrigir Erros com IA' : 'Ajustar Workflow com IA' }}</h2>
                    <p class="text-xs text-ink/60">
                        {{
                            initialInstructions
                                ? 'Revise a correção sugerida antes de aplicar'
                                : 'Refine a estrutura, etapas, transições ou papéis em linguagem natural'
                        }}
                    </p>
                </div>
            </div>
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
            <label class="aresta-label mb-3 block">
                Descrição do Processo <span class="font-normal text-ink/50">(base)</span>
                <textarea
                    v-model="form.description"
                    rows="2"
                    class="aresta-input mt-1 w-full text-xs"
                    placeholder="Ex.: Processo de aprovação de despesas de viagem..."
                ></textarea>
            </label>

            <label class="aresta-label mb-3 block">
                Instruções de Ajuste ou Melhoria <span class="text-danger">*</span>
                <textarea
                    v-model="form.instructions"
                    rows="4"
                    class="aresta-input mt-1 w-full text-xs"
                    placeholder="Ex.: Adicionar uma etapa de aprovação da Diretoria para valores acima de R$ 50 mil. Trocar o responsável da verificação para a equipe de Compliance."
                    required
                    autofocus
                ></textarea>
                <span class="mt-1 block text-[11px] font-normal text-ink/60">
                    Descreva em português o que deve ser modificado, adicionado ou removido no fluxo atual.
                </span>
            </label>
            <p v-if="form.errors.instructions" class="aresta-error-text mb-3 text-xs text-danger font-medium">{{ form.errors.instructions }}</p>

            <div class="mt-5 flex items-center justify-end gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-ink/15 px-4 py-2 text-xs font-semibold text-ink hover:bg-ink/5 transition-colors"
                    :disabled="form.processing"
                    @click="closeModal"
                >
                    Cancelar
                </button>
                <button
                    type="submit"
                    class="flex items-center gap-1.5 rounded-lg bg-accent px-4 py-2 text-xs font-semibold text-accent-ink transition-opacity hover:opacity-90 disabled:opacity-50"
                    :disabled="form.processing || !form.instructions.trim()"
                >
                    <span>✨</span>
                    <span>{{ form.processing ? 'Refinando com IA...' : 'Refinar Workflow' }}</span>
                </button>
            </div>
        </form>
    </Modal>
</template>
