<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CvEditorData, CvEditorFormData, CvTemplateDefinition, SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, Save } from 'lucide-vue-next';

const props = defineProps<{
    cv: CvEditorData;
    template: CvTemplateDefinition;
}>();

type BasicEditorFormData = Omit<CvEditorFormData, 'professional_headline' | 'contact_email' | 'phone' | 'location' | 'professional_summary'> & {
    professional_headline: string;
    contact_email: string;
    phone: string;
    location: string;
    professional_summary: string;
};

const page = usePage<SharedData>();

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
        work_experiences: props.cv.work_experiences,
        education_entries: props.cv.education_entries,
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
    form.patch(route('cvs.update', { cv: props.cv.id }), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
};
</script>

<template>
    <Head :title="form.title || cv.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <Button as-child variant="ghost" size="sm" class="-ml-3 mb-4">
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

            <form @submit.prevent="saveCv">
                <Card>
                    <CardHeader>
                        <CardTitle>Información personal</CardTitle>
                        <CardDescription>
                            Plantilla: {{ template.name }}. El título solo te ayuda a identificar este CV; los demás datos pueden aparecer en el
                            documento.
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
                                <p class="mt-1 text-sm text-muted-foreground">Debes mantener al menos un email, teléfono o enlace de contacto.</p>
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
                    </CardContent>

                    <CardFooter class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t pt-6">
                        <span v-if="form.recentlySuccessful" role="status" class="flex items-center gap-2 text-sm text-green-700 dark:text-green-400">
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
        </main>
    </AppLayout>
</template>
