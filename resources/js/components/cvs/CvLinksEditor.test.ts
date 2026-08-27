// @vitest-environment jsdom

import CvLinksEditor from '@/components/cvs/CvLinksEditor.vue';
import type { CvLinkFormInput } from '@/types';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { reactive } from 'vue';

describe('CvLinksEditor error lifecycle', () => {
    it('reports a type edit without treating it as an index change', async () => {
        const links = reactive<CvLinkFormInput[]>([
            { type: 'linkedin', label: '', url: 'https://linkedin.com/in/example' },
            { type: 'github', label: '', url: 'https://github.com/example' },
        ]);
        const wrapper = mount(CvLinksEditor, {
            props: {
                modelValue: links,
                errors: {
                    'links.0.label': 'La etiqueta es obligatoria.',
                    'links.1.url': 'La URL no es válida.',
                },
            },
        });

        await wrapper.get('#link-0-type').setValue('other');

        expect(wrapper.emitted('fieldChange')).toEqual([[['links.0.type', 'links.0.label', 'contact_email']]]);
        expect(wrapper.emitted('structureChange')).toBeUndefined();
    });

    it('reports reordering as a structural change so indexed errors can be invalidated', async () => {
        const links = reactive<CvLinkFormInput[]>([
            { type: 'linkedin', label: '', url: 'https://linkedin.com/in/example' },
            { type: 'github', label: '', url: 'https://github.com/example' },
        ]);
        const wrapper = mount(CvLinksEditor, {
            props: {
                modelValue: links,
                errors: {},
            },
        });

        await wrapper.get('[aria-label="Mover enlace 2 hacia arriba"]').trigger('click');

        expect(wrapper.emitted('structureChange')).toHaveLength(1);
        expect(wrapper.emitted('fieldChange')).toBeUndefined();
    });
});
