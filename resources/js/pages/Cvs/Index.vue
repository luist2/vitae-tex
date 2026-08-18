<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CvSummary, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Copy, FilePlus2, FileText, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    cvs: CvSummary[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Mis CVs',
        href: '/cvs',
    },
];

const page = usePage<SharedData>();
const createOpen = ref(false);
const cvToDelete = ref<CvSummary>();
const duplicatingId = ref<number>();

const createForm = useForm({
    title: '',
});

const deleteForm = useForm({});

const createCv = () => {
    createForm.post(route('cvs.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            createForm.reset();
        },
    });
};

const duplicateCv = (cv: CvSummary) => {
    router.post(
        route('cvs.duplicate', { cv: cv.id }),
        {},
        {
            preserveScroll: true,
            onStart: () => (duplicatingId.value = cv.id),
            onFinish: () => (duplicatingId.value = undefined),
        },
    );
};

const deleteCv = () => {
    if (!cvToDelete.value) {
        return;
    }

    deleteForm.delete(route('cvs.destroy', { cv: cvToDelete.value.id }), {
        preserveScroll: true,
        onSuccess: () => (cvToDelete.value = undefined),
    });
};

const formatUpdatedAt = (value: string) =>
    new Intl.DateTimeFormat('es-CL', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
</script>

<template>
    <Head title="Mis CVs" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <main class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Mis CVs</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Crea y administra versiones independientes de tu currículum.</p>
                </div>

                <Button type="button" @click="createOpen = true">
                    <Plus />
                    Crear CV
                </Button>
            </div>

            <div
                v-if="page.props.flash.success"
                role="status"
                class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200"
            >
                {{ page.props.flash.success }}
            </div>

            <Card v-if="cvs.length === 0" class="border-dashed">
                <CardContent class="flex flex-col items-center px-6 py-16 text-center">
                    <div class="mb-4 rounded-full bg-muted p-4">
                        <FilePlus2 class="size-8 text-muted-foreground" />
                    </div>
                    <h2 class="text-lg font-semibold">Crea tu primer CV</h2>
                    <p class="mt-2 max-w-md text-sm text-muted-foreground">Empieza con un documento vacío y completa su contenido en el editor.</p>
                    <Button type="button" class="mt-6" @click="createOpen = true">
                        <Plus />
                        Crear CV
                    </Button>
                </CardContent>
            </Card>

            <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Card v-for="cv in cvs" :key="cv.id" class="flex flex-col">
                    <CardHeader>
                        <div class="mb-2 flex size-10 items-center justify-center rounded-lg bg-muted">
                            <FileText class="size-5 text-muted-foreground" />
                        </div>
                        <CardTitle class="line-clamp-2">{{ cv.title }}</CardTitle>
                        <CardDescription>Actualizado {{ formatUpdatedAt(cv.updated_at) }}</CardDescription>
                    </CardHeader>

                    <CardContent class="flex-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Jake's Resume</p>
                    </CardContent>

                    <CardFooter class="flex flex-wrap gap-2 border-t pt-4">
                        <Button as-child size="sm">
                            <Link :href="route('cvs.edit', { cv: cv.id })">
                                <Pencil />
                                Abrir
                            </Link>
                        </Button>
                        <Button type="button" size="sm" variant="outline" :disabled="duplicatingId === cv.id" @click="duplicateCv(cv)">
                            <Copy />
                            {{ duplicatingId === cv.id ? 'Duplicando…' : 'Duplicar' }}
                        </Button>
                        <Button type="button" size="icon" variant="ghost" class="ml-auto text-destructive" @click="cvToDelete = cv">
                            <Trash2 />
                            <span class="sr-only">Eliminar {{ cv.title }}</span>
                        </Button>
                    </CardFooter>
                </Card>
            </div>
        </main>

        <Dialog :open="createOpen" @update:open="createOpen = $event">
            <DialogContent>
                <form @submit.prevent="createCv">
                    <DialogHeader>
                        <DialogTitle>Crear CV</DialogTitle>
                        <DialogDescription>Usa un título interno que te ayude a distinguir esta versión.</DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2 py-6">
                        <Label for="cv-title">Título</Label>
                        <Input id="cv-title" v-model="createForm.title" maxlength="100" autocomplete="off" autofocus />
                        <InputError :message="createForm.errors.title" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="createOpen = false">Cancelar</Button>
                        <Button type="submit" :disabled="createForm.processing">
                            {{ createForm.processing ? 'Creando…' : 'Crear CV' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="cvToDelete !== undefined" @update:open="!$event && (cvToDelete = undefined)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Eliminar CV permanentemente</DialogTitle>
                    <DialogDescription>
                        Se eliminará “{{ cvToDelete?.title }}” junto con todo su contenido. Esta acción no se puede deshacer.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="cvToDelete = undefined">Cancelar</Button>
                    <Button type="button" variant="destructive" :disabled="deleteForm.processing" @click="deleteCv">
                        {{ deleteForm.processing ? 'Eliminando…' : 'Eliminar permanentemente' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
