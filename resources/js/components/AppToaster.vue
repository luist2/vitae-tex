<script setup lang="ts">
import type { ToastFlash } from '@/types';
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import { Toaster, toast } from 'vue-sonner';

const props = defineProps<{
    initialToast?: ToastFlash;
}>();

const showToast = (value: unknown) => {
    if (
        typeof value !== 'object' ||
        value === null ||
        !('type' in value) ||
        value.type !== 'success' ||
        !('message' in value) ||
        typeof value.message !== 'string' ||
        value.message.trim() === ''
    ) {
        return;
    }

    toast.success(value.message);
};

let removeFlashListener: VoidFunction | undefined;

onMounted(() => {
    showToast(props.initialToast);

    removeFlashListener = router.on('flash', (event) => {
        showToast(event.detail.flash.toast);
    });
});

onBeforeUnmount(() => removeFlashListener?.());
</script>

<template>
    <Toaster
        position="bottom-right"
        :duration="7000"
        :visible-toasts="3"
        :offset="16"
        :mobile-offset="16"
        close-button
        close-button-position="top-right"
        container-aria-label="Notificaciones"
        :toast-options="{
            unstyled: true,
            closeButtonAriaLabel: 'Cerrar notificación',
            classes: {
                toast: 'pointer-events-auto flex w-[var(--width)] items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-4 pr-10 text-sm text-green-900 shadow-lg dark:border-green-900 dark:bg-green-950 dark:text-green-100',
                title: 'font-medium leading-5',
                content: 'flex min-w-0 flex-1 flex-col gap-0.5',
                icon: 'flex size-4 shrink-0 items-center justify-center text-green-700 dark:text-green-400',
                closeButton:
                    'absolute right-2 top-2 flex size-6 items-center justify-center rounded-md text-green-800 transition-colors hover:bg-green-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-700 focus-visible:ring-offset-1 dark:text-green-200 dark:hover:bg-green-900 dark:focus-visible:ring-green-300',
            },
        }"
    />
</template>
