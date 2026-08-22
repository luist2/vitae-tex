// @vitest-environment jsdom

import CvWorkExperiencesEditor from '@/components/cvs/CvWorkExperiencesEditor.vue';
import type { CvWorkExperienceFormInput } from '@/types';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import { reactive } from 'vue';

const mountedWrappers: VueWrapper[] = [];

const experience = (): CvWorkExperienceFormInput => ({
    employer: 'Acme',
    role: 'Desarrolladora',
    location: '',
    start_date: '2025-01',
    end_date: '2025-12',
    is_current: false,
    highlights: [],
});

const mountEditor = (experiences: CvWorkExperienceFormInput[], errors: Partial<Record<string, string>> = {}) => {
    const wrapper = mount(CvWorkExperiencesEditor, {
        attachTo: document.body,
        props: {
            modelValue: reactive(experiences),
            errors,
        },
    });

    mountedWrappers.push(wrapper);

    return wrapper;
};

afterEach(() => {
    mountedWrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    document.body.innerHTML = '';
});

describe('CvWorkExperiencesEditor accessibility', () => {
    it('moves focus into a newly added highlight and restores it after removal', async () => {
        const experiences = [experience()];
        const wrapper = mountEditor(experiences);

        await wrapper.get('#add-work-experience-0-highlight').trigger('click');
        await flushPromises();

        expect(document.activeElement).toBe(document.getElementById('work-experience-0-highlight-0'));
        expect(wrapper.get('[role="status"]').text()).toContain('Punto destacado 1 añadido');

        await wrapper.get('[aria-label="Eliminar punto destacado 1 de la experiencia 1"]').trigger('click');
        await flushPromises();

        expect(experiences[0]?.highlights).toEqual([]);
        expect(document.activeElement).toBe(document.getElementById('add-work-experience-0-highlight'));
    });

    it('associates current-state validation errors with the checkbox', () => {
        const wrapper = mountEditor([experience()], {
            'work_experiences.0.is_current': 'El estado actual no es válido.',
        });
        const checkbox = wrapper.get('#work-experience-0-is-current');

        expect(checkbox.attributes('aria-invalid')).toBe('true');
        expect(checkbox.attributes('aria-describedby')).toBe('work-experience-0-is-current-error');
        expect(wrapper.get('#work-experience-0-is-current-error').attributes('role')).toBe('alert');
    });
});
