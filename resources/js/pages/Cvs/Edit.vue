<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CvSummary, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, FileText } from 'lucide-vue-next';

const props = defineProps<{
    cv: CvSummary;
}>();

const page = usePage<SharedData>();

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
</script>

<template>
    <Head :title="cv.title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
            <div>
                <Button as-child variant="ghost" size="sm" class="-ml-3 mb-4">
                    <Link :href="route('cvs.index')">
                        <ArrowLeft />
                        Volver a mis CVs
                    </Link>
                </Button>
                <h1 class="text-2xl font-semibold tracking-tight">{{ cv.title }}</h1>
            </div>

            <div
                v-if="page.props.flash.success"
                role="status"
                class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200"
            >
                {{ page.props.flash.success }}
            </div>

            <Card>
                <CardHeader>
                    <div class="mb-2 flex size-10 items-center justify-center rounded-lg bg-muted">
                        <FileText class="size-5 text-muted-foreground" />
                    </div>
                    <CardTitle>CV listo para editar</CardTitle>
                    <CardDescription>Plantilla: Jake's Resume</CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        El documento y su ciclo de vida ya están disponibles. El formulario de contenido se incorporará en el siguiente bloque del
                        MVP.
                    </p>
                </CardContent>
            </Card>
        </main>
    </AppLayout>
</template>
