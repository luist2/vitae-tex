<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { indexAfterRemoval, remainingItemsMessage, useAccessibleCollection } from '@/composables/useAccessibleCollection';
import type { CvSkillGroupInput, CvSkillInput } from '@/types';
import { ArrowDown, ArrowUp, Braces, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const maxGroups = 10;
const maxSkillsPerGroup = 20;
const maxTotalSkills = 100;

const props = defineProps<{
    errors: Partial<Record<string, string>>;
}>();

const emit = defineEmits<{
    structureChange: [];
}>();

const groups = defineModel<CvSkillGroupInput[]>({ required: true });
const { announcement, completeAction } = useAccessibleCollection();

const groupKeys = new WeakMap<CvSkillGroupInput, string>();
const skillKeys = new WeakMap<CvSkillInput, string>();
let nextGroupKey = 0;
let nextSkillKey = 0;

const totalSkills = computed(() => groups.value.reduce((total, group) => total + group.skills.length, 0));
const canAddGroup = computed(() => groups.value.length < maxGroups && totalSkills.value < maxTotalSkills);

const errorFor = (path: string) => props.errors[path];

const groupKey = (group: CvSkillGroupInput) => {
    let key = groupKeys.get(group);

    if (!key) {
        key = `skill-group-${nextGroupKey}`;
        nextGroupKey += 1;
        groupKeys.set(group, key);
    }

    return key;
};

const skillKey = (skill: CvSkillInput) => {
    let key = skillKeys.get(skill);

    if (!key) {
        key = `skill-${nextSkillKey}`;
        nextSkillKey += 1;
        skillKeys.set(skill, key);
    }

    return key;
};

const announceStructureChange = () => emit('structureChange');

const addGroup = async () => {
    if (!canAddGroup.value) {
        return;
    }

    const index = groups.value.length;

    groups.value.push({
        name: '',
        skills: [{ name: '' }],
    });
    announceStructureChange();

    await completeAction(`Grupo ${index + 1} añadido.`, `skill-group-${index}-name`);
};

const removeGroup = async (index: number) => {
    groups.value.splice(index, 1);
    announceStructureChange();

    const nextIndex = indexAfterRemoval(index, groups.value.length);
    const focusId = nextIndex === null ? 'add-skill-group' : `skill-group-${nextIndex}-name`;

    await completeAction(`Grupo ${index + 1} eliminado. ${remainingItemsMessage(groups.value.length, 'grupo', 'grupos')}`, focusId);
};

const moveGroup = async (index: number, offset: -1 | 1) => {
    const target = index + offset;

    if (target < 0 || target >= groups.value.length) {
        return;
    }

    const [group] = groups.value.splice(index, 1);

    if (!group) {
        return;
    }

    groups.value.splice(target, 0, group);
    announceStructureChange();
    await completeAction(`Grupo movido a la posición ${target + 1} de ${groups.value.length}.`, `skill-group-${target}-name`);
};

const addSkill = async (groupIndex: number) => {
    const group = groups.value[groupIndex];

    if (!group || group.skills.length >= maxSkillsPerGroup || totalSkills.value >= maxTotalSkills) {
        return;
    }

    const skillIndex = group.skills.length;

    group.skills.push({ name: '' });
    announceStructureChange();

    await completeAction(`Habilidad ${skillIndex + 1} añadida al grupo ${groupIndex + 1}.`, `skill-group-${groupIndex}-skill-${skillIndex}`);
};

const removeSkill = async (groupIndex: number, skillIndex: number) => {
    const group = groups.value[groupIndex];

    if (!group || group.skills.length <= 1) {
        return;
    }

    group.skills.splice(skillIndex, 1);
    announceStructureChange();

    const nextIndex = indexAfterRemoval(skillIndex, group.skills.length);
    const focusId = nextIndex === null ? `add-skill-group-${groupIndex}-skill` : `skill-group-${groupIndex}-skill-${nextIndex}`;

    await completeAction(
        `Habilidad ${skillIndex + 1} eliminada del grupo ${groupIndex + 1}. ${remainingItemsMessage(group.skills.length, 'habilidad', 'habilidades')}`,
        focusId,
    );
};

const moveSkill = async (groupIndex: number, skillIndex: number, offset: -1 | 1) => {
    const skills = groups.value[groupIndex]?.skills;
    const target = skillIndex + offset;

    if (!skills || target < 0 || target >= skills.length) {
        return;
    }

    const [skill] = skills.splice(skillIndex, 1);

    if (!skill) {
        return;
    }

    skills.splice(target, 0, skill);
    announceStructureChange();
    await completeAction(
        `Habilidad movida a la posición ${target + 1} de ${skills.length} en el grupo ${groupIndex + 1}.`,
        `skill-group-${groupIndex}-skill-${target}`,
    );
};
</script>

<template>
    <section class="space-y-5 border-t pt-8" aria-labelledby="editor-skills-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="editor-skills-heading" class="text-base font-semibold">Habilidades técnicas</h2>
                <p class="mt-1 text-sm text-muted-foreground">Agrupa tus habilidades por categoría y ordénalas como aparecerán en el CV.</p>
            </div>

            <Button id="add-skill-group" type="button" variant="outline" size="sm" :disabled="!canAddGroup" @click="addGroup">
                <Plus />
                Añadir grupo
            </Button>
        </div>

        <InputError :message="errorFor('skill_groups')" />

        <div v-if="groups.length === 0" class="rounded-lg border border-dashed px-6 py-8 text-center">
            <div class="mx-auto mb-3 flex size-10 items-center justify-center rounded-full bg-muted">
                <Braces class="size-5 text-muted-foreground" />
            </div>
            <p class="text-sm font-medium">Aún no has añadido habilidades</p>
            <p class="mt-1 text-sm text-muted-foreground">Puedes dejar esta sección vacía o crear tu primer grupo.</p>
        </div>

        <ol v-else class="space-y-5">
            <li v-for="(group, groupIndex) in groups" :key="groupKey(group)">
                <fieldset class="space-y-5 rounded-lg border p-4 sm:p-5">
                    <legend class="sr-only">Grupo de habilidades {{ groupIndex + 1 }}</legend>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-medium">Grupo {{ groupIndex + 1 }}</h3>

                        <div class="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="groupIndex === 0"
                                :aria-label="`Mover grupo ${groupIndex + 1} hacia arriba`"
                                @click="moveGroup(groupIndex, -1)"
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="groupIndex === groups.length - 1"
                                :aria-label="`Mover grupo ${groupIndex + 1} hacia abajo`"
                                @click="moveGroup(groupIndex, 1)"
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:text-destructive"
                                :aria-label="`Eliminar grupo ${groupIndex + 1}`"
                                @click="removeGroup(groupIndex)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`skill-group-${groupIndex}-name`">Nombre del grupo</Label>
                        <Input
                            :id="`skill-group-${groupIndex}-name`"
                            v-model="group.name"
                            maxlength="60"
                            autocomplete="off"
                            placeholder="Ej. Lenguajes, Frameworks o Herramientas"
                            :aria-invalid="Boolean(errorFor(`skill_groups.${groupIndex}.name`))"
                            :aria-describedby="`skill-group-${groupIndex}-name-error`"
                        />
                        <InputError :id="`skill-group-${groupIndex}-name-error`" :message="errorFor(`skill_groups.${groupIndex}.name`)" />
                    </div>

                    <div class="space-y-3 border-t pt-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h4 class="text-sm font-medium">Habilidades del grupo</h4>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Cada grupo debe contener entre 1 y {{ maxSkillsPerGroup }} habilidades.
                                </p>
                            </div>

                            <Button
                                :id="`add-skill-group-${groupIndex}-skill`"
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="group.skills.length >= maxSkillsPerGroup || totalSkills >= maxTotalSkills"
                                @click="addSkill(groupIndex)"
                            >
                                <Plus />
                                Añadir habilidad
                            </Button>
                        </div>

                        <InputError :message="errorFor(`skill_groups.${groupIndex}.skills`)" />

                        <ol class="space-y-3">
                            <li v-for="(skill, skillIndex) in group.skills" :key="skillKey(skill)" class="flex items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <Label :for="`skill-group-${groupIndex}-skill-${skillIndex}`" class="sr-only">
                                        Habilidad {{ skillIndex + 1 }} del grupo {{ groupIndex + 1 }}
                                    </Label>
                                    <Input
                                        :id="`skill-group-${groupIndex}-skill-${skillIndex}`"
                                        v-model="skill.name"
                                        maxlength="80"
                                        autocomplete="off"
                                        :aria-invalid="Boolean(errorFor(`skill_groups.${groupIndex}.skills.${skillIndex}.name`))"
                                        :aria-describedby="`skill-group-${groupIndex}-skill-${skillIndex}-error`"
                                    />
                                    <InputError
                                        :id="`skill-group-${groupIndex}-skill-${skillIndex}-error`"
                                        :message="errorFor(`skill_groups.${groupIndex}.skills.${skillIndex}.name`)"
                                    />
                                </div>

                                <div class="flex shrink-0 items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :disabled="skillIndex === 0"
                                        :aria-label="`Mover habilidad ${skillIndex + 1} del grupo ${groupIndex + 1} hacia arriba`"
                                        @click="moveSkill(groupIndex, skillIndex, -1)"
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        :disabled="skillIndex === group.skills.length - 1"
                                        :aria-label="`Mover habilidad ${skillIndex + 1} del grupo ${groupIndex + 1} hacia abajo`"
                                        @click="moveSkill(groupIndex, skillIndex, 1)"
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:text-destructive"
                                        :disabled="group.skills.length === 1"
                                        :aria-label="`Eliminar habilidad ${skillIndex + 1} del grupo ${groupIndex + 1}`"
                                        @click="removeSkill(groupIndex, skillIndex)"
                                    >
                                        <Trash2 />
                                    </Button>
                                </div>
                            </li>
                        </ol>
                    </div>
                </fieldset>
            </li>
        </ol>

        <div class="flex flex-wrap justify-between gap-2 text-xs text-muted-foreground">
            <p v-if="groups.length >= maxGroups">Alcanzaste el máximo de {{ maxGroups }} grupos permitidos por CV.</p>
            <p class="ml-auto" aria-live="polite">{{ totalSkills }}/{{ maxTotalSkills }} habilidades</p>
        </div>

        <p role="status" aria-live="polite" aria-atomic="true" class="sr-only">{{ announcement }}</p>
    </section>
</template>
