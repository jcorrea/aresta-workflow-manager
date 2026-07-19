<script setup>
import { route } from 'ziggy-js';

defineProps({
    active: { type: String, default: '' }, // 'workflows' | 'instances' | 'inbox'
});
</script>

<template>
    <nav class="border-b border-ink/10 bg-panel">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-6 py-3">
            <a :href="route('home')" class="flex items-center gap-2">
                <img src="/img/aresta-icon.svg" alt="" class="h-5 w-auto" />
                <span class="text-sm font-semibold text-ink">Workflow Manager</span>
            </a>
            <div class="flex items-center gap-5 text-sm">
                <a
                    :href="route('workflows.index')"
                    class="hover:text-accent"
                    :class="active === 'workflows' ? 'font-medium text-accent' : 'text-ink/70'"
                >
                    Workflows
                </a>
                <a
                    :href="route('process-instances.index')"
                    class="hover:text-accent"
                    :class="active === 'instances' ? 'font-medium text-accent' : 'text-ink/70'"
                >
                    Instâncias
                </a>
                <a
                    :href="route('inbox.index')"
                    class="hover:text-accent"
                    :class="active === 'inbox' ? 'font-medium text-accent' : 'text-ink/70'"
                >
                    Minhas tarefas
                </a>
                <a href="/admin" class="text-ink/50 hover:text-ink">Admin</a>
            </div>
        </div>
    </nav>
</template>
