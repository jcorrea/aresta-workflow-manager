<script setup>
defineProps({
    title: { type: String, required: true },
    text: { type: String, default: '' },
});
</script>

<template>
    <!-- Estado vazio do starter kit (docs/brand/components/estado-vazio.html): ícone, mensagem
         e call-to-action opcional (slot), num card de borda tracejada. Reage ao tema. -->
    <div
        class="mx-auto my-6 flex w-full max-w-sm flex-col items-center gap-2 rounded-xl border border-dashed border-ink/15 px-6 py-12 text-center"
    >
        <div class="mb-2 flex h-14 w-14 items-center justify-center rounded-xl bg-ink/5 text-ink/60">
            <slot name="icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 12h-6l-2 3h-4l-2-3H2" />
                    <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
                </svg>
            </slot>
        </div>
        <h2 class="text-base font-semibold tracking-tight text-ink">{{ title }}</h2>
        <p v-if="text" class="mb-2 text-sm text-ink/60">{{ text }}</p>
        <slot />
    </div>
</template>
