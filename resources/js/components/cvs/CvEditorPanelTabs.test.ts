// @vitest-environment jsdom

import CvEditorPanelTabs, { type CvEditorPanel } from '@/components/cvs/CvEditorPanelTabs.vue';
import { mount } from '@vue/test-utils';
import axe from 'axe-core';
import { afterEach, describe, expect, it } from 'vitest';

afterEach(() => {
    document.body.innerHTML = '';
});

describe('CvEditorPanelTabs', () => {
    it('uses arrow keys and Home/End to select and focus a panel', async () => {
        const wrapper = mount(CvEditorPanelTabs, {
            attachTo: document.body,
            props: {
                modelValue: 'editor' as CvEditorPanel,
                'onUpdate:modelValue': (panel: CvEditorPanel) => wrapper.setProps({ modelValue: panel }),
            },
        });
        const editorTab = wrapper.get('#editor-tab');
        const previewTab = wrapper.get('#preview-tab');

        expect(editorTab.attributes('aria-selected')).toBe('true');
        expect(editorTab.attributes('tabindex')).toBe('0');
        expect(previewTab.attributes('tabindex')).toBe('-1');

        await editorTab.trigger('keydown', { key: 'ArrowRight' });
        expect(previewTab.attributes('aria-selected')).toBe('true');
        expect(document.activeElement).toBe(previewTab.element);

        await previewTab.trigger('keydown', { key: 'Home' });
        expect(editorTab.attributes('aria-selected')).toBe('true');
        expect(document.activeElement).toBe(editorTab.element);

        await editorTab.trigger('keydown', { key: 'End' });
        expect(document.activeElement).toBe(previewTab.element);

        await previewTab.trigger('keydown', { key: 'ArrowLeft' });
        expect(document.activeElement).toBe(editorTab.element);

        wrapper.unmount();
    });

    it('has no detectable semantic accessibility violations', async () => {
        document.body.insertAdjacentHTML('beforeend', '<section id="editor-panel"></section><section id="preview-panel"></section>');
        const wrapper = mount(CvEditorPanelTabs, {
            attachTo: document.body,
            props: {
                modelValue: 'editor' as CvEditorPanel,
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
