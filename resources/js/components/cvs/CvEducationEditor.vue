<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CvEducationFormInput } from '@/types';
import { ArrowDown, ArrowUp, GraduationCap, Plus, Trash2 } from 'lucide-vue-next';
import { nextTick } from 'vue';

const maxEducationEntries = 10;

const props = defineProps<{
    errors: Partial<Record<string, string>>;
}>();

const emit = defineEmits<{
    structureChange: [];
}>();

const entries = defineModel<CvEducationFormInput[]>({ required: true });

const entryKeys = new WeakMap<CvEducationFormInput, string>();
let nextEntryKey = 0;

const errorFor = (path: string) => props.errors[path];

const entryKey = (entry: CvEducationFormInput) => {
    let key = entryKeys.get(entry);

    if (!key) {
        key = `education-${nextEntryKey}`;
        nextEntryKey += 1;
        entryKeys.set(entry, key);
    }

    return key;
};

const announceStructureChange = () => emit('structureChange');

const addEntry = async () => {
    if (entries.value.length >= maxEducationEntries) {
        return;
    }

    const index = entries.value.length;

    entries.value.push({
        institution: '',
        qualification: '',
        field_of_study: '',
        location: '',
        start_date: '',
        end_date: '',
        is_current: false,
        description: '',
    });
    announceStructureChange();

    await nextTick();
    document.getElementById(`education-${index}-institution`)?.focus();
};

const removeEntry = (index: number) => {
    entries.value.splice(index, 1);
    announceStructureChange();
};

const moveEntry = (index: number, offset: -1 | 1) => {
    const target = index + offset;

    if (target < 0 || target >= entries.value.length) {
        return;
    }

    const [entry] = entries.value.splice(index, 1);

    if (!entry) {
        return;
    }

    entries.value.splice(target, 0, entry);
    announceStructureChange();
};

const setCurrent = (index: number, checked: boolean) => {
    const entry = entries.value[index];

    if (!entry) {
        return;
    }

    entry.is_current = checked;

    if (checked) {
        entry.end_date = '';
    }

    announceStructureChange();
};
</script>

<template>
    <section class="space-y-5 border-t pt-8" aria-labelledby="editor-education-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="editor-education-heading" class="text-base font-semibold">Educación</h2>
                <p class="mt-1 text-sm text-muted-foreground">Añade tu formación académica y ordénala como aparecerá en el CV.</p>
            </div>

            <Button type="button" variant="outline" size="sm" :disabled="entries.length >= maxEducationEntries" @click="addEntry">
                <Plus />
                Añadir educación
            </Button>
        </div>

        <InputError :message="errorFor('education_entries')" />

        <div v-if="entries.length === 0" class="rounded-lg border border-dashed px-6 py-8 text-center">
            <div class="mx-auto mb-3 flex size-10 items-center justify-center rounded-full bg-muted">
                <GraduationCap class="size-5 text-muted-foreground" />
            </div>
            <p class="text-sm font-medium">Aún no has añadido formación académica</p>
            <p class="mt-1 text-sm text-muted-foreground">Puedes dejar esta sección vacía o agregar tu primera entrada.</p>
        </div>

        <ol v-else class="space-y-5">
            <li v-for="(entry, index) in entries" :key="entryKey(entry)">
                <fieldset class="space-y-5 rounded-lg border p-4 sm:p-5">
                    <legend class="sr-only">Educación {{ index + 1 }}</legend>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-medium">Educación {{ index + 1 }}</h3>

                        <div class="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="index === 0"
                                :aria-label="`Mover educación ${index + 1} hacia arriba`"
                                @click="moveEntry(index, -1)"
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="index === entries.length - 1"
                                :aria-label="`Mover educación ${index + 1} hacia abajo`"
                                @click="moveEntry(index, 1)"
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:text-destructive"
                                :aria-label="`Eliminar educación ${index + 1}`"
                                @click="removeEntry(index)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`education-${index}-institution`">Institución</Label>
                            <Input
                                :id="`education-${index}-institution`"
                                v-model="entry.institution"
                                maxlength="120"
                                autocomplete="organization"
                                :aria-invalid="Boolean(errorFor(`education_entries.${index}.institution`))"
                                :aria-describedby="`education-${index}-institution-error`"
                            />
                            <InputError :id="`education-${index}-institution-error`" :message="errorFor(`education_entries.${index}.institution`)" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`education-${index}-qualification`">Título o grado</Label>
                            <Input
                                :id="`education-${index}-qualification`"
                                v-model="entry.qualification"
                                maxlength="160"
                                autocomplete="off"
                                :aria-invalid="Boolean(errorFor(`education_entries.${index}.qualification`))"
                                :aria-describedby="`education-${index}-qualification-error`"
                            />
                            <InputError
                                :id="`education-${index}-qualification-error`"
                                :message="errorFor(`education_entries.${index}.qualification`)"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`education-${index}-field-of-study`">Área de estudio</Label>
                            <Input
                                :id="`education-${index}-field-of-study`"
                                v-model="entry.field_of_study"
                                maxlength="120"
                                autocomplete="off"
                                :aria-invalid="Boolean(errorFor(`education_entries.${index}.field_of_study`))"
                                :aria-describedby="`education-${index}-field-of-study-error`"
                            />
                            <InputError
                                :id="`education-${index}-field-of-study-error`"
                                :message="errorFor(`education_entries.${index}.field_of_study`)"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`education-${index}-location`">Ubicación</Label>
                            <Input
                                :id="`education-${index}-location`"
                                v-model="entry.location"
                                maxlength="120"
                                autocomplete="off"
                                :aria-invalid="Boolean(errorFor(`education_entries.${index}.location`))"
                                :aria-describedby="`education-${index}-location-error`"
                            />
                            <InputError :id="`education-${index}-location-error`" :message="errorFor(`education_entries.${index}.location`)" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`education-${index}-start-date`">Fecha de inicio</Label>
                            <Input
                                :id="`education-${index}-start-date`"
                                v-model="entry.start_date"
                                type="month"
                                :aria-invalid="Boolean(errorFor(`education_entries.${index}.start_date`))"
                                :aria-describedby="`education-${index}-start-date-error`"
                            />
                            <InputError :id="`education-${index}-start-date-error`" :message="errorFor(`education_entries.${index}.start_date`)" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`education-${index}-end-date`">Fecha de término</Label>
                            <Input
                                :id="`education-${index}-end-date`"
                                v-model="entry.end_date"
                                type="month"
                                :disabled="entry.is_current"
                                :aria-invalid="Boolean(errorFor(`education_entries.${index}.end_date`))"
                                :aria-describedby="`education-${index}-end-date-help education-${index}-end-date-error`"
                            />
                            <p v-if="entry.is_current" :id="`education-${index}-end-date-help`" class="text-xs text-muted-foreground">
                                No se necesita una fecha de término para una formación actual.
                            </p>
                            <InputError :id="`education-${index}-end-date-error`" :message="errorFor(`education_entries.${index}.end_date`)" />
                        </div>

                        <div class="flex items-center gap-2 sm:col-span-2">
                            <Checkbox
                                :id="`education-${index}-is-current`"
                                :checked="entry.is_current"
                                @update:checked="setCurrent(index, $event === true)"
                            />
                            <Label :for="`education-${index}-is-current`" class="font-normal">Actualmente estudio aquí</Label>
                            <InputError :id="`education-${index}-is-current-error`" :message="errorFor(`education_entries.${index}.is_current`)" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label :for="`education-${index}-description`">Descripción</Label>
                            <textarea
                                :id="`education-${index}-description`"
                                v-model="entry.description"
                                rows="4"
                                maxlength="600"
                                :aria-invalid="Boolean(errorFor(`education_entries.${index}.description`))"
                                :aria-describedby="`education-${index}-description-help education-${index}-description-error`"
                                class="flex min-h-24 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 md:text-sm"
                            />
                            <div :id="`education-${index}-description-help`" class="text-right text-xs text-muted-foreground">
                                {{ entry.description.length }}/600
                            </div>
                            <InputError :id="`education-${index}-description-error`" :message="errorFor(`education_entries.${index}.description`)" />
                        </div>
                    </div>
                </fieldset>
            </li>
        </ol>

        <p v-if="entries.length >= maxEducationEntries" class="text-xs text-muted-foreground">
            Alcanzaste el máximo de {{ maxEducationEntries }} entradas de educación permitidas por CV.
        </p>
    </section>
</template>
