<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { indexAfterRemoval, remainingItemsMessage, useAccessibleCollection } from '@/composables/useAccessibleCollection';
import type { CvLinkFormInput, CvLinkType } from '@/types';
import { ArrowDown, ArrowUp, Link2, Plus, Trash2 } from 'lucide-vue-next';

const maxLinks = 8;
const linkTypes: Array<{ value: CvLinkType; label: string }> = [
    { value: 'linkedin', label: 'LinkedIn' },
    { value: 'github', label: 'GitHub' },
    { value: 'portfolio', label: 'Portfolio' },
    { value: 'other', label: 'Otro' },
];

const props = defineProps<{
    errors: Partial<Record<string, string>>;
}>();

const emit = defineEmits<{
    structureChange: [];
}>();

const links = defineModel<CvLinkFormInput[]>({ required: true });
const { announcement, completeAction } = useAccessibleCollection();

const linkKeys = new WeakMap<CvLinkFormInput, string>();
let nextLinkKey = 0;

const errorFor = (path: string) => props.errors[path];

const linkKey = (link: CvLinkFormInput) => {
    let key = linkKeys.get(link);

    if (!key) {
        key = `link-${nextLinkKey}`;
        nextLinkKey += 1;
        linkKeys.set(link, key);
    }

    return key;
};

const announceStructureChange = () => emit('structureChange');

const addLink = async () => {
    if (links.value.length >= maxLinks) {
        return;
    }

    const index = links.value.length;

    links.value.push({
        type: 'linkedin',
        label: '',
        url: '',
    });
    announceStructureChange();

    await completeAction(`Enlace ${index + 1} añadido.`, `link-${index}-type`);
};

const removeLink = async (index: number) => {
    links.value.splice(index, 1);
    announceStructureChange();

    const nextIndex = indexAfterRemoval(index, links.value.length);
    const focusId = nextIndex === null ? 'add-link' : `link-${nextIndex}-type`;

    await completeAction(`Enlace ${index + 1} eliminado. ${remainingItemsMessage(links.value.length, 'enlace', 'enlaces')}`, focusId);
};

const moveLink = async (index: number, offset: -1 | 1) => {
    const target = index + offset;

    if (target < 0 || target >= links.value.length) {
        return;
    }

    const [link] = links.value.splice(index, 1);

    if (!link) {
        return;
    }

    links.value.splice(target, 0, link);
    announceStructureChange();
    await completeAction(`Enlace movido a la posición ${target + 1} de ${links.value.length}.`, `link-${target}-type`);
};
</script>

<template>
    <section class="space-y-5 border-t pt-8" aria-labelledby="editor-links-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="editor-links-heading" class="text-base font-semibold">Enlaces de contacto</h2>
                <p class="mt-1 text-sm text-muted-foreground">Añade los perfiles y sitios que aparecerán en el encabezado del CV.</p>
            </div>

            <Button id="add-link" type="button" variant="outline" size="sm" :disabled="links.length >= maxLinks" @click="addLink">
                <Plus />
                Añadir enlace
            </Button>
        </div>

        <InputError :message="errorFor('links')" />

        <div v-if="links.length === 0" class="rounded-lg border border-dashed px-6 py-8 text-center">
            <div class="mx-auto mb-3 flex size-10 items-center justify-center rounded-full bg-muted">
                <Link2 class="size-5 text-muted-foreground" />
            </div>
            <p class="text-sm font-medium">Aún no has añadido enlaces</p>
            <p class="mt-1 text-sm text-muted-foreground">Puedes usar un enlace como dato de contacto o añadirlo junto a tu email o teléfono.</p>
        </div>

        <ol v-else class="space-y-5">
            <li v-for="(link, index) in links" :key="linkKey(link)">
                <fieldset class="space-y-5 rounded-lg border p-4 sm:p-5">
                    <legend class="sr-only">Enlace {{ index + 1 }}</legend>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-medium">Enlace {{ index + 1 }}</h3>

                        <div class="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="index === 0"
                                :aria-label="`Mover enlace ${index + 1} hacia arriba`"
                                @click="moveLink(index, -1)"
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="index === links.length - 1"
                                :aria-label="`Mover enlace ${index + 1} hacia abajo`"
                                @click="moveLink(index, 1)"
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:text-destructive"
                                :aria-label="`Eliminar enlace ${index + 1}`"
                                @click="removeLink(index)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`link-${index}-type`">Tipo</Label>
                            <select
                                :id="`link-${index}-type`"
                                v-model="link.type"
                                :aria-invalid="Boolean(errorFor(`links.${index}.type`))"
                                :aria-describedby="`link-${index}-type-error`"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                @change="announceStructureChange"
                            >
                                <option v-for="type in linkTypes" :key="type.value" :value="type.value">
                                    {{ type.label }}
                                </option>
                            </select>
                            <InputError :id="`link-${index}-type-error`" :message="errorFor(`links.${index}.type`)" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`link-${index}-label`">Etiqueta{{ link.type === 'other' ? ' (obligatoria)' : '' }}</Label>
                            <Input
                                :id="`link-${index}-label`"
                                v-model="link.label"
                                maxlength="60"
                                autocomplete="off"
                                :placeholder="link.type === 'other' ? 'Mi sitio profesional' : 'Opcional'"
                                :aria-invalid="Boolean(errorFor(`links.${index}.label`))"
                                :aria-describedby="`link-${index}-label-error`"
                            />
                            <InputError :id="`link-${index}-label-error`" :message="errorFor(`links.${index}.label`)" />
                        </div>

                        <div class="grid gap-2 sm:col-span-2">
                            <Label :for="`link-${index}-url`">URL</Label>
                            <Input
                                :id="`link-${index}-url`"
                                v-model="link.url"
                                type="url"
                                maxlength="2048"
                                autocomplete="url"
                                placeholder="https://ejemplo.com/perfil"
                                :aria-invalid="Boolean(errorFor(`links.${index}.url`))"
                                :aria-describedby="`link-${index}-url-error`"
                            />
                            <InputError :id="`link-${index}-url-error`" :message="errorFor(`links.${index}.url`)" />
                        </div>
                    </div>
                </fieldset>
            </li>
        </ol>

        <p v-if="links.length >= maxLinks" class="text-xs text-muted-foreground">Alcanzaste el máximo de {{ maxLinks }} enlaces permitidos por CV.</p>

        <p role="status" aria-live="polite" aria-atomic="true" class="sr-only">{{ announcement }}</p>
    </section>
</template>
