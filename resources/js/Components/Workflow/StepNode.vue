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
        <div class="flex items-center justify-between rounded-t-lg bg-ink/5 px-2 py-1 text-xs font-semibold text-ink/70">
            <span>{{ data.name }}</span>
            <button
                v-if="!readonly"
                type="button"
                class="text-accent hover:underline"
                @click.stop="$emit('add-activity', data.id)"
            >
                + atividade
            </button>
        </div>
    </div>
</template>
