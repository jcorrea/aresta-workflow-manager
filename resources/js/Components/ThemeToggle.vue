<script setup>
import { ref, onMounted } from 'vue';

const THEME_KEY = 'aresta-workflow-theme';
const isDark = ref(false);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
});

function toggle() {
    isDark.value = document.documentElement.classList.toggle('dark');
    try {
        localStorage.setItem(THEME_KEY, isDark.value ? 'dark' : 'light');
    } catch (err) {
        // localStorage indisponível — ignora.
    }
}
</script>

<template>
    <button
        type="button"
        :aria-pressed="isDark"
        title="Alternar tema"
        class="flex h-9 w-9 items-center justify-center rounded-full border border-white/15 bg-white/5 text-white/80 transition-colors hover:border-accent/45 hover:bg-white/10 hover:text-white focus-visible:border-accent/45 focus-visible:outline-none"
        @click="toggle"
    >
        <svg v-if="!isDark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z" />
        </svg>
        <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4">
            <circle cx="12" cy="12" r="4" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 2v2m0 16v2M2 12h2m16 0h2m-3.07-6.93-1.41 1.41M6.48 17.52l-1.41 1.41m0-13.86 1.41 1.41m11.04 11.04 1.41 1.41" />
        </svg>
    </button>
</template>
