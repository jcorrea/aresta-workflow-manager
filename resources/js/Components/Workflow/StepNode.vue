<script setup>
import { NodeResizer } from '@vue-flow/node-resizer';
import '@vue-flow/node-resizer/dist/style.css';

const props = defineProps({
    data: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['add-activity', 'resize-end']);

// Tamanho mínimo cobre o cabeçalho + espaço pra pelo menos uma atividade sem sobrepor.
const MIN_STEP_WIDTH = 240;
const MIN_STEP_HEIGHT = 140;

function onResizeEnd({ params }) {
    emit('resize-end', {
        id: props.data.id,
        width: Math.round(params.width),
        height: Math.round(params.height),
        positionX: Math.round(params.x),
        positionY: Math.round(params.y),
    });
}
</script>

<template>
    <NodeResizer
        v-if="!readonly"
        :is-visible="selected"
        :min-width="MIN_STEP_WIDTH"
        :min-height="MIN_STEP_HEIGHT"
        @resize-end="onResizeEnd"
    />
    <div
        class="h-full w-full rounded-xl border-2 border-dashed bg-panel/60"
        :class="selected ? 'border-accent' : 'border-ink/20'"
    >
        <div class="flex items-center justify-between rounded-t-lg border-b border-ink/10 bg-ink/5 px-3 py-1.5 text-xs font-semibold text-ink/80">
            <span class="truncate font-medium">{{ data.name }}</span>
            <button
                v-if="!readonly"
                type="button"
                class="inline-flex items-center gap-1 rounded-md bg-accent px-2 py-0.5 text-xs font-medium text-white shadow-sm transition hover:bg-accent/90 active:scale-95"
                title="Adicionar atividade nesta etapa"
                @click.stop="$emit('add-activity', data.id)"
            >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                </svg>
                <span>Atividade</span>
            </button>
        </div>
    </div>
</template>
