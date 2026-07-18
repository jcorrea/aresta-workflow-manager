<script setup>
defineProps({
    data: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

defineEmits(['add-activity']);
</script>

<template>
    <div
        class="h-full w-full rounded-xl border-2 border-dashed bg-gray-50/70"
        :class="selected ? 'border-indigo-500' : 'border-gray-300'"
    >
        <div class="flex items-center justify-between rounded-t-lg bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">
            <span>{{ data.name }}</span>
            <button type="button" class="text-indigo-600 hover:underline" @click.stop="$emit('add-activity', data.id)">
                + atividade
            </button>
        </div>
    </div>
</template>
