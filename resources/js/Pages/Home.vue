<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const props = defineProps({
    user: { type: Object, required: true },
    organizations: { type: Array, required: true },
});

const initials = props.user.name?.trim().charAt(0).toUpperCase() ?? '?';
const branding = computed(() => usePage().props.branding);
const logoUrl = computed(() => branding.value.logo_url);

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <Head :title="branding.app_name" />

    <div class="flex min-h-screen items-center justify-center bg-canvas p-4">
        <div class="w-full max-w-md rounded-xl border border-ink/10 bg-panel p-10 shadow-lg">
            <!-- Header: logo + tema -->
            <div class="mb-6 flex items-center justify-between">
                <img v-if="logoUrl" class="h-8 w-auto object-contain" :src="logoUrl" :alt="branding.app_name" />
                <template v-else>
                    <img class="h-8 w-auto dark:hidden" src="/img/aresta-logo-black.svg" alt="aresta" />
                    <img class="hidden h-8 w-auto dark:block" src="/img/aresta-logo.svg" alt="aresta" />
                </template>
                <ThemeToggle />
            </div>

            <!-- Perfil do usuário -->
            <div class="mb-7 flex items-center gap-3.5">
                <img
                    v-if="user.avatar_url"
                    class="h-11 w-11 shrink-0 rounded-full border border-ink/10 object-cover"
                    :src="user.avatar_url"
                    alt=""
                />
                <div
                    v-else
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-accent text-base font-bold text-accent-ink"
                >
                    {{ initials }}
                </div>
                <div>
                    <p class="text-[0.9375rem] font-semibold text-ink">{{ user.name }}</p>
                    <p class="mt-0.5 text-[0.8125rem] text-ink/55">{{ user.email }}</p>
                </div>
            </div>

            <!-- Organizações -->
            <h2 class="mb-3 text-[0.6875rem] font-semibold uppercase tracking-wide text-ink/55">Organizações</h2>
            <ul class="mb-8 flex flex-wrap gap-2">
                <li
                    v-for="org in organizations"
                    :key="org.id"
                    class="rounded-full bg-ink/10 px-3 py-1.5 text-[0.8125rem] text-ink"
                >
                    {{ org.name }}
                </li>
                <li v-if="!organizations.length" class="text-[0.8125rem] italic text-ink/40">
                    Nenhuma organização vinculada ainda.
                </li>
            </ul>

            <!-- Ações de navegação -->
            <div class="flex flex-col gap-2.5">
                <Link
                    :href="route('workflows.index')"
                    class="block rounded-lg bg-accent px-6 py-3 text-center text-sm font-semibold text-accent-ink transition-opacity hover:opacity-90"
                >
                    Ir para Workflows
                </Link>
                <Link
                    :href="route('process-instances.index')"
                    class="block rounded-lg border border-ink/15 px-6 py-3 text-center text-sm font-semibold text-ink/70 transition-colors hover:border-ink/30 hover:text-ink"
                >
                    Instâncias em andamento
                </Link>
                <Link
                    :href="route('inbox.index')"
                    class="block rounded-lg border border-ink/15 px-6 py-3 text-center text-sm font-semibold text-ink/70 transition-colors hover:border-ink/30 hover:text-ink"
                >
                    Minhas tarefas
                </Link>
                <a
                    href="/admin"
                    class="block rounded-lg border border-ink/15 px-6 py-3 text-center text-sm font-semibold text-ink/70 transition-colors hover:border-ink/30 hover:text-ink"
                >
                    Painel administrativo
                </a>
                <button
                    type="button"
                    class="block w-full rounded-lg border border-ink/15 px-6 py-3 text-center text-sm font-semibold text-ink/70 transition-colors hover:border-ink/30 hover:text-ink"
                    @click="logout"
                >
                    Sair
                </button>
            </div>
        </div>
    </div>
</template>
