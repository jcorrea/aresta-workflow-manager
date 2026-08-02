<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const user = computed(() => usePage().props.auth.user);

const initials = computed(() => {
    const name = user.value?.name ?? '';
    return name.trim().charAt(0).toUpperCase() || '?';
});

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <!-- Vive na navbar sempre escura — estilos fixos do modo escuro, como no starter kit. -->
    <div class="group relative">
        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full">
            <img
                v-if="user?.avatar_url"
                :src="user.avatar_url"
                alt=""
                class="h-9 w-9 rounded-full border border-white/15 object-cover"
            />
            <span
                v-else
                class="flex h-9 w-9 select-none items-center justify-center rounded-full border border-white/15 bg-white/5 text-xs font-bold uppercase text-white"
            >
                {{ initials }}
            </span>
        </button>
        <div
            class="pointer-events-none absolute right-0 top-full z-20 w-max min-w-60 translate-y-1 pt-2 opacity-0 transition-all duration-150 group-hover:pointer-events-auto group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:pointer-events-auto group-focus-within:translate-y-0 group-focus-within:opacity-100"
        >
            <div class="rounded-xl border border-white/10 bg-surface p-4 text-left shadow-lg">
                <p class="text-sm font-semibold text-white">{{ user?.name }}</p>
                <p class="mt-0.5 font-mono text-[0.6875rem] text-white/50">{{ user?.email }}</p>
                <div class="mt-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 text-[0.8125rem] font-semibold text-danger-soft hover:underline"
                        @click="logout"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-3.5 w-3.5">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                            <polyline points="16 17 21 12 16 7" />
                            <line x1="21" y1="12" x2="9" y2="12" />
                        </svg>
                        Sair
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
