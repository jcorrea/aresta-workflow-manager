<script setup>
defineProps({
    data: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
});

defineEmits(['add-activity']);
</script>

<template>
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
