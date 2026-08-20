<script setup lang="ts">
import CvEducationEditor from '@/components/cvs/CvEducationEditor.vue';
import CvSkillGroupsEditor from '@/components/cvs/CvSkillGroupsEditor.vue';
import CvWorkExperiencesEditor from '@/components/cvs/CvWorkExperiencesEditor.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    BreadcrumbItem,
    CvEditorData,
    CvEditorFormData,
    CvEducationFormInput,
    CvTemplateDefinition,
    CvWorkExperienceFormInput,
    SharedData,
} from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, FilePenLine, FileText, Save, TriangleAlert } from 'lucide-vue-next';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{
    cv: CvEditorData;
    template: CvTemplateDefinition;
}>();

type BasicEditorFormData = Omit<
    CvEditorFormData,
    'professional_headline' | 'contact_email' | 'phone' | 'location' | 'professional_summary' | 'work_experiences' | 'education_entries'
> & {
    professional_headline: string;
    contact_email: string;
    phone: string;
    location: string;
    professional_summary: string;
    work_experiences: CvWorkExperienceFormInput[];
    education_entries: CvEducationFormInput[];
};

type EditorPanel = 'editor' | 'preview';

const page = usePage<SharedData>();
const activePanel = ref<EditorPanel>('editor');
const editorTab = ref<HTMLButtonElement>();
const previewTab = ref<HTMLButtonElement>();
const unsavedChangesMessage = 'Tienes cambios sin guardar. Si sales ahora, perderás esos cambios.';
let allowNextVisit = false;

const form = useForm<BasicEditorFormData>(
    structuredClone({
        title: props.cv.title,
        template_key: props.cv.template_key,
        full_name: props.cv.full_name,
        professional_headline: props.cv.professional_headline ?? '',
        contact_email: props.cv.contact_email ?? '',
        phone: props.cv.phone ?? '',
        location: props.cv.location ?? '',
        professional_summary: props.cv.professional_summary ?? '',
        work_experiences: props.cv.work_experiences.map((experience) => ({
            ...experience,
            location: experience.location ?? '',
            end_date: experience.end_date ?? '',
        })),
        education_entries: props.cv.education_entries.map((entry) => ({
            ...entry,
            field_of_study: entry.field_of_study ?? '',
            location: entry.location ?? '',
            end_date: entry.end_date ?? '',
            description: entry.description ?? '',
        })),
        skill_groups: props.cv.skill_groups,
        projects: props.cv.projects,
        certifications: props.cv.certifications,
        links: props.cv.links,
    }),
);

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
    });
};

const clearCollectionErrors = (collection: 'work_experiences' | 'education_entries' | 'skill_groups') => {
    const fields = Object.keys(form.errors).filter((field) => field === collection || field.startsWith(`${collection}.`));

    if (fields.length > 0) {
        form.clearErrors(...(fields as Array<keyof typeof form.errors>));
    }
};

const selectPanel = (panel: EditorPanel, focusTab = false) => {
    activePanel.value = panel;

    if (focusTab) {
        void nextTick(() => (panel === 'editor' ? editorTab.value : previewTab.value)?.focus());
    }
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
});
</script>

<template>
    <Head :title="form.title || cv.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto flex w-full max-w-[1600px] flex-1 flex-col gap-4 p-4 md:p-6 lg:h-[calc(100svh-4rem)] lg:min-h-0 lg:overflow-hidden">
            <div class="shrink-0">
                <Button as-child variant="ghost" size="sm" class="-ml-3 mb-2">
                    <Link :href="route('cvs.index')">
                        <ArrowLeft />
                        Volver a mis CVs
                    </Link>
                </Button>
                <h1 class="text-2xl font-semibold tracking-tight">{{ form.title || cv.title }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">Completa la información básica que aparecerá en tu currículum.</p>
            </div>

            <div
                v-if="page.props.flash.success && !form.isDirty"
                role="status"
                class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200"
            >
                {{ page.props.flash.success }}
            </div>

            <div role="tablist" aria-label="Vista del editor" class="grid shrink-0 grid-cols-2 rounded-lg border bg-muted p-1 lg:hidden">
                <button
                    id="editor-tab"
                    ref="editorTab"
                    type="button"
                    role="tab"
                    :aria-selected="activePanel === 'editor'"
                    aria-controls="editor-panel"
                    :tabindex="activePanel === 'editor' ? 0 : -1"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    :class="activePanel === 'editor' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="selectPanel('editor')"
                    @keydown.right.prevent="selectPanel('preview', true)"
                    @keydown.end.prevent="selectPanel('preview', true)"
                >
                    <FilePenLine class="size-4" />
                    Editor
                </button>
                <button
                    id="preview-tab"
                    ref="previewTab"
                    type="button"
                    role="tab"
                    :aria-selected="activePanel === 'preview'"
                    aria-controls="preview-panel"
                    :tabindex="activePanel === 'preview' ? 0 : -1"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    :class="activePanel === 'preview' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    @click="selectPanel('preview')"
                    @keydown.left.prevent="selectPanel('editor', true)"
                    @keydown.home.prevent="selectPanel('editor', true)"
                >
                    <FileText class="size-4" />
                    Preview
                </button>
            </div>

            <div class="min-h-0 flex-1 lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(20rem,0.85fr)] lg:gap-6">
                <section
                    id="editor-panel"
                    role="tabpanel"
                    aria-labelledby="editor-tab"
                    :class="activePanel === 'editor' ? 'block' : 'hidden lg:block'"
                    class="lg:h-full lg:min-h-0 lg:overflow-y-auto lg:pr-2"
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

                                <CvSkillGroupsEditor
                                    v-model="form.skill_groups"
                                    :errors="form.errors"
                                    @structure-change="clearCollectionErrors('skill_groups')"
                                />
                            </CardContent>

                            <CardFooter
                                class="sticky bottom-0 z-10 mt-8 flex flex-wrap items-center justify-end gap-3 border-t bg-card/95 pt-6 backdrop-blur"
                            >
                                <span
                                    v-if="form.isDirty"
                                    role="status"
                                    class="mr-auto flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400"
                                >
                                    <TriangleAlert class="size-4" />
                                    Cambios sin guardar
                                </span>
                                <span
                                    v-else-if="form.recentlySuccessful"
                                    role="status"
                                    class="mr-auto flex items-center gap-2 text-sm text-green-700 dark:text-green-400"
                                >
                                    <CheckCircle2 class="size-4" />
                                    Cambios guardados
                                </span>
                                <Button type="submit" :disabled="form.processing || !form.isDirty">
                                    <Save />
                                    {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                                </Button>
                            </CardFooter>
                        </Card>
                    </form>
                </section>

                <section
                    id="preview-panel"
                    role="tabpanel"
                    aria-labelledby="preview-tab"
                    :class="activePanel === 'preview' ? 'block' : 'hidden lg:block'"
                    class="lg:h-full lg:min-h-0"
                >
                    <Card class="flex min-h-[28rem] flex-col lg:h-full lg:min-h-0">
                        <CardHeader class="shrink-0 border-b">
                            <CardTitle>Preview del CV</CardTitle>
                            <CardDescription>El PDF se genera únicamente a partir de la última versión guardada.</CardDescription>
                        </CardHeader>
                        <CardContent class="flex flex-1 items-center justify-center bg-muted/30 p-6">
                            <div class="max-w-sm text-center">
                                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full border bg-background shadow-sm">
                                    <FileText class="size-6 text-muted-foreground" />
                                </div>
                                <h2 class="font-semibold">Aún no hay un preview generado</h2>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    Cuando la generación esté disponible, el PDF aparecerá aquí sin reemplazar el formulario.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </main>
    </AppLayout>
</template>
