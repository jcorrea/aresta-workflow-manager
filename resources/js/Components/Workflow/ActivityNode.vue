<script setup>
import { computed } from 'vue';
import { Handle, Position } from '@vue-flow/core';

const props = defineProps({
    data: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

const typeStyles = {
    task: { icon: '👤', color: 'bg-blue-50 border-blue-400' },
    form: { icon: '📝', color: 'bg-purple-50 border-purple-400' },
    automated_action: { icon: '⚡', color: 'bg-amber-50 border-amber-400' },
    condition: { icon: '◆', color: 'bg-emerald-50 border-emerald-400' },
};

const style = computed(() => typeStyles[props.data.type] ?? typeStyles.task);
const isCondition = computed(() => props.data.type === 'condition');
</script>

<template>
    <div
        class="min-w-[160px] rounded-lg border-2 px-3 py-2 text-xs shadow-sm"
        :class="[style.color, selected ? 'ring-2 ring-indigo-500 ring-offset-1' : '']"
        :style="isCondition ? { clipPath: 'polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%)', padding: '1.5rem 2rem' } : {}"
    >
        <Handle type="target" :position="Position.Top" />

        <div class="flex items-center gap-1 font-medium text-gray-800">
            <span>{{ style.icon }}</span>
            <span class="truncate">{{ data.name }}</span>
        </div>

        <div class="mt-1 flex flex-wrap gap-1 text-[10px] text-gray-500">
            <span v-if="data.isStart" class="rounded bg-green-100 px-1 text-green-700">início</span>
            <span v-if="data.isEnd" class="rounded bg-red-100 px-1 text-red-700">fim</span>
            <span v-if="data.assigneeRoleName" class="rounded bg-gray-100 px-1">{{ data.assigneeRoleName }}</span>
            <span v-if="data.assigneeUserName" class="rounded bg-gray-100 px-1">{{ data.assigneeUserName }}</span>
            <span v-if="data.slaHours" class="rounded bg-gray-100 px-1">{{ data.slaHours }}h SLA</span>
        </div>

        <Handle type="source" :position="Position.Bottom" />
    </div>
</template>
