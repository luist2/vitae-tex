<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        id: string;
        label: string;
        disabled?: boolean;
        ariaInvalid?: boolean;
        ariaDescribedby?: string;
    }>(),
    {
        disabled: false,
        ariaInvalid: false,
        ariaDescribedby: undefined,
    },
);

const model = defineModel<string>({ required: true });

const months = [
    { value: '01', label: 'Enero' },
    { value: '02', label: 'Febrero' },
    { value: '03', label: 'Marzo' },
    { value: '04', label: 'Abril' },
    { value: '05', label: 'Mayo' },
    { value: '06', label: 'Junio' },
    { value: '07', label: 'Julio' },
    { value: '08', label: 'Agosto' },
    { value: '09', label: 'Septiembre' },
    { value: '10', label: 'Octubre' },
    { value: '11', label: 'Noviembre' },
    { value: '12', label: 'Diciembre' },
];

const parts = computed(() => {
    const separator = model.value.indexOf('-');

    if (separator === -1) {
        return { year: model.value.replace(/\D/g, '').slice(0, 4), month: '' };
    }

    return {
        year: model.value.slice(0, separator).replace(/\D/g, '').slice(0, 4),
        month: model.value.slice(separator + 1),
    };
});

const updateModel = (year: string, month: string) => {
    model.value = year === '' && month === '' ? '' : `${year}-${month}`;
};

const month = computed({
    get: () => parts.value.month,
    set: (value: string) => updateModel(parts.value.year, value),
});

const year = computed({
    get: () => parts.value.year,
    set: (value: string | number) => updateModel(String(value).replace(/\D/g, '').slice(0, 4), parts.value.month),
});
</script>

<template>
    <fieldset class="grid gap-2" :disabled="props.disabled">
        <legend class="text-sm font-medium leading-none">{{ props.label }}</legend>

        <div class="grid grid-cols-[minmax(0,1fr)_minmax(6.5rem,0.65fr)] gap-2">
            <div class="min-w-0">
                <Label :for="props.id" class="sr-only">Mes</Label>
                <select
                    :id="props.id"
                    v-model="month"
                    :disabled="props.disabled"
                    :aria-invalid="props.ariaInvalid"
                    :aria-describedby="props.ariaDescribedby"
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <option value="">Mes</option>
                    <option v-for="option in months" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <div class="min-w-0">
                <Label :for="`${props.id}-year`" class="sr-only">Año</Label>
                <Input
                    :id="`${props.id}-year`"
                    v-model="year"
                    type="text"
                    inputmode="numeric"
                    pattern="[0-9]{4}"
                    maxlength="4"
                    autocomplete="off"
                    placeholder="AAAA"
                    :disabled="props.disabled"
                    :aria-invalid="props.ariaInvalid"
                    :aria-describedby="props.ariaDescribedby"
                />
            </div>
        </div>
    </fieldset>
</template>
