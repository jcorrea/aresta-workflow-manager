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

// Arestas percorridas ficam destacadas com o acento da marca; as não percorridas do desenho
// ficam em cinza neutro, fora da paleta de marca (03-editor-visual.md §7,
// 05-identidade-visual.md §7.3).
const edges = computed(() =>
    props.graph.edges.map((edge) => ({
        ...edge,
        animated: edge.data.traversed,
        style: edge.data.traversed
            ? { stroke: 'var(--color-accent)', strokeWidth: 2 }
            : { stroke: '#D4D4D4', strokeWidth: 1 },
    })),
);
</script>

<template>
    <Head :title="`Acompanhar ${instance.name}`" />

    <div class="flex h-screen flex-col bg-canvas">
        <header class="flex items-center justify-between border-b border-ink/10 bg-panel px-4 py-2">
            <div>
                <h1 class="text-sm font-semibold text-ink">{{ instance.name }}</h1>
                <p class="text-xs text-ink/60">{{ instance.code }} — {{ instance.status }}</p>
            </div>
        </header>

        <div class="flex-1">
            <VueFlow :nodes="nodes" :edges="edges" :nodes-draggable="false" :nodes-connectable="false" :elements-selectable="false">
                <Background pattern-color="rgba(13, 13, 13, 0.08)" :gap="20" />
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

/* Controles do canvas (zoom/fit/lock) na cor da marca — 05-identidade-visual.md §7.3 */
.vue-flow__controls {
    box-shadow: 0 0 0 1px rgba(13, 13, 13, 0.08);
    border-radius: 8px;
    overflow: hidden;
}
.vue-flow__controls-button {
    background: var(--color-panel);
    border-bottom: 1px solid rgba(13, 13, 13, 0.08);
}
.vue-flow__controls-button:hover {
    background: color-mix(in srgb, var(--color-accent) 15%, var(--color-panel));
}
.vue-flow__controls-button svg {
    fill: var(--color-ink);
}
</style>
