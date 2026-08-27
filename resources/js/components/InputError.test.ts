// @vitest-environment jsdom

import InputError from '@/components/InputError.vue';
import { inputErrorAnnouncementKey } from '@/lib/inputErrorAccessibility';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('InputError', () => {
    it('announces errors by default for standalone forms', () => {
        const wrapper = mount(InputError, { props: { message: 'Revisa este campo.' } });

        expect(wrapper.attributes('role')).toBe('alert');
        expect(wrapper.attributes('aria-atomic')).toBe('true');
    });

    it('leaves announcements to a parent summary when configured', () => {
        const wrapper = mount(InputError, {
            props: { message: 'Revisa este campo.' },
            global: {
                provide: {
                    [inputErrorAnnouncementKey as symbol]: false,
                },
            },
        });

        expect(wrapper.attributes('role')).toBeUndefined();
        expect(wrapper.attributes('aria-atomic')).toBeUndefined();
        expect(wrapper.text()).toBe('Revisa este campo.');
    });
});
