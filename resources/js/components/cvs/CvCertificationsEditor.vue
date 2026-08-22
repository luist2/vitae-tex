<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { indexAfterRemoval, remainingItemsMessage, useAccessibleCollection } from '@/composables/useAccessibleCollection';
import type { CvCertificationFormInput } from '@/types';
import { ArrowDown, ArrowUp, BadgeCheck, Plus, Trash2 } from 'lucide-vue-next';

const maxCertifications = 20;

const props = defineProps<{
    errors: Partial<Record<string, string>>;
}>();

const emit = defineEmits<{
    structureChange: [];
}>();

const certifications = defineModel<CvCertificationFormInput[]>({ required: true });
const { announcement, completeAction } = useAccessibleCollection();

const certificationKeys = new WeakMap<CvCertificationFormInput, string>();
let nextCertificationKey = 0;

const errorFor = (path: string) => props.errors[path];

const certificationKey = (certification: CvCertificationFormInput) => {
    let key = certificationKeys.get(certification);

    if (!key) {
        key = `certification-${nextCertificationKey}`;
        nextCertificationKey += 1;
        certificationKeys.set(certification, key);
    }

    return key;
};

const announceStructureChange = () => emit('structureChange');

const addCertification = async () => {
    if (certifications.value.length >= maxCertifications) {
        return;
    }

    const index = certifications.value.length;

    certifications.value.push({
        name: '',
        issuer: '',
        issued_on: '',
        expires_on: '',
        credential_id: '',
        credential_url: '',
    });
    announceStructureChange();

    await completeAction(`Certificación ${index + 1} añadida.`, `certification-${index}-name`);
};

const removeCertification = async (index: number) => {
    certifications.value.splice(index, 1);
    announceStructureChange();

    const nextIndex = indexAfterRemoval(index, certifications.value.length);
    const focusId = nextIndex === null ? 'add-certification' : `certification-${nextIndex}-name`;

    await completeAction(
        `Certificación ${index + 1} eliminada. ${remainingItemsMessage(certifications.value.length, 'certificación', 'certificaciones')}`,
        focusId,
    );
};

const moveCertification = async (index: number, offset: -1 | 1) => {
    const target = index + offset;

    if (target < 0 || target >= certifications.value.length) {
        return;
    }

    const [certification] = certifications.value.splice(index, 1);

    if (!certification) {
        return;
    }

    certifications.value.splice(target, 0, certification);
    announceStructureChange();
    await completeAction(`Certificación movida a la posición ${target + 1} de ${certifications.value.length}.`, `certification-${target}-name`);
};
</script>

<template>
    <section class="space-y-5 border-t pt-8" aria-labelledby="editor-certifications-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="editor-certifications-heading" class="text-base font-semibold">Certificaciones</h2>
                <p class="mt-1 text-sm text-muted-foreground">Añade tus credenciales profesionales y ordénalas como aparecerán en el CV.</p>
            </div>

            <Button
                id="add-certification"
                type="button"
                variant="outline"
                size="sm"
                :disabled="certifications.length >= maxCertifications"
                @click="addCertification"
            >
                <Plus />
                Añadir certificación
            </Button>
        </div>

        <InputError :message="errorFor('certifications')" />

        <div v-if="certifications.length === 0" class="rounded-lg border border-dashed px-6 py-8 text-center">
            <div class="mx-auto mb-3 flex size-10 items-center justify-center rounded-full bg-muted">
                <BadgeCheck class="size-5 text-muted-foreground" />
            </div>
            <p class="text-sm font-medium">Aún no has añadido certificaciones</p>
            <p class="mt-1 text-sm text-muted-foreground">Puedes dejar esta sección vacía o agregar tu primera certificación.</p>
        </div>

        <ol v-else class="space-y-5">
            <li v-for="(certification, index) in certifications" :key="certificationKey(certification)">
                <fieldset class="space-y-5 rounded-lg border p-4 sm:p-5">
                    <legend class="sr-only">Certificación {{ index + 1 }}</legend>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-medium">Certificación {{ index + 1 }}</h3>

                        <div class="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="index === 0"
                                :aria-label="`Mover certificación ${index + 1} hacia arriba`"
                                @click="moveCertification(index, -1)"
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :disabled="index === certifications.length - 1"
                                :aria-label="`Mover certificación ${index + 1} hacia abajo`"
                                @click="moveCertification(index, 1)"
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="text-destructive hover:text-destructive"
                                :aria-label="`Eliminar certificación ${index + 1}`"
                                @click="removeCertification(index)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`certification-${index}-name`">Nombre</Label>
                            <Input
                                :id="`certification-${index}-name`"
                                v-model="certification.name"
                                maxlength="160"
                                autocomplete="off"
                                :aria-invalid="Boolean(errorFor(`certifications.${index}.name`))"
                                :aria-describedby="`certification-${index}-name-error`"
                            />
                            <InputError :id="`certification-${index}-name-error`" :message="errorFor(`certifications.${index}.name`)" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`certification-${index}-issuer`">Emisor</Label>
                            <Input
                                :id="`certification-${index}-issuer`"
                                v-model="certification.issuer"
                                maxlength="120"
                                autocomplete="organization"
                                :aria-invalid="Boolean(errorFor(`certifications.${index}.issuer`))"
                                :aria-describedby="`certification-${index}-issuer-error`"
                            />
                            <InputError :id="`certification-${index}-issuer-error`" :message="errorFor(`certifications.${index}.issuer`)" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`certification-${index}-issued-on`">Fecha de emisión</Label>
                            <Input
                                :id="`certification-${index}-issued-on`"
                                v-model="certification.issued_on"
                                type="month"
                                :aria-invalid="Boolean(errorFor(`certifications.${index}.issued_on`))"
                                :aria-describedby="`certification-${index}-issued-on-error`"
                            />
                            <InputError :id="`certification-${index}-issued-on-error`" :message="errorFor(`certifications.${index}.issued_on`)" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`certification-${index}-expires-on`">Fecha de expiración</Label>
                            <Input
                                :id="`certification-${index}-expires-on`"
                                v-model="certification.expires_on"
                                type="month"
                                :aria-invalid="Boolean(errorFor(`certifications.${index}.expires_on`))"
                                :aria-describedby="`certification-${index}-expires-on-error`"
                            />
                            <InputError :id="`certification-${index}-expires-on-error`" :message="errorFor(`certifications.${index}.expires_on`)" />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`certification-${index}-credential-id`">ID de credencial</Label>
                            <Input
                                :id="`certification-${index}-credential-id`"
                                v-model="certification.credential_id"
                                maxlength="120"
                                autocomplete="off"
                                :aria-invalid="Boolean(errorFor(`certifications.${index}.credential_id`))"
                                :aria-describedby="`certification-${index}-credential-id-error`"
                            />
                            <InputError
                                :id="`certification-${index}-credential-id-error`"
                                :message="errorFor(`certifications.${index}.credential_id`)"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`certification-${index}-credential-url`">URL de credencial</Label>
                            <Input
                                :id="`certification-${index}-credential-url`"
                                v-model="certification.credential_url"
                                type="url"
                                maxlength="2048"
                                autocomplete="url"
                                placeholder="https://ejemplo.com/credencial"
                                :aria-invalid="Boolean(errorFor(`certifications.${index}.credential_url`))"
                                :aria-describedby="`certification-${index}-credential-url-error`"
                            />
                            <InputError
                                :id="`certification-${index}-credential-url-error`"
                                :message="errorFor(`certifications.${index}.credential_url`)"
                            />
                        </div>
                    </div>
                </fieldset>
            </li>
        </ol>

        <p v-if="certifications.length >= maxCertifications" class="text-xs text-muted-foreground">
            Alcanzaste el máximo de {{ maxCertifications }} certificaciones permitidas por CV.
        </p>

        <p role="status" aria-live="polite" aria-atomic="true" class="sr-only">{{ announcement }}</p>
    </section>
</template>
