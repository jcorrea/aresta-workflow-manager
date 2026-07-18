<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { VueFlow } from '@vue-flow/core';
import { Background } from '@vue-flow/background';
import { Controls } from '@vue-flow/controls';
import ActivityNode from '@/Components/Workflow/ActivityNode.vue';
import StepNode from '@/Components/Workflow/StepNode.vue';

const props = defineProps({
    instance: { type: Object, required: true },
    graph: { type: Object, required: true },
});

const nodes = computed(() => props.graph.nodes);

// Arestas percorridas ficam destacadas; as não percorridas do desenho ficam em cinza claro
// (03-editor-visual.md §7).
const edges = computed(() =>
    props.graph.edges.map((edge) => ({
        ...edge,
        animated: edge.data.traversed,
        style: edge.data.traversed
            ? { stroke: '#4f46e5', strokeWidth: 2 }
            : { stroke: '#d1d5db', strokeWidth: 1 },
    })),
);
</script>

<template>
    <Head :title="`Acompanhar ${instance.name}`" />

    <div class="flex h-screen flex-col bg-gray-100">
        <header class="flex items-center justify-between border-b bg-white px-4 py-2 shadow-sm">
            <div>
                <h1 class="text-sm font-semibold text-gray-800">{{ instance.name }}</h1>
                <p class="text-xs text-gray-500">{{ instance.code }} — {{ instance.status }}</p>
            </div>
        </header>

        <div class="flex-1">
            <VueFlow :nodes="nodes" :edges="edges" :nodes-draggable="false" :nodes-connectable="false" :elements-selectable="false">
                <Background />
                <Controls :show-interactive="false" />

                <template #node-step="nodeProps">
                    <StepNode v-bind="nodeProps" readonly />
                </template>
                <template #node-task="nodeProps">
                    <ActivityNode v-bind="nodeProps" />
                </template>
                <template #node-form="nodeProps">
                    <ActivityNode v-bind="nodeProps" />
                </template>
                <template #node-automated_action="nodeProps">
                    <ActivityNode v-bind="nodeProps" />
                </template>
                <template #node-condition="nodeProps">
                    <ActivityNode v-bind="nodeProps" />
                </template>
            </VueFlow>
        </div>
    </div>
</template>

<style>
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
@import '@vue-flow/controls/dist/style.css';
</style>
