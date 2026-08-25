<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { CvPdfPreviewStatus } from '@/composables/useCvPdfPreview';
import { CheckCircle2, FileText, LoaderCircle, RefreshCw, Save, TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    isDirty: boolean;
    isSaving: boolean;
    previewStatus: CvPdfPreviewStatus;
    hasPreview: boolean;
    previewIsStale: boolean;
    previewRetryAfterSeconds: number;
}>();

const emit = defineEmits<{
    save: [];
    generate: [];
}>();

const isGenerating = computed(() => props.previewStatus === 'generating');
const previewIsCurrent = computed(() => props.hasPreview && !props.previewIsStale);
const generationDisabled = computed(
    () => props.isDirty || props.isSaving || isGenerating.value || previewIsCurrent.value || props.previewRetryAfterSeconds > 0,
);

const generationLabel = computed(() => {
    if (isGenerating.value) {
        return props.hasPreview ? 'Regenerar CV' : 'Generar CV';
    }

    if (props.previewRetryAfterSeconds > 0) {
        return `Reintentar en ${props.previewRetryAfterSeconds} s`;
    }

    if (previewIsCurrent.value) {
        return 'CV generado';
    }

    if (props.hasPreview) {
        return 'Regenerar CV';
    }

    if (props.previewStatus === 'error') {
        return 'Intentar nuevamente';
    }

    return 'Generar CV';
});
</script>

<template>
    <section
        aria-label="Acciones del CV"
        class="sticky top-0 z-20 flex shrink-0 flex-col gap-3 rounded-lg border bg-background/95 p-3 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-background/80 2xl:flex-row 2xl:items-center"
    >
        <div class="min-w-0 flex-1 text-sm" aria-live="polite">
            <span v-if="isSaving" class="flex items-center gap-2 text-muted-foreground">
                <LoaderCircle class="size-4 shrink-0 animate-spin" aria-hidden="true" />
                Guardando cambios…
            </span>
            <span v-else-if="isDirty" id="cv-generation-help" class="flex items-center gap-2 text-amber-700 dark:text-amber-400">
                <TriangleAlert class="size-4 shrink-0" aria-hidden="true" />
                Cambios sin guardar. Guarda antes de generar.
            </span>
            <span v-else-if="isGenerating" class="sr-only" role="status">
                {{ hasPreview ? 'Regenerando el preview…' : 'Generando el preview…' }}
            </span>
            <span
                v-else-if="previewRetryAfterSeconds > 0"
                id="cv-generation-rate-limit-help"
                role="status"
                class="flex items-center gap-2 text-amber-700 dark:text-amber-400"
            >
                <TriangleAlert class="size-4 shrink-0" aria-hidden="true" />
                Podrás regenerar dentro de {{ previewRetryAfterSeconds }} s.
            </span>
            <span v-else class="text-muted-foreground">CV guardado.</span>
        </div>

        <div class="grid grid-cols-1 gap-2 min-[420px]:grid-cols-2 sm:flex sm:shrink-0 sm:flex-wrap">
            <Button type="button" :variant="isDirty ? 'default' : 'outline'" :disabled="isSaving || !isDirty" @click="emit('save')">
                <LoaderCircle v-if="isSaving" class="animate-spin" aria-hidden="true" />
                <Save v-else aria-hidden="true" />
                {{ isSaving ? 'Guardando…' : 'Guardar cambios' }}
            </Button>
            <Button
                type="button"
                :variant="generationDisabled ? 'outline' : 'default'"
                :disabled="generationDisabled"
                :aria-busy="isGenerating"
                :aria-describedby="
                    isDirty && !isSaving ? 'cv-generation-help' : previewRetryAfterSeconds > 0 ? 'cv-generation-rate-limit-help' : undefined
                "
                @click="emit('generate')"
            >
                <CheckCircle2 v-if="previewIsCurrent" aria-hidden="true" />
                <RefreshCw v-else-if="hasPreview" aria-hidden="true" />
                <FileText v-else aria-hidden="true" />
                {{ generationLabel }}
            </Button>
        </div>
    </section>
</template>
