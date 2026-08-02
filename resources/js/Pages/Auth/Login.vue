<script setup>
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const props = defineProps({
    appName: { type: String, required: true },
    azureRedirectUrl: { type: String, required: true },
});
</script>

<template>
    <Head :title="appName" />

    <!-- Login sempre escuro (starter kit, docs/brand/components/login.html) -->
    <div
        class="relative flex min-h-screen items-center justify-center overflow-hidden"
        style="background: #0D0D0D; color: #FFFFFF;"
    >
        <!-- Glow verde de fundo -->
        <div
            class="pointer-events-none absolute inset-0"
            style="background-image: radial-gradient(640px circle at 76% 60%, color-mix(in srgb, #00FF66 18%, transparent), transparent 60%);"
        />

        <!-- Card de login -->
        <div
            class="relative flex w-full max-w-sm flex-col items-center gap-2 rounded-[20px] px-8 py-12 text-center"
            style="background: #1A1A1A; border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 2px 4px rgba(0,0,0,0.30), 0 20px 48px rgba(0,0,0,0.34);"
        >
            <img class="block w-36 h-auto" src="/img/aresta-logo.svg" alt="aresta" />

            <h1 class="mt-4 text-[1.375rem] font-semibold tracking-tight">{{ appName }}</h1>

            <p class="mb-4 max-w-[26ch] text-sm leading-relaxed" style="color: rgba(255,255,255,0.55);">
                Entre com sua conta corporativa para continuar.
            </p>

            <a
                :href="azureRedirectUrl"
                class="flex w-full items-center justify-center gap-2.5 rounded-xl px-5 py-3 text-sm font-semibold text-white transition-colors"
                style="border: 1px solid rgba(255,255,255,0.10); background: rgba(255,255,255,0.05);"
                onmouseover="this.style.background='rgba(255,255,255,0.08)'; this.style.borderColor='color-mix(in srgb, #00FF66 45%, transparent)';"
                onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.borderColor='rgba(255,255,255,0.10)';"
            >
                <svg width="16" height="16" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0">
                    <rect x="1" y="1" width="9" height="9" fill="#F25022" />
                    <rect x="11" y="1" width="9" height="9" fill="#7FBA00" />
                    <rect x="1" y="11" width="9" height="9" fill="#00A4EF" />
                    <rect x="11" y="11" width="9" height="9" fill="#FFB900" />
                </svg>
                Entrar com a conta Microsoft
            </a>

            <span class="mt-4 font-mono text-[0.6875rem] tracking-wide" style="color: rgba(255,255,255,0.35);">
                sso://microsoft-entra-id
            </span>
        </div>
    </div>
</template>
