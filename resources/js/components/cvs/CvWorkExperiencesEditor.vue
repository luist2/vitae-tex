<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MonthYearInput from '@/components/cvs/MonthYearInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { indexAfterRemoval, remainingItemsMessage, useAccessibleCollection } from '@/composables/useAccessibleCollection';
import type { CvWorkExperienceFormInput } from '@/types';
import { ArrowDown, ArrowUp, BriefcaseBusiness, Plus, Trash2 } from 'lucide-vue-next';

const maxExperiences = 15;
const maxHighlights = 8;

const props = defineProps<{
    errors: Partial<Record<string, string>>;
}>();

const emit = defineEmits<{
    structureChange: [];
}>();

const experiences = defineModel<CvWorkExperienceFormInput[]>({ required: true });
const { announcement, completeAction } = useAccessibleCollection();

const experienceKeys = new WeakMap<CvWorkExperienceFormInput, string>();
let nextExperienceKey = 0;

const errorFor = (path: string) => props.errors[path];

const experienceKey = (experience: CvWorkExperienceFormInput) => {
    let key = experienceKeys.get(experience);

    if (!key) {
        key = `experience-${nextExperienceKey}`;
        nextExperienceKey += 1;
        experienceKeys.set(experience, key);
    }

    return key;
};

const announceStructureChange = () => emit('structureChange');

const addExperience = async () => {
    if (experiences.value.length >= maxExperiences) {
        return;
    }

    const index = experiences.value.length;

    experiences.value.push({
        employer: '',
        role: '',
        location: '',
        start_date: '',
        end_date: '',
        is_current: false,
        highlights: [],
    });
    announceStructureChange();

    await completeAction(`Experiencia ${index + 1} añadida.`, `work-experience-${index}-employer`);
};

const removeExperience = async (index: number) => {
    experiences.value.splice(index, 1);
    announceStructureChange();

    const nextIndex = indexAfterRemoval(index, experiences.value.length);
    const focusId = nextIndex === null ? 'add-work-experience' : `work-experience-${nextIndex}-employer`;

    await completeAction(
        `Experiencia ${index + 1} eliminada. ${remainingItemsMessage(experiences.value.length, 'experiencia', 'experiencias')}`,
        focusId,
    );
};

const moveExperience = async (index: number, offset: -1 | 1) => {
    const target = index + offset;

    if (target < 0 || target >= experiences.value.length) {
        return;
    }

    const [experience] = experiences.value.splice(index, 1);

    if (!experience) {
        return;
    }

    experiences.value.splice(target, 0, experience);
    announceStructureChange();
    await completeAction(`Experiencia movida a la posición ${target + 1} de ${experiences.value.length}.`, `work-experience-${target}-employer`);
};

const setCurrent = (index: number, checked: boolean) => {
    const experience = experiences.value[index];

    if (!experience) {
        return;
    }

    experience.is_current = checked;

    if (checked) {
        experience.end_date = '';
    }

    announceStructureChange();
};

const addHighlight = async (experienceIndex: number) => {
    const experience = experiences.value[experienceIndex];

    if (!experience || experience.highlights.length >= maxHighlights) {
        return;
    }

    experience.highlights.push('');
    announceStructureChange();
    await completeAction(
        `Punto destacado ${experience.highlights.length} añadido a la experiencia ${experienceIndex + 1}.`,
        `work-experience-${experienceIndex}-highlight-${experience.highlights.length - 1}`,
    );
};

const removeHighlight = async (experienceIndex: number, highlightIndex: number) => {
    const highlights = experiences.value[experienceIndex]?.highlights;

    if (!highlights) {
        return;
    }

    highlights.splice(highlightIndex, 1);
    announceStructureChange();

    const nextIndex = indexAfterRemoval(highlightIndex, highlights.length);
    const focusId =
        nextIndex === null ? `add-work-experience-${experienceIndex}-highlight` : `work-experience-${experienceIndex}-highlight-${nextIndex}`;

    await completeAction(
        `Punto destacado ${highlightIndex + 1} eliminado de la experiencia ${experienceIndex + 1}. ${remainingItemsMessage(highlights.length, 'punto', 'puntos')}`,
        focusId,
    );
};
</script>

<template>
    <section class="space-y-5 border-t pt-8" aria-labelledby="editor-work-experience-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="editor-work-experience-heading" class="text-base font-semibold">Experiencia laboral</h2>
                <p class="mt-1 text-sm text-muted-foreground">Añade tus empleos más relevantes y ordénalos como aparecerán en el CV.</p>
            </div>

            <Button
                id="add-work-experience"
                type="button"
                variant="outline"
                size="sm"
                :disabled="experiences.length >= maxExperiences"
                @click="addExperience"
            >
                <Plus />
                Añadir experiencia
            </Button>
        </div>

        <InputError :message="errorFor('work_experiences')" />

        <div v-if="experiences.length === 0" class="rounded-lg border border-dashed px-6 py-8 text-center">
            <div class="mx-auto mb-3 flex size-10 items-center justify-center rounded-full bg-muted">
                <BriefcaseBusiness class="size-5 text-muted-foreground" />
            </div>
            <p class="text-sm font-medium">Aún no has añadido experiencia laboral</p>
            <p class="mt-1 text-sm text-muted-foreground">Puedes dejar esta sección vacía o agregar tu primer empleo.</p>
        </div>

        <ol v-else class="space-y-5">
            <li v-for="(experience, index) in experiences" :key="experienceKey(experience)">
                <fieldset class="space-y-5 rounded-lg border p-4 sm:p-5">
                    <legend class="sr-only">Experiencia {{ index + 1 }}</legend>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-medium">Experiencia {{ index + 1 }}</h3>

                        <div class="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="index === 0"
                                :aria-label="`Mover experiencia ${index + 1} hacia arriba`"
                                @click="moveExperience(index, -1)"
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="index === experiences.length - 1"
                                :aria-label="`Mover experiencia ${index + 1} hacia abajo`"
                                @click="moveExperience(index, 1)"
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:text-destructive"
                                :aria-label="`Eliminar experiencia ${index + 1}`"
                                @click="removeExperience(index)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`work-experience-${index}-employer`">Empresa</Label>
                            <Input
                                :id="`work-experience-${index}-employer`"
                                v-model="experience.employer"
                                maxlength="120"
                                autocomplete="organization"
                                :aria-invalid="Boolean(errorFor(`work_experiences.${index}.employer`))"
                                :aria-describedby="`work-experience-${index}-employer-error`"
                            />
                            <InputError :id="`work-experience-${index}-employer-error`" :message="errorFor(`work_experiences.${index}.employer`)" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`work-experience-${index}-role`">Cargo</Label>
                            <Input
                                :id="`work-experience-${index}-role`"
                                v-model="experience.role"
                                maxlength="120"
                                autocomplete="organization-title"
                                :aria-invalid="Boolean(errorFor(`work_experiences.${index}.role`))"
                                :aria-describedby="`work-experience-${index}-role-error`"
                            />
                            <InputError :id="`work-experience-${index}-role-error`" :message="errorFor(`work_experiences.${index}.role`)" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label :for="`work-experience-${index}-location`">Ubicación</Label>
                            <Input
                                :id="`work-experience-${index}-location`"
                                v-model="experience.location"
                                maxlength="120"
                                autocomplete="off"
                                :aria-invalid="Boolean(errorFor(`work_experiences.${index}.location`))"
                                :aria-describedby="`work-experience-${index}-location-error`"
                            />
                            <InputError :id="`work-experience-${index}-location-error`" :message="errorFor(`work_experiences.${index}.location`)" />
                        </div>

                        <div class="grid gap-2">
                            <MonthYearInput
                                :id="`work-experience-${index}-start-date`"
                                v-model="experience.start_date"
                                label="Fecha de inicio"
                                :aria-invalid="Boolean(errorFor(`work_experiences.${index}.start_date`))"
                                :aria-describedby="`work-experience-${index}-start-date-error`"
                            />
                            <InputError
                                :id="`work-experience-${index}-start-date-error`"
                                :message="errorFor(`work_experiences.${index}.start_date`)"
                            />
                        </div>

                        <div class="grid gap-2">
                            <MonthYearInput
                                :id="`work-experience-${index}-end-date`"
                                v-model="experience.end_date"
                                label="Fecha de término"
                                :disabled="experience.is_current"
                                :aria-invalid="Boolean(errorFor(`work_experiences.${index}.end_date`))"
                                :aria-describedby="
                                    experience.is_current
                                        ? `work-experience-${index}-end-date-help work-experience-${index}-end-date-error`
                                        : `work-experience-${index}-end-date-error`
                                "
                            />
                            <p v-if="experience.is_current" :id="`work-experience-${index}-end-date-help`" class="text-xs text-muted-foreground">
                                No se necesita una fecha de término para un empleo actual.
                            </p>
                            <InputError :id="`work-experience-${index}-end-date-error`" :message="errorFor(`work_experiences.${index}.end_date`)" />
                        </div>

                        <div class="flex items-center gap-2 sm:col-span-2">
                            <Checkbox
                                :id="`work-experience-${index}-is-current`"
                                :checked="experience.is_current"
                                :aria-invalid="Boolean(errorFor(`work_experiences.${index}.is_current`))"
                                :aria-describedby="`work-experience-${index}-is-current-error`"
                                @update:checked="setCurrent(index, $event === true)"
                            />
                            <Label :for="`work-experience-${index}-is-current`" class="font-normal">Actualmente trabajo aquí</Label>
                            <InputError
                                :id="`work-experience-${index}-is-current-error`"
                                :message="errorFor(`work_experiences.${index}.is_current`)"
                            />
                        </div>
                    </div>

                    <div class="space-y-3 border-t pt-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-medium">Logros o responsabilidades</h4>
                                <p class="mt-1 text-xs text-muted-foreground">Puedes añadir hasta {{ maxHighlights }} puntos destacados.</p>
                            </div>
                            <Button
                                :id="`add-work-experience-${index}-highlight`"
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="experience.highlights.length >= maxHighlights"
                                @click="addHighlight(index)"
                            >
                                <Plus />
                                Añadir punto
                            </Button>
                        </div>

                        <InputError :message="errorFor(`work_experiences.${index}.highlights`)" />

                        <div v-for="(_, highlightIndex) in experience.highlights" :key="highlightIndex" class="grid gap-2">
                            <div class="flex items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <Label :for="`work-experience-${index}-highlight-${highlightIndex}`" class="sr-only">
                                        Punto destacado {{ highlightIndex + 1 }} de la experiencia {{ index + 1 }}
                                    </Label>
                                    <textarea
                                        :id="`work-experience-${index}-highlight-${highlightIndex}`"
                                        v-model="experience.highlights[highlightIndex]"
                                        rows="3"
                                        maxlength="300"
                                        :aria-invalid="Boolean(errorFor(`work_experiences.${index}.highlights.${highlightIndex}`))"
                                        :aria-describedby="`work-experience-${index}-highlight-${highlightIndex}-help work-experience-${index}-highlight-${highlightIndex}-error`"
                                        class="flex min-h-20 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 md:text-sm"
                                    />
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="shrink-0 text-destructive hover:text-destructive"
                                    :aria-label="`Eliminar punto destacado ${highlightIndex + 1} de la experiencia ${index + 1}`"
                                    @click="removeHighlight(index, highlightIndex)"
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                            <div :id="`work-experience-${index}-highlight-${highlightIndex}-help`" class="text-right text-xs text-muted-foreground">
                                {{ experience.highlights[highlightIndex]?.length ?? 0 }}/300
                            </div>
                            <InputError
                                :id="`work-experience-${index}-highlight-${highlightIndex}-error`"
                                :message="errorFor(`work_experiences.${index}.highlights.${highlightIndex}`)"
                            />
                        </div>
                    </div>
                </fieldset>
            </li>
        </ol>

        <p v-if="experiences.length >= maxExperiences" class="text-xs text-muted-foreground">
            Alcanzaste el máximo de {{ maxExperiences }} experiencias permitido por CV.
        </p>

        <p role="status" aria-live="polite" aria-atomic="true" class="sr-only">{{ announcement }}</p>
    </section>
</template>
