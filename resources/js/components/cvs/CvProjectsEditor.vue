<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MonthYearInput from '@/components/cvs/MonthYearInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { indexAfterRemoval, remainingItemsMessage, useAccessibleCollection } from '@/composables/useAccessibleCollection';
import type { CvProjectFormInput } from '@/types';
import { ArrowDown, ArrowUp, FolderKanban, Plus, Trash2 } from 'lucide-vue-next';

const maxProjects = 15;
const maxHighlights = 8;
const maxTechnologies = 20;

const props = defineProps<{
    errors: Partial<Record<string, string>>;
}>();

const emit = defineEmits<{
    fieldChange: [paths: string[]];
    structureChange: [];
}>();

const projects = defineModel<CvProjectFormInput[]>({ required: true });
const { announcement, completeAction } = useAccessibleCollection();

const projectKeys = new WeakMap<CvProjectFormInput, string>();
let nextProjectKey = 0;

const errorFor = (path: string) => props.errors[path];

const projectKey = (project: CvProjectFormInput) => {
    let key = projectKeys.get(project);

    if (!key) {
        key = `project-${nextProjectKey}`;
        nextProjectKey += 1;
        projectKeys.set(project, key);
    }

    return key;
};

const announceStructureChange = () => emit('structureChange');

const addProject = async () => {
    if (projects.value.length >= maxProjects) {
        return;
    }

    const index = projects.value.length;

    projects.value.push({
        name: '',
        role: '',
        description: '',
        url: '',
        start_date: '',
        end_date: '',
        is_current: false,
        highlights: [],
        technologies: [],
    });
    announceStructureChange();

    await completeAction(`Proyecto ${index + 1} añadido.`, `project-${index}-name`);
};

const removeProject = async (index: number) => {
    projects.value.splice(index, 1);
    announceStructureChange();

    const nextIndex = indexAfterRemoval(index, projects.value.length);
    const focusId = nextIndex === null ? 'add-project' : `project-${nextIndex}-name`;

    await completeAction(`Proyecto ${index + 1} eliminado. ${remainingItemsMessage(projects.value.length, 'proyecto', 'proyectos')}`, focusId);
};

const moveProject = async (index: number, offset: -1 | 1) => {
    const target = index + offset;

    if (target < 0 || target >= projects.value.length) {
        return;
    }

    const [project] = projects.value.splice(index, 1);

    if (!project) {
        return;
    }

    projects.value.splice(target, 0, project);
    announceStructureChange();
    await completeAction(`Proyecto movido a la posición ${target + 1} de ${projects.value.length}.`, `project-${target}-name`);
};

const setCurrent = (index: number, checked: boolean) => {
    const project = projects.value[index];

    if (!project) {
        return;
    }

    project.is_current = checked;

    if (checked) {
        project.end_date = '';
    }

    emit('fieldChange', [`projects.${index}.is_current`, `projects.${index}.start_date`, `projects.${index}.end_date`]);
};

const addHighlight = async (projectIndex: number) => {
    const project = projects.value[projectIndex];

    if (!project || project.highlights.length >= maxHighlights) {
        return;
    }

    const highlightIndex = project.highlights.length;

    project.highlights.push('');
    announceStructureChange();

    await completeAction(
        `Punto destacado ${highlightIndex + 1} añadido al proyecto ${projectIndex + 1}.`,
        `project-${projectIndex}-highlight-${highlightIndex}`,
    );
};

const removeHighlight = async (projectIndex: number, highlightIndex: number) => {
    const highlights = projects.value[projectIndex]?.highlights;

    if (!highlights) {
        return;
    }

    highlights.splice(highlightIndex, 1);
    announceStructureChange();

    const nextIndex = indexAfterRemoval(highlightIndex, highlights.length);
    const focusId = nextIndex === null ? `add-project-${projectIndex}-highlight` : `project-${projectIndex}-highlight-${nextIndex}`;

    await completeAction(
        `Punto destacado ${highlightIndex + 1} eliminado del proyecto ${projectIndex + 1}. ${remainingItemsMessage(highlights.length, 'punto', 'puntos')}`,
        focusId,
    );
};

const moveHighlight = async (projectIndex: number, highlightIndex: number, offset: -1 | 1) => {
    const highlights = projects.value[projectIndex]?.highlights;
    const target = highlightIndex + offset;

    if (!highlights || target < 0 || target >= highlights.length) {
        return;
    }

    const [highlight] = highlights.splice(highlightIndex, 1);

    if (highlight === undefined) {
        return;
    }

    highlights.splice(target, 0, highlight);
    announceStructureChange();
    await completeAction(
        `Punto destacado movido a la posición ${target + 1} de ${highlights.length} en el proyecto ${projectIndex + 1}.`,
        `project-${projectIndex}-highlight-${target}`,
    );
};

const addTechnology = async (projectIndex: number) => {
    const project = projects.value[projectIndex];

    if (!project || project.technologies.length >= maxTechnologies) {
        return;
    }

    const technologyIndex = project.technologies.length;

    project.technologies.push('');
    announceStructureChange();

    await completeAction(
        `Tecnología ${technologyIndex + 1} añadida al proyecto ${projectIndex + 1}.`,
        `project-${projectIndex}-technology-${technologyIndex}`,
    );
};

const removeTechnology = async (projectIndex: number, technologyIndex: number) => {
    const technologies = projects.value[projectIndex]?.technologies;

    if (!technologies) {
        return;
    }

    technologies.splice(technologyIndex, 1);
    announceStructureChange();

    const nextIndex = indexAfterRemoval(technologyIndex, technologies.length);
    const focusId = nextIndex === null ? `add-project-${projectIndex}-technology` : `project-${projectIndex}-technology-${nextIndex}`;

    await completeAction(
        `Tecnología ${technologyIndex + 1} eliminada del proyecto ${projectIndex + 1}. ${remainingItemsMessage(technologies.length, 'tecnología', 'tecnologías')}`,
        focusId,
    );
};

const moveTechnology = async (projectIndex: number, technologyIndex: number, offset: -1 | 1) => {
    const technologies = projects.value[projectIndex]?.technologies;
    const target = technologyIndex + offset;

    if (!technologies || target < 0 || target >= technologies.length) {
        return;
    }

    const [technology] = technologies.splice(technologyIndex, 1);

    if (technology === undefined) {
        return;
    }

    technologies.splice(target, 0, technology);
    announceStructureChange();
    await completeAction(
        `Tecnología movida a la posición ${target + 1} de ${technologies.length} en el proyecto ${projectIndex + 1}.`,
        `project-${projectIndex}-technology-${target}`,
    );
};
</script>

<template>
    <section class="space-y-5 border-t pt-8" aria-labelledby="editor-projects-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="editor-projects-heading" class="text-base font-semibold">Proyectos</h2>
                <p class="mt-1 text-sm text-muted-foreground">Presenta tus proyectos más relevantes y ordénalos como aparecerán en el CV.</p>
            </div>

            <Button id="add-project" type="button" variant="outline" size="sm" :disabled="projects.length >= maxProjects" @click="addProject">
                <Plus />
                Añadir proyecto
            </Button>
        </div>

        <InputError :message="errorFor('projects')" />

        <div v-if="projects.length === 0" class="rounded-lg border border-dashed px-6 py-8 text-center">
            <div class="mx-auto mb-3 flex size-10 items-center justify-center rounded-full bg-muted">
                <FolderKanban class="size-5 text-muted-foreground" />
            </div>
            <p class="text-sm font-medium">Aún no has añadido proyectos</p>
            <p class="mt-1 text-sm text-muted-foreground">Puedes dejar esta sección vacía o agregar tu primer proyecto.</p>
        </div>

        <ol v-else class="space-y-5">
            <li v-for="(project, index) in projects" :key="projectKey(project)">
                <fieldset class="space-y-5 rounded-lg border p-4 sm:p-5">
                    <legend class="sr-only">Proyecto {{ index + 1 }}</legend>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-medium">Proyecto {{ index + 1 }}</h3>

                        <div class="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="index === 0"
                                :aria-label="`Mover proyecto ${index + 1} hacia arriba`"
                                @click="moveProject(index, -1)"
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="index === projects.length - 1"
                                :aria-label="`Mover proyecto ${index + 1} hacia abajo`"
                                @click="moveProject(index, 1)"
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:text-destructive"
                                :aria-label="`Eliminar proyecto ${index + 1}`"
                                @click="removeProject(index)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`project-${index}-name`">Nombre</Label>
                            <Input
                                :id="`project-${index}-name`"
                                v-model="project.name"
                                maxlength="120"
                                autocomplete="off"
                                :aria-invalid="Boolean(errorFor(`projects.${index}.name`))"
                                :aria-describedby="`project-${index}-name-error`"
                            />
                            <InputError :id="`project-${index}-name-error`" :message="errorFor(`projects.${index}.name`)" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`project-${index}-role`">Rol</Label>
                            <Input
                                :id="`project-${index}-role`"
                                v-model="project.role"
                                maxlength="120"
                                autocomplete="off"
                                :aria-invalid="Boolean(errorFor(`projects.${index}.role`))"
                                :aria-describedby="`project-${index}-role-error`"
                            />
                            <InputError :id="`project-${index}-role-error`" :message="errorFor(`projects.${index}.role`)" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label :for="`project-${index}-url`">URL</Label>
                            <Input
                                :id="`project-${index}-url`"
                                v-model="project.url"
                                type="url"
                                maxlength="2048"
                                autocomplete="url"
                                placeholder="https://ejemplo.com/proyecto"
                                :aria-invalid="Boolean(errorFor(`projects.${index}.url`))"
                                :aria-describedby="`project-${index}-url-error`"
                            />
                            <InputError :id="`project-${index}-url-error`" :message="errorFor(`projects.${index}.url`)" />
                        </div>

                        <div class="grid gap-2">
                            <MonthYearInput
                                :id="`project-${index}-start-date`"
                                v-model="project.start_date"
                                label="Fecha de inicio"
                                :aria-invalid="Boolean(errorFor(`projects.${index}.start_date`))"
                                :aria-describedby="`project-${index}-start-date-error`"
                            />
                            <InputError :id="`project-${index}-start-date-error`" :message="errorFor(`projects.${index}.start_date`)" />
                        </div>

                        <div class="grid gap-2">
                            <MonthYearInput
                                :id="`project-${index}-end-date`"
                                v-model="project.end_date"
                                label="Fecha de término"
                                :disabled="project.is_current"
                                :aria-invalid="Boolean(errorFor(`projects.${index}.end_date`))"
                                :aria-describedby="
                                    project.is_current
                                        ? `project-${index}-end-date-help project-${index}-end-date-error`
                                        : `project-${index}-end-date-error`
                                "
                            />
                            <p v-if="project.is_current" :id="`project-${index}-end-date-help`" class="text-xs text-muted-foreground">
                                No se necesita una fecha de término para un proyecto actual.
                            </p>
                            <InputError :id="`project-${index}-end-date-error`" :message="errorFor(`projects.${index}.end_date`)" />
                        </div>

                        <div class="flex items-center gap-2 sm:col-span-2">
                            <Checkbox
                                :id="`project-${index}-is-current`"
                                :checked="project.is_current"
                                :aria-invalid="Boolean(errorFor(`projects.${index}.is_current`))"
                                :aria-describedby="`project-${index}-is-current-error`"
                                @update:checked="setCurrent(index, $event === true)"
                            />
                            <Label :for="`project-${index}-is-current`" class="font-normal">Proyecto en curso</Label>
                            <InputError :id="`project-${index}-is-current-error`" :message="errorFor(`projects.${index}.is_current`)" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label :for="`project-${index}-description`">Descripción</Label>
                            <textarea
                                :id="`project-${index}-description`"
                                v-model="project.description"
                                rows="4"
                                maxlength="600"
                                :aria-invalid="Boolean(errorFor(`projects.${index}.description`))"
                                :aria-describedby="`project-${index}-description-help project-${index}-description-error`"
                                class="flex min-h-24 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 md:text-sm"
                            />
                            <div :id="`project-${index}-description-help`" class="text-right text-xs text-muted-foreground">
                                {{ project.description.length }}/600
                            </div>
                            <InputError :id="`project-${index}-description-error`" :message="errorFor(`projects.${index}.description`)" />
                        </div>
                    </div>

                    <div class="space-y-3 border-t pt-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-medium">Tecnologías</h4>
                                <p class="mt-1 text-xs text-muted-foreground">Puedes añadir y ordenar hasta {{ maxTechnologies }} tecnologías.</p>
                            </div>
                            <Button
                                :id="`add-project-${index}-technology`"
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="project.technologies.length >= maxTechnologies"
                                @click="addTechnology(index)"
                            >
                                <Plus />
                                Añadir tecnología
                            </Button>
                        </div>

                        <InputError :message="errorFor(`projects.${index}.technologies`)" />

                        <ol v-if="project.technologies.length > 0" class="space-y-3">
                            <li v-for="(_, technologyIndex) in project.technologies" :key="technologyIndex" class="flex items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <Label :for="`project-${index}-technology-${technologyIndex}`" class="sr-only">
                                        Tecnología {{ technologyIndex + 1 }} del proyecto {{ index + 1 }}
                                    </Label>
                                    <Input
                                        :id="`project-${index}-technology-${technologyIndex}`"
                                        v-model="project.technologies[technologyIndex]"
                                        maxlength="60"
                                        autocomplete="off"
                                        :aria-invalid="Boolean(errorFor(`projects.${index}.technologies.${technologyIndex}`))"
                                        :aria-describedby="`project-${index}-technology-${technologyIndex}-error`"
                                    />
                                    <InputError
                                        :id="`project-${index}-technology-${technologyIndex}-error`"
                                        :message="errorFor(`projects.${index}.technologies.${technologyIndex}`)"
                                    />
                                </div>

                                <div class="flex shrink-0 items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :disabled="technologyIndex === 0"
                                        :aria-label="`Mover tecnología ${technologyIndex + 1} del proyecto ${index + 1} hacia arriba`"
                                        @click="moveTechnology(index, technologyIndex, -1)"
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :disabled="technologyIndex === project.technologies.length - 1"
                                        :aria-label="`Mover tecnología ${technologyIndex + 1} del proyecto ${index + 1} hacia abajo`"
                                        @click="moveTechnology(index, technologyIndex, 1)"
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                        :aria-label="`Eliminar tecnología ${technologyIndex + 1} del proyecto ${index + 1}`"
                                        @click="removeTechnology(index, technologyIndex)"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </li>
                        </ol>
                    </div>

                    <div class="space-y-3 border-t pt-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-medium">Logros o características</h4>
                                <p class="mt-1 text-xs text-muted-foreground">Puedes añadir y ordenar hasta {{ maxHighlights }} puntos destacados.</p>
                            </div>
                            <Button
                                :id="`add-project-${index}-highlight`"
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="project.highlights.length >= maxHighlights"
                                @click="addHighlight(index)"
                            >
                                <Plus />
                                Añadir punto
                            </Button>
                        </div>

                        <InputError :message="errorFor(`projects.${index}.highlights`)" />

                        <ol v-if="project.highlights.length > 0" class="space-y-3">
                            <li v-for="(_, highlightIndex) in project.highlights" :key="highlightIndex" class="grid gap-2">
                                <div class="flex items-start gap-2">
                                    <div class="min-w-0 flex-1">
                                        <Label :for="`project-${index}-highlight-${highlightIndex}`" class="sr-only">
                                            Punto destacado {{ highlightIndex + 1 }} del proyecto {{ index + 1 }}
                                        </Label>
                                        <textarea
                                            :id="`project-${index}-highlight-${highlightIndex}`"
                                            v-model="project.highlights[highlightIndex]"
                                            rows="3"
                                            maxlength="300"
                                            :aria-invalid="Boolean(errorFor(`projects.${index}.highlights.${highlightIndex}`))"
                                            :aria-describedby="`project-${index}-highlight-${highlightIndex}-help project-${index}-highlight-${highlightIndex}-error`"
                                            class="flex min-h-20 w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 md:text-sm"
                                        />
                                    </div>

                                    <div class="flex shrink-0 items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :disabled="highlightIndex === 0"
                                            :aria-label="`Mover punto destacado ${highlightIndex + 1} del proyecto ${index + 1} hacia arriba`"
                                            @click="moveHighlight(index, highlightIndex, -1)"
                                        >
                                            <ArrowUp />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :disabled="highlightIndex === project.highlights.length - 1"
                                            :aria-label="`Mover punto destacado ${highlightIndex + 1} del proyecto ${index + 1} hacia abajo`"
                                            @click="moveHighlight(index, highlightIndex, 1)"
                                        >
                                            <ArrowDown />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="text-destructive hover:text-destructive"
                                            :aria-label="`Eliminar punto destacado ${highlightIndex + 1} del proyecto ${index + 1}`"
                                            @click="removeHighlight(index, highlightIndex)"
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </div>
                                <div :id="`project-${index}-highlight-${highlightIndex}-help`" class="text-right text-xs text-muted-foreground">
                                    {{ project.highlights[highlightIndex]?.length ?? 0 }}/300
                                </div>
                                <InputError
                                    :id="`project-${index}-highlight-${highlightIndex}-error`"
                                    :message="errorFor(`projects.${index}.highlights.${highlightIndex}`)"
                                />
                            </li>
                        </ol>
                    </div>
                </fieldset>
            </li>
        </ol>

        <p v-if="projects.length >= maxProjects" class="text-xs text-muted-foreground">
            Alcanzaste el máximo de {{ maxProjects }} proyectos permitido por CV.
        </p>

        <p role="status" aria-live="polite" aria-atomic="true" class="sr-only">{{ announcement }}</p>
    </section>
</template>
