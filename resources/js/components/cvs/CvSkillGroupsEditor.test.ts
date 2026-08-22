// @vitest-environment jsdom

import CvSkillGroupsEditor from '@/components/cvs/CvSkillGroupsEditor.vue';
import type { CvSkillGroupInput } from '@/types';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import axe from 'axe-core';
import { afterEach, describe, expect, it } from 'vitest';
import { reactive } from 'vue';

const mountedWrappers: VueWrapper[] = [];

const mountEditor = (groups: CvSkillGroupInput[], errors: Partial<Record<string, string>> = {}) => {
    const wrapper = mount(CvSkillGroupsEditor, {
        attachTo: document.body,
        props: {
            modelValue: reactive(groups),
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

describe('CvSkillGroupsEditor accessibility', () => {
    it('focuses and announces additions, movements and removals', async () => {
        const groups: CvSkillGroupInput[] = [
            { name: 'Lenguajes', skills: [{ name: 'PHP' }] },
            { name: 'Herramientas', skills: [{ name: 'Git' }] },
        ];
        const wrapper = mountEditor(groups);

        await wrapper.get('[aria-label="Mover grupo 2 hacia arriba"]').trigger('click');
        await flushPromises();

        expect(groups[0]?.name).toBe('Herramientas');
        expect(document.activeElement).toBe(document.getElementById('skill-group-0-name'));
        expect(wrapper.get('[role="status"]').text()).toBe('Grupo movido a la posición 1 de 2.');

        await wrapper.get('[aria-label="Eliminar grupo 1"]').trigger('click');
        await flushPromises();

        expect(groups).toHaveLength(1);
        expect(document.activeElement).toBe(document.getElementById('skill-group-0-name'));
        expect(wrapper.get('[role="status"]').text()).toContain('Grupo 1 eliminado');
    });

    it('keeps focus inside a group when a skill is removed', async () => {
        const groups: CvSkillGroupInput[] = [{ name: 'Lenguajes', skills: [{ name: 'PHP' }, { name: 'TypeScript' }] }];
        const wrapper = mountEditor(groups);

        await wrapper.get('[aria-label="Eliminar habilidad 2 del grupo 1"]').trigger('click');
        await flushPromises();

        expect(groups[0]?.skills).toHaveLength(1);
        expect(document.activeElement).toBe(document.getElementById('skill-group-0-skill-0'));
        expect(wrapper.get('[role="status"]').text()).toContain('Habilidad 2 eliminada');
    });

    it('associates validation errors and has no detectable semantic violations', async () => {
        const wrapper = mountEditor([{ name: '', skills: [{ name: '' }] }], {
            'skill_groups.0.name': 'El nombre del grupo es obligatorio.',
            'skill_groups.0.skills.0.name': 'La habilidad es obligatoria.',
        });

        expect(wrapper.get('#skill-group-0-name').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('#skill-group-0-name-error').attributes('role')).toBe('alert');
        expect(wrapper.get('#skill-group-0-skill-0').attributes('aria-describedby')).toBe('skill-group-0-skill-0-error');

        const result = await axe.run(wrapper.element, {
            rules: {
                'color-contrast': { enabled: false },
            },
        });

        expect(result.violations).toEqual([]);
    });
});
