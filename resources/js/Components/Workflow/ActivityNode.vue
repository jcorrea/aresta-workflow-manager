<script setup>
import { computed } from 'vue';
import { Handle, Position } from '@vue-flow/core';

const props = defineProps({
    data: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

// Tipo de nó não é mais distinguido por cor (o brandbook rejeita paleta multi-colorida —
// docs/specs/05-identidade-visual.md §7.3): ícone + forma (losango para `condition`, já abaixo)
// cumprem esse papel; cor fica reservada ao acento da marca e ao estado de execução.
const typeIcons = {
    task: '👤',
    form: '📝',
    automated_action: '⚡',
    condition: '◆',
};

const icon = computed(() => typeIcons[props.data.type] ?? typeIcons.task);
const isCondition = computed(() => props.data.type === 'condition');

// Modo somente-leitura (03-editor-visual.md §7): quando `data.executionStatus` existe, o nó
// pertence a uma ProcessInstance sendo acompanhada, não ao editor — a cor passa a comunicar
// progresso (concluído/ativo/não alcançado), não mais o tipo do nó.
const executionClass = {
    completed: 'border-accent bg-accent/10',
    active: 'border-accent bg-panel animate-pulse',
    not_reached: 'border-ink/15 bg-panel opacity-50',
};
const colorClass = computed(() =>
    props.data.executionStatus ? executionClass[props.data.executionStatus] : 'border-ink/20 bg-panel',
);
</script>

<template>
    <div
        class="min-w-[160px] rounded-lg border-2 px-3 py-2 text-xs shadow-sm"
        :class="[colorClass, selected ? 'ring-2 ring-accent ring-offset-1' : '']"
        :style="isCondition ? { clipPath: 'polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%)', padding: '1.5rem 2rem' } : {}"
    >
        <Handle type="target" :position="Position.Top" />

        <div class="flex items-center gap-1 font-medium text-ink">
            <span>{{ icon }}</span>
            <span class="truncate">{{ data.name }}</span>
        </div>

        <div class="mt-1 flex flex-wrap gap-1 text-[10px] text-ink/60">
            <span v-if="data.isStart" class="rounded bg-accent/15 px-1 text-accent">início</span>
            <span v-if="data.isEnd" class="rounded bg-ink/10 px-1 text-ink/70">fim</span>
            <span v-if="data.assigneeRoleName" class="rounded bg-ink/10 px-1 text-ink/70">{{ data.assigneeRoleName }}</span>
            <span v-if="data.assigneeUserName" class="rounded bg-ink/10 px-1 text-ink/70">{{ data.assigneeUserName }}</span>
            <span v-if="data.slaHours" class="rounded bg-ink/10 px-1 text-ink/70">{{ data.slaHours }}h SLA</span>
        </div>

        <Handle type="source" :position="Position.Bottom" />
    </div>
</template>
