<script setup lang="ts">
import type { CvEditorErrorSummaryItem } from '@/lib/cvEditorAccessibility';
import { TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    items: CvEditorErrorSummaryItem[];
}>();

defineEmits<{
    select: [path: string];
}>();

const linkedItemCount = computed(() => props.items.filter((item) => item.targetId).length);
const errorCountText = computed(() => (props.items.length === 1 ? 'Hay 1 error.' : `Hay ${props.items.length} errores.`));
</script>

<template>
    <section
        v-if="items.length > 0"
        id="cv-editor-error-summary"
        role="alert"
        aria-atomic="true"
        aria-labelledby="cv-editor-error-summary-heading"
        tabindex="-1"
        class="shrink-0 rounded-lg border border-red-300 bg-red-50 p-4 text-red-950 outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100"
    >
        <div class="flex items-start gap-3">
            <TriangleAlert class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <h2 id="cv-editor-error-summary-heading" class="font-semibold">Revisa los datos del CV</h2>
                <p class="mt-1 text-sm">
                    {{ errorCountText }}
                    <template v-if="linkedItemCount > 0">Selecciona un mensaje para ir al campo correspondiente.</template>
                </p>

                <ul class="mt-3 max-h-36 list-disc space-y-1 overflow-y-auto pl-5 pr-2 text-sm">
                    <li v-for="item in items" :key="item.path">
                        <button
                            v-if="item.targetId"
                            type="button"
                            :aria-controls="item.targetId"
                            class="decoration-current/50 rounded-sm text-left underline underline-offset-2 hover:decoration-current focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2"
                            @click="$emit('select', item.path)"
                        >
                            {{ item.message }}
                        </button>
                        <span v-else>
                            {{ item.message }}
                            <span class="font-medium">No se pudo localizar automáticamente este campo.</span>
                        </span>
                    </li>
                </ul>

                <p v-if="linkedItemCount === 0" class="mt-3 text-sm font-medium">
                    Revisa las secciones del formulario y corrige los datos indicados antes de guardar otra vez.
                </p>
            </div>
        </div>
    </section>
</template>
