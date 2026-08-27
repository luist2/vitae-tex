// @vitest-environment jsdom

import CvEditorActions from '@/components/cvs/CvEditorActions.vue';
import { mount } from '@vue/test-utils';
import axe from 'axe-core';
import { afterEach, describe, expect, it } from 'vitest';

afterEach(() => {
    document.body.innerHTML = '';
});

const mountActions = (props: Partial<InstanceType<typeof CvEditorActions>['$props']> = {}) =>
    mount(CvEditorActions, {
        props: {
            saveFormId: 'cv-editor-form',
            isDirty: false,
            isSaving: false,
            previewStatus: 'idle',
            hasPreview: false,
            previewIsStale: false,
            previewRetryAfterSeconds: 0,
            ...props,
        },
    });

describe('CvEditorActions', () => {
    it('keeps save and generation together while enforcing their sequence', async () => {
        const wrapper = mountActions({ isDirty: true });
        const buttons = wrapper.findAll('button');

        expect(buttons).toHaveLength(2);
        expect(buttons[0].text()).toContain('Guardar cambios');
        expect(buttons[0].attributes('disabled')).toBeUndefined();
        expect(buttons[1].text()).toContain('Generar CV');
        expect(buttons[1].attributes('disabled')).toBeDefined();

        expect(buttons[0].attributes('type')).toBe('submit');
        expect(buttons[0].attributes('form')).toBe('cv-editor-form');

        await wrapper.setProps({ isDirty: false });

        expect(buttons[0].attributes('disabled')).toBeDefined();
        expect(buttons[1].attributes('disabled')).toBeUndefined();
        expect(wrapper.text()).toContain('CV guardado.');

        await buttons[1].trigger('click');
        expect(wrapper.emitted('generate')).toHaveLength(1);
    });

    it('offers regeneration for a stale preview and prevents redundant generation for a current one', async () => {
        const wrapper = mountActions({ hasPreview: true, previewIsStale: true });
        const generationButton = wrapper.findAll('button')[1];

        expect(generationButton.text()).toContain('Regenerar CV');
        expect(generationButton.attributes('disabled')).toBeUndefined();

        await wrapper.setProps({ previewIsStale: false, previewStatus: 'ready' });

        expect(generationButton.text()).toContain('CV generado');
        expect(generationButton.attributes('disabled')).toBeDefined();
        expect(wrapper.text()).not.toContain('El preview está actualizado');
    });

    it('uses only an assistive announcement in the action bar while generating', () => {
        const wrapper = mountActions({ previewStatus: 'generating' });
        const generationButton = wrapper.findAll('button')[1];

        expect(generationButton.text()).toContain('Generar CV');
        expect(generationButton.attributes('aria-busy')).toBe('true');
        expect(generationButton.find('.animate-spin').exists()).toBe(false);
        expect(wrapper.get('[aria-live="polite"] .sr-only').text()).toBe('Generando el preview…');
    });

    it('disables regeneration and shows the remaining rate-limit cooldown', async () => {
        const wrapper = mountActions({
            previewStatus: 'error',
            hasPreview: true,
            previewIsStale: true,
            previewRetryAfterSeconds: 17,
        });
        const generationButton = wrapper.findAll('button')[1];

        expect(generationButton.text()).toContain('Reintentar en 17 s');
        expect(generationButton.attributes('disabled')).toBeDefined();
        expect(generationButton.attributes('aria-describedby')).toBe('cv-generation-rate-limit-help');
        expect(wrapper.get('#cv-generation-rate-limit-help').text()).toContain('Podrás regenerar dentro de 17 s.');

        await generationButton.trigger('click');
        expect(wrapper.emitted('generate')).toBeUndefined();

        await wrapper.setProps({ previewRetryAfterSeconds: 0, previewStatus: 'ready' });
        expect(generationButton.text()).toContain('Regenerar CV');
        expect(generationButton.attributes('disabled')).toBeUndefined();
    });

    it('has no detectable semantic accessibility violations', async () => {
        const wrapper = mount(CvEditorActions, {
            attachTo: document.body,
            props: {
                saveFormId: 'cv-editor-form',
                isDirty: true,
                isSaving: false,
                previewStatus: 'idle',
                hasPreview: false,
                previewIsStale: false,
                previewRetryAfterSeconds: 0,
            },
        });
        const result = await axe.run(wrapper.element, {
            rules: {
                'color-contrast': { enabled: false },
            },
        });

        expect(result.violations).toEqual([]);
        wrapper.unmount();
    });
});
