// @vitest-environment jsdom

import CvEditorErrorSummary from '@/components/cvs/CvEditorErrorSummary.vue';
import { mount } from '@vue/test-utils';
import axe from 'axe-core';
import { afterEach, describe, expect, it } from 'vitest';

afterEach(() => {
    document.body.innerHTML = '';
});

describe('CvEditorErrorSummary', () => {
    it('presents every error with linked controls and a visible fallback', async () => {
        document.body.innerHTML = '<input id="cv-full-name">';
        const wrapper = mount(CvEditorErrorSummary, {
            attachTo: document.body,
            props: {
                items: [
                    { path: 'full_name', message: 'El nombre es obligatorio.', targetId: 'cv-full-name' },
                    { path: 'unexpected_path', message: 'Revisa el contenido enviado.' },
                ],
            },
        });

        expect(wrapper.attributes('role')).toBe('alert');
        expect(wrapper.attributes('tabindex')).toBe('-1');
        expect(wrapper.text()).toContain('Hay 2 errores.');
        expect(wrapper.text()).toContain('El nombre es obligatorio.');
        expect(wrapper.text()).toContain('Revisa el contenido enviado.');
        expect(wrapper.text()).toContain('No se pudo localizar automáticamente este campo.');

        const link = wrapper.get('button');
        expect(link.attributes('aria-controls')).toBe('cv-full-name');
        await link.trigger('click');
        expect(wrapper.emitted('select')).toEqual([['full_name']]);

        const result = await axe.run(wrapper.element, {
            rules: {
                'color-contrast': { enabled: false },
            },
        });
        expect(result.violations).toEqual([]);
        wrapper.unmount();
    });

    it('focuses the visible summary when no error has a destination', () => {
        const wrapper = mount(CvEditorErrorSummary, {
            attachTo: document.body,
            props: {
                items: [{ path: 'unexpected_path', message: 'Revisa el contenido enviado.' }],
            },
        });

        (wrapper.element as HTMLElement).focus();

        expect(document.activeElement).toBe(wrapper.element);
        expect(wrapper.text()).toContain('Hay 1 error.');
        expect(wrapper.text()).toContain('Revisa las secciones del formulario');
        wrapper.unmount();
    });
});
