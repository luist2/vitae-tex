// @vitest-environment jsdom

import MonthYearInput from '@/components/cvs/MonthYearInput.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';

const mountInput = (
    initialValue = '',
    props: Partial<{
        disabled: boolean;
        ariaInvalid: boolean;
        ariaDescribedby: string;
    }> = {},
) => {
    const value = ref(initialValue);
    const Harness = defineComponent({
        setup() {
            return () =>
                h(MonthYearInput, {
                    id: 'employment-start',
                    label: 'Fecha de inicio',
                    modelValue: value.value,
                    'onUpdate:modelValue': (nextValue: string) => {
                        value.value = nextValue;
                    },
                    ...props,
                });
        },
    });

    return { wrapper: mount(Harness), value };
};

describe('MonthYearInput', () => {
    it('loads a canonical month and year with an accessible group label', () => {
        const { wrapper } = mountInput('2025-07', {
            ariaInvalid: true,
            ariaDescribedby: 'employment-start-error',
        });
        const month = wrapper.get<HTMLSelectElement>('#employment-start');
        const year = wrapper.get<HTMLInputElement>('#employment-start-year');

        expect(wrapper.get('legend').text()).toBe('Fecha de inicio');
        expect(month.element.value).toBe('07');
        expect(year.element.value).toBe('2025');
        expect(month.attributes('aria-invalid')).toBe('true');
        expect(year.attributes('aria-invalid')).toBe('true');
        expect(month.attributes('aria-describedby')).toBe('employment-start-error');
        expect(year.attributes('aria-describedby')).toBe('employment-start-error');
    });

    it('emits canonical, partial, and empty values without discarding user input', async () => {
        const { wrapper, value } = mountInput();
        const month = wrapper.get<HTMLSelectElement>('#employment-start');
        const year = wrapper.get<HTMLInputElement>('#employment-start-year');

        await month.setValue('03');
        expect(value.value).toBe('-03');

        await year.setValue('2026');
        expect(value.value).toBe('2026-03');

        await month.setValue('');
        expect(value.value).toBe('2026-');

        await year.setValue('');
        expect(value.value).toBe('');
    });

    it('keeps only the first four numeric year characters', async () => {
        const { wrapper, value } = mountInput('-11');

        await wrapper.get<HTMLInputElement>('#employment-start-year').setValue('20a268');
        await nextTick();

        expect(value.value).toBe('2026-11');
        expect(wrapper.get<HTMLInputElement>('#employment-start-year').element.value).toBe('2026');
    });

    it('disables both controls together', () => {
        const { wrapper } = mountInput('2025-12', { disabled: true });

        expect(wrapper.get('#employment-start').attributes()).toHaveProperty('disabled');
        expect(wrapper.get('#employment-start-year').attributes()).toHaveProperty('disabled');
    });
});
