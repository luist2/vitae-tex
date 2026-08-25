<script setup lang="ts">
import CvCertificationsEditor from '@/components/cvs/CvCertificationsEditor.vue';
import CvEditorActions from '@/components/cvs/CvEditorActions.vue';
import CvEditorPanelTabs, { type CvEditorPanel } from '@/components/cvs/CvEditorPanelTabs.vue';
import CvEducationEditor from '@/components/cvs/CvEducationEditor.vue';
import CvLinksEditor from '@/components/cvs/CvLinksEditor.vue';
import CvPdfPreview, { type CvPdfDisplayStatus } from '@/components/cvs/CvPdfPreview.vue';
import CvProjectsEditor from '@/components/cvs/CvProjectsEditor.vue';
import CvSkillGroupsEditor from '@/components/cvs/CvSkillGroupsEditor.vue';
import CvWorkExperiencesEditor from '@/components/cvs/CvWorkExperiencesEditor.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCvPdfPreview } from '@/composables/useCvPdfPreview';
import AppLayout from '@/layouts/AppLayout.vue';
import { currentCsrfHeaders } from '@/lib/csrf';
import { focusFirstCvEditorError } from '@/lib/cvEditorAccessibility';
import { createCvEditorFormData, type BasicEditorFormData } from '@/lib/cvEditorForm';
import { replaceCvContentWithExample } from '@/lib/cvExample';
import type { BreadcrumbItem, CvEditorData, CvTemplateDefinition } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Download, FileInput, FileText, LoaderCircle, TriangleAlert } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    cv: CvEditorData;
    template: CvTemplateDefinition;
}>();

const activePanel = ref<CvEditorPanel>('editor');
const previewDisplayStatus = ref<CvPdfDisplayStatus>('loading');
const unsavedChangesMessage = 'Tienes cambios sin guardar. Si sales ahora, perderás esos cambios.';
const exampleReplacementMessage =
    'Este CV ya contiene información. Cargar el ejemplo reemplazará los campos del formulario, pero no guardará los cambios. ¿Quieres continuar?';
let allowNextVisit = false;

const form = useForm<BasicEditorFormData>(createCvEditorFormData(props.cv));

const hasUnsavedChanges = computed(() => form.isDirty);
const currentRevision = computed(() => props.cv.revision);
const {
    status: previewStatus,
    previewBlob,
    previewUrl,
    errorMessage: previewErrorMessage,
    retryAfterSeconds: previewRetryAfterSeconds,
    isStale: previewIsStale,
    canDownload: canDownloadPreview,
    generate: generatePreview,
    download: downloadPreview,
    dispose: disposePreview,
} = useCvPdfPreview({
    endpoint: route('cvs.generate.pdf', { cv: props.cv.id }),
    csrfHeaders: currentCsrfHeaders,
    hasUnsavedChanges,
    currentRevision,
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Mis CVs',
        href: '/cvs',
    },
    {
        title: props.cv.title,
        href: `/cvs/${props.cv.id}/edit`,
    },
];

const saveCv = () => {
    allowNextVisit = true;

    form.patch(route('cvs.update', { cv: props.cv.id }), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
        onError: (errors) => {
            selectPanel('editor');
            void nextTick(() => focusFirstCvEditorError(errors));
        },
    });
};

const clearCollectionErrors = (collection: 'work_experiences' | 'education_entries' | 'skill_groups' | 'projects' | 'certifications' | 'links') => {
    const fields = Object.keys(form.errors).filter((field) => field === collection || field.startsWith(`${collection}.`));

    if (fields.length > 0) {
        form.clearErrors(...(fields as Array<keyof typeof form.errors>));
    }
};

const selectPanel = (panel: CvEditorPanel) => {
    activePanel.value = panel;
};

const loadExample = () => {
    const replaced = replaceCvContentWithExample(form, () => window.confirm(exampleReplacementMessage));

    if (replaced) {
        form.clearErrors();
        selectPanel('editor');
    }
};

const generatePdf = async () => {
    selectPanel('preview');
    await generatePreview();
};

const handleBeforeUnload = (event: BeforeUnloadEvent) => {
    if (!form.isDirty) {
        return;
    }

    event.preventDefault();
    event.returnValue = '';
};

let removeBeforeNavigationListener: VoidFunction | undefined;

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);

    removeBeforeNavigationListener = router.on('before', (event) => {
        if (!form.isDirty || event.detail.visit.prefetch) {
            return;
        }

        if (allowNextVisit) {
            allowNextVisit = false;

            return;
        }

        if (!window.confirm(unsavedChangesMessage)) {
            event.preventDefault();
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    removeBeforeNavigationListener?.();
    disposePreview();
});
</script>

<template>
    <Head :title="form.title || cv.title" />

    <AppLayout :breadcrumbs="breadcrumbs" content-class="lg:h-[calc(100svh-1rem)] lg:overflow-hidden">
        <main
            class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-4 p-4 md:p-6 lg:grid lg:min-h-0 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.85fr)] lg:gap-6 lg:overflow-hidden"
        >
            <div class="contents lg:flex lg:min-h-0 lg:flex-col lg:gap-4">
                <div class="shrink-0">
                    <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                        <Link :href="route('cvs.index')">
                            <ArrowLeft />
                            Volver a mis CVs
                        </Link>
                    </Button>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold tracking-tight">{{ form.title || cv.title }}</h1>
                            <p class="mt-1 text-sm text-muted-foreground">Completa la información básica que aparecerá en tu currículum.</p>
                        </div>
                        <Button type="button" variant="outline" class="shrink-0 self-start" :disabled="form.processing" @click="loadExample">
                            <FileInput />
                            Cargar datos de ejemplo
                        </Button>
                    </div>
                </div>

                <CvEditorPanelTabs v-model="activePanel" />

                <CvEditorActions
                    :is-dirty="form.isDirty"
                    :is-saving="form.processing"
                    :preview-status="previewStatus"
                    :has-preview="Boolean(previewUrl)"
                    :preview-is-stale="previewIsStale"
                    :preview-retry-after-seconds="previewRetryAfterSeconds"
                    @save="saveCv"
                    @generate="generatePdf"
                />

                <section
                    id="editor-panel"
                    role="tabpanel"
                    aria-labelledby="editor-tab"
                    :class="activePanel === 'editor' ? 'block' : 'hidden lg:block'"
                    class="lg:min-h-0 lg:flex-1 lg:overflow-y-auto lg:pr-2"
                >
                    <form @submit.prevent="saveCv">
                        <Card>
                            <CardHeader>
                                <CardTitle>Información personal</CardTitle>
                                <CardDescription>
                                    Plantilla: {{ template.name }}. El título solo te ayuda a identificar este CV; los demás datos pueden aparecer en
                                    el documento.
                                </CardDescription>
                            </CardHeader>

                            <CardContent class="space-y-8">
                                <section class="grid gap-5 sm:grid-cols-2" aria-labelledby="editor-identification-heading">
                                    <h2 id="editor-identification-heading" class="sr-only">Identificación del CV</h2>

                                    <div class="grid gap-2 sm:col-span-2">
                                        <Label for="cv-title">Título interno</Label>
                                        <Input
                                            id="cv-title"
                                            v-model="form.title"
                                            maxlength="100"
                                            autocomplete="off"
                                            :aria-invalid="Boolean(form.errors.title)"
                                            aria-describedby="cv-title-error"
                                        />
                                        <InputError id="cv-title-error" :message="form.errors.title" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="cv-full-name">Nombre completo</Label>
                                        <Input
                                            id="cv-full-name"
                                            v-model="form.full_name"
                                            maxlength="120"
                                            autocomplete="name"
                                            :aria-invalid="Boolean(form.errors.full_name)"
                                            aria-describedby="cv-full-name-error"
                                        />
                                        <InputError id="cv-full-name-error" :message="form.errors.full_name" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="cv-professional-headline">Titular profesional</Label>
                                        <Input
                                            id="cv-professional-headline"
                                            v-model="form.professional_headline"
                                            maxlength="160"
                                            autocomplete="organization-title"
                                            :aria-invalid="Boolean(form.errors.professional_headline)"
                                            aria-describedby="cv-professional-headline-error"
                                        />
                                        <InputError id="cv-professional-headline-error" :message="form.errors.professional_headline" />
                                    </div>
                                </section>

                                <section class="grid gap-5 border-t pt-8 sm:grid-cols-2" aria-labelledby="editor-contact-heading">
                                    <div class="sm:col-span-2">
                                        <h2 id="editor-contact-heading" class="text-base font-semibold">Contacto</h2>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            Debes mantener al menos un email, teléfono o enlace de contacto.
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="cv-contact-email">Email de contacto</Label>
                                        <Input
                                            id="cv-contact-email"
                                            v-model="form.contact_email"
                                            type="email"
                                            maxlength="254"
                                            autocomplete="email"
                                            :aria-invalid="Boolean(form.errors.contact_email)"
                                            aria-describedby="cv-contact-email-error"
                                        />
                                        <InputError id="cv-contact-email-error" :message="form.errors.contact_email" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label for="cv-phone">Teléfono</Label>
                                        <Input
                                            id="cv-phone"
                                            v-model="form.phone"
                                            type="tel"
                                            maxlength="40"
                                            autocomplete="tel"
                                            :aria-invalid="Boolean(form.errors.phone)"
                                            aria-describedby="cv-phone-error"
                                        />
                                        <InputError id="cv-phone-error" :message="form.errors.phone" />
                                    </div>

                                    <div class="grid gap-2 sm:col-span-2">
                                        <Label for="cv-location">Ubicación</Label>
                                        <Input
                                            id="cv-location"
                                            v-model="form.location"
                                            maxlength="120"
                                            autocomplete="address-level2"
                                            :aria-invalid="Boolean(form.errors.location)"
                                            aria-describedby="cv-location-error"
                                        />
                                        <InputError id="cv-location-error" :message="form.errors.location" />
                                    </div>
                                </section>

                                <CvLinksEditor v-model="form.links" :errors="form.errors" @structure-change="clearCollectionErrors('links')" />

                                <section class="grid gap-2 border-t pt-8" aria-labelledby="editor-summary-heading">
                                    <div>
                                        <h2 id="editor-summary-heading" class="text-base font-semibold">Perfil profesional</h2>
                                        <p class="mt-1 text-sm text-muted-foreground">Resume tu experiencia, especialidad y propuesta profesional.</p>
                                    </div>

                                    <Label for="cv-professional-summary" class="sr-only">Resumen profesional</Label>
                                    <textarea
                                        id="cv-professional-summary"
                                        v-model="form.professional_summary"
                                        rows="7"
                                        maxlength="1200"
                                        :aria-invalid="Boolean(form.errors.professional_summary)"
                                        aria-describedby="cv-professional-summary-help cv-professional-summary-error"
                                        class="flex min-h-36 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 md:text-sm"
                                    />
                                    <div id="cv-professional-summary-help" class="text-right text-xs text-muted-foreground">
                                        {{ form.professional_summary.length }}/1200
                                    </div>
                                    <InputError id="cv-professional-summary-error" :message="form.errors.professional_summary" />
                                </section>

                                <CvEducationEditor
                                    v-model="form.education_entries"
                                    :errors="form.errors"
                                    @structure-change="clearCollectionErrors('education_entries')"
                                />

                                <CvWorkExperiencesEditor
                                    v-model="form.work_experiences"
                                    :errors="form.errors"
                                    @structure-change="clearCollectionErrors('work_experiences')"
                                />

                                <CvProjectsEditor
                                    v-model="form.projects"
                                    :errors="form.errors"
                                    @structure-change="clearCollectionErrors('projects')"
                                />

                                <CvSkillGroupsEditor
                                    v-model="form.skill_groups"
                                    :errors="form.errors"
                                    @structure-change="clearCollectionErrors('skill_groups')"
                                />

                                <CvCertificationsEditor
                                    v-model="form.certifications"
                                    :errors="form.errors"
                                    @structure-change="clearCollectionErrors('certifications')"
                                />
                            </CardContent>
                        </Card>
                    </form>
                </section>
            </div>

            <section
                id="preview-panel"
                role="tabpanel"
                aria-labelledby="preview-tab"
                :class="activePanel === 'preview' ? 'block' : 'hidden lg:block'"
                class="lg:h-full lg:min-h-0"
            >
                <Card class="flex min-h-[28rem] flex-col lg:h-full lg:min-h-0">
                    <CardHeader class="shrink-0 gap-3 border-b p-4 2xl:flex-row 2xl:items-start 2xl:justify-between">
                        <div class="min-w-0 flex-1">
                            <CardTitle>Preview del CV</CardTitle>
                            <CardDescription>Última versión guardada.</CardDescription>
                            <p
                                v-if="previewStatus === 'generating' && previewUrl"
                                role="status"
                                class="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <LoaderCircle class="size-3.5 animate-spin" />
                                Generando el PDF…
                            </p>
                            <p
                                v-else-if="previewStatus === 'error' && previewUrl"
                                :role="previewRetryAfterSeconds > 0 ? 'status' : 'alert'"
                                class="mt-2 flex max-w-md items-start gap-1.5 text-xs"
                                :class="previewRetryAfterSeconds > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-destructive'"
                            >
                                <TriangleAlert class="mt-0.5 size-3.5 shrink-0" />
                                {{ previewErrorMessage }} El preview anterior sigue visible.
                            </p>
                            <p
                                v-else-if="previewIsStale"
                                role="status"
                                class="mt-2 flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400"
                            >
                                <TriangleAlert class="size-3.5" />
                                Preview desactualizado
                            </p>
                            <p
                                v-else-if="previewUrl && previewDisplayStatus === 'error'"
                                role="alert"
                                class="mt-2 flex items-center gap-1.5 text-xs text-destructive"
                            >
                                <TriangleAlert class="size-3.5" />
                                El PDF se generó, pero no pudo mostrarse
                            </p>
                            <p
                                v-else-if="previewUrl && previewDisplayStatus === 'loading'"
                                role="status"
                                class="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <LoaderCircle class="size-3.5 animate-spin" />
                                Preparando el preview…
                            </p>
                            <p v-else-if="previewUrl" role="status" class="mt-2 flex items-center gap-1.5 text-xs text-green-700 dark:text-green-400">
                                <CheckCircle2 class="size-3.5" />
                                Preview actualizado
                            </p>
                            <p v-if="form.isDirty" id="preview-download-help" class="mt-2 text-xs text-amber-700 dark:text-amber-400">
                                Guarda los cambios antes de descargar.
                            </p>
                            <p v-else-if="previewIsStale" id="preview-download-help" class="mt-2 text-xs text-amber-700 dark:text-amber-400">
                                Regenera el preview antes de descargar el PDF.
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2 2xl:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="!canDownloadPreview"
                                :aria-describedby="previewIsStale || form.isDirty ? 'preview-download-help' : undefined"
                                @click="downloadPreview"
                            >
                                <Download />
                                Descargar PDF
                            </Button>
                            <Button v-if="!form.isDirty" as-child variant="outline" size="sm">
                                <a :href="route('cvs.download.tex', { cv: cv.id })" download>
                                    <Download />
                                    Descargar .tex
                                </a>
                            </Button>
                            <Button v-else type="button" variant="outline" size="sm" disabled aria-describedby="preview-download-help">
                                <Download />
                                Descargar .tex
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="flex min-h-0 flex-1 items-center justify-center bg-muted/30" :class="previewUrl ? 'p-0' : 'p-6'">
                        <CvPdfPreview v-if="previewBlob" :source="previewBlob" @status-change="previewDisplayStatus = $event" />
                        <div v-else-if="previewStatus === 'generating'" role="status" class="max-w-sm text-center">
                            <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full border bg-background shadow-sm">
                                <LoaderCircle class="size-6 animate-spin text-muted-foreground" />
                            </div>
                            <h2 class="font-semibold">Generando el PDF</h2>
                            <p class="mt-2 text-sm text-muted-foreground">La compilación puede tardar unos segundos.</p>
                        </div>
                        <div v-else-if="previewStatus === 'error'" role="alert" class="max-w-sm text-center">
                            <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full border bg-background shadow-sm">
                                <TriangleAlert
                                    class="size-6"
                                    :class="previewRetryAfterSeconds > 0 ? 'text-amber-700 dark:text-amber-400' : 'text-destructive'"
                                />
                            </div>
                            <h2 class="font-semibold">
                                {{ previewRetryAfterSeconds > 0 ? 'Espera antes de generar otro PDF' : 'No se pudo generar el preview' }}
                            </h2>
                            <p class="mt-2 text-sm text-muted-foreground">{{ previewErrorMessage }}</p>
                            <Button type="button" class="mt-4" :disabled="form.isDirty || previewRetryAfterSeconds > 0" @click="generatePdf">
                                {{ previewRetryAfterSeconds > 0 ? `Reintentar en ${previewRetryAfterSeconds} s` : 'Intentar nuevamente' }}
                            </Button>
                        </div>
                        <div v-else class="max-w-sm text-center">
                            <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full border bg-background shadow-sm">
                                <FileText class="size-6 text-muted-foreground" />
                            </div>
                            <h2 class="font-semibold">Aún no hay un preview generado</h2>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Guarda tus cambios y pulsa «Generar CV» para ver aquí el último estado persistido.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </section>
        </main>
    </AppLayout>
</template>
