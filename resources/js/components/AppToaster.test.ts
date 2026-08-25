// @vitest-environment jsdom

import AppToaster from '@/components/AppToaster.vue';
import { mount } from '@vue/test-utils';
import axe from 'axe-core';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Toaster, toast } from 'vue-sonner';

const { removeFlashListener, routerOn } = vi.hoisted(() => ({
    removeFlashListener: vi.fn(),
    routerOn: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        on: routerOn,
    },
}));

type FlashListener = (event: { detail: { flash: { toast?: unknown } } }) => void;

let flashListener: FlashListener | undefined;

beforeEach(() => {
    flashListener = undefined;
    routerOn.mockImplementation((_event: string, listener: FlashListener) => {
        flashListener = listener;

        return removeFlashListener;
    });
    vi.spyOn(toast, 'success').mockImplementation(() => 1);
});

afterEach(() => {
    vi.restoreAllMocks();
    vi.clearAllMocks();
    document.body.innerHTML = '';
});

describe('AppToaster', () => {
    it('shows an initial success and configures an accessible temporary toaster', async () => {
        const wrapper = mount(AppToaster, {
            attachTo: document.body,
            props: {
                initialToast: {
                    type: 'success',
                    message: 'CV creado correctamente.',
                },
            },
        });

        expect(toast.success).toHaveBeenCalledWith('CV creado correctamente.');

        const toaster = wrapper.getComponent(Toaster);
        expect(toaster.props()).toMatchObject({
            position: 'bottom-right',
            duration: 7000,
            visibleToasts: 3,
            closeButton: true,
            containerAriaLabel: 'Notificaciones',
        });
        expect(toaster.props('toastOptions')).toMatchObject({
            unstyled: true,
            closeButtonAriaLabel: 'Cerrar notificación',
        });
        expect(wrapper.get('[aria-live="polite"]').attributes('aria-relevant')).toBe('additions text');

        const result = await axe.run(wrapper.element, {
            rules: {
                'color-contrast': { enabled: false },
            },
        });
        expect(result.violations).toEqual([]);

        wrapper.unmount();
        expect(removeFlashListener).toHaveBeenCalledOnce();
    });

    it('shows every flash event, including consecutive identical messages', () => {
        const wrapper = mount(AppToaster);
        const event = {
            detail: {
                flash: {
                    toast: {
                        type: 'success',
                        message: 'CV guardado correctamente.',
                    },
                },
            },
        };

        flashListener?.(event);
        flashListener?.(event);

        expect(toast.success).toHaveBeenNthCalledWith(1, 'CV guardado correctamente.');
        expect(toast.success).toHaveBeenNthCalledWith(2, 'CV guardado correctamente.');
        wrapper.unmount();
    });

    it('ignores missing, unsupported, and empty flash payloads', () => {
        const wrapper = mount(AppToaster);

        flashListener?.({ detail: { flash: {} } });
        flashListener?.({ detail: { flash: { toast: { type: 'error', message: 'Error inesperado.' } } } });
        flashListener?.({ detail: { flash: { toast: { type: 'success', message: '   ' } } } });

        expect(toast.success).not.toHaveBeenCalled();
        wrapper.unmount();
    });
});
