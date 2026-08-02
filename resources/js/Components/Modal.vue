<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    maxWidth: { type: String, default: 'lg' },
});

const maxWidthClass = computed(() => ({
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
}[props.maxWidth] ?? 'max-w-lg'));

const emit = defineEmits(['close']);

function close() {
    emit('close');
}

function handleKeyDown(e) {
    if (e.key === 'Escape' && props.show) {
        close();
    }
}

watch(
    () => props.show,
    (value) => {
        if (value) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
);

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="close"
            >
                <Transition
                    enter-active-class="ease-out duration-200"
                    enter-from-class="opacity-0 scale-95 translate-y-2"
                    enter-to-class="opacity-100 scale-100 translate-y-0"
                    leave-active-class="ease-in duration-150"
                    leave-from-class="opacity-100 scale-100 translate-y-0"
                    leave-to-class="opacity-0 scale-95 translate-y-2"
                >
                    <div
                        v-if="show"
                        :class="['w-full rounded-2xl border border-ink/10 bg-panel p-6 shadow-lg relative transform transition-all', maxWidthClass]"
                        role="dialog"
                        aria-modal="true"
                    >
                        <slot />
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
