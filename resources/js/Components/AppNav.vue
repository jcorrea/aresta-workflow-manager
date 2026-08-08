<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import UserMenu from '@/Components/UserMenu.vue';

defineProps({
    active: { type: String, default: '' }, // 'workflows' | 'instances' | 'inbox'
});

const branding = computed(() => usePage().props.branding);
const logoUrl = computed(() => branding.value.logo_url ?? '/img/aresta-logo.svg');
</script>

<template>
    <!-- Navbar SEMPRE escura (starter kit, docs/brand/components/navbar.html) — só o
         conteúdo da página abaixo dela reage ao tema claro/escuro. -->
    <nav class="sticky top-0 z-30 border-b border-white/10 bg-surface">
        <div class="flex items-center justify-between px-6 py-3">
            <Link :href="route('home')" class="flex items-center">
                <img :src="logoUrl" :alt="branding.app_name" class="h-6 w-auto object-contain" />
            </Link>
            <div class="flex items-center gap-5 text-sm">
                <Link
                    :href="route('workflows.index')"
                    class="hover:text-accent transition-colors"
                    :class="active === 'workflows' ? 'font-medium text-accent' : 'text-white/70'"
                >
                    Workflows
                </Link>
                <Link
                    :href="route('process-instances.index')"
                    class="hover:text-accent transition-colors"
                    :class="active === 'instances' ? 'font-medium text-accent' : 'text-white/70'"
                >
                    Instâncias
                </Link>
                <Link
                    :href="route('inbox.index')"
                    class="hover:text-accent transition-colors"
                    :class="active === 'inbox' ? 'font-medium text-accent' : 'text-white/70'"
                >
                    Minhas tarefas
                </Link>
                <a href="/admin" class="text-white/50 hover:text-white transition-colors">Admin</a>
                <div class="flex items-center gap-2 border-l border-white/10 pl-4">
                    <ThemeToggle />
                    <UserMenu />
                </div>
            </div>
        </div>
    </nav>
</template>
