// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import axe from 'axe-core';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import CvPdfPreview from './CvPdfPreview.vue';

const getDocument = vi.fn();
const globalWorkerOptions = { workerSrc: '' };

vi.mock('pdfjs-dist', () => ({
    getDocument,
    GlobalWorkerOptions: globalWorkerOptions,
    VerbosityLevel: { ERRORS: 0 },
}));

class IntersectionObserverMock {
    static instances: IntersectionObserverMock[] = [];

    readonly elements: Element[] = [];
    readonly disconnect = vi.fn();

    constructor(
        private readonly callback: IntersectionObserverCallback,
        readonly options?: IntersectionObserverInit,
    ) {
        IntersectionObserverMock.instances.push(this);
    }

    observe = (element: Element) => {
        this.elements.push(element);
    };

    unobserve = vi.fn();
    takeRecords = vi.fn().mockReturnValue([]);

    trigger(element: Element, isIntersecting: boolean) {
        this.callback([{ target: element, isIntersecting } as IntersectionObserverEntry], this as unknown as IntersectionObserver);
    }
}

class ResizeObserverMock {
    static instances: ResizeObserverMock[] = [];

    readonly disconnect = vi.fn();
    readonly observe = vi.fn();
    readonly unobserve = vi.fn();

    constructor(readonly callback: ResizeObserverCallback) {
        ResizeObserverMock.instances.push(this);
    }

    trigger() {
        this.callback([], this as unknown as ResizeObserver);
    }
}

const source = (name: string): Blob =>
    ({
        arrayBuffer: vi.fn().mockResolvedValue(new TextEncoder().encode(`%PDF-${name}`).buffer),
    }) as unknown as Blob;

const documentFixture = (pageCount = 1, renderPromise: Promise<void> = Promise.resolve()) => {
    const cancel = vi.fn();
    const render = vi.fn().mockReturnValue({ promise: renderPromise, cancel });
    const getPage = vi.fn().mockResolvedValue({
        getViewport: ({ scale }: { scale: number }) => ({ width: 595 * scale, height: 842 * scale }),
        render,
    });
    const destroy = vi.fn().mockResolvedValue(undefined);
    const document = { numPages: pageCount, getPage };

    getDocument.mockReturnValue({ promise: Promise.resolve(document), destroy });

    return { cancel, destroy, getPage, render };
};

const preparePages = async (pageCount = 1, renderPromise: Promise<void> = Promise.resolve()) => {
    const fixture = documentFixture(pageCount, renderPromise);
    const wrapper = mount(CvPdfPreview, { props: { source: source('first') }, attachTo: document.body });
    await flushPromises();
    const observer = IntersectionObserverMock.instances.at(-1)!;

    return { fixture, observer, wrapper };
};

describe('CvPdfPreview', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        getDocument.mockReset();
        globalWorkerOptions.workerSrc = '';
        IntersectionObserverMock.instances = [];
        ResizeObserverMock.instances = [];
        vi.stubGlobal('IntersectionObserver', IntersectionObserverMock);
        vi.stubGlobal('ResizeObserver', ResizeObserverMock);
        vi.stubGlobal('requestAnimationFrame', vi.fn().mockReturnValue(1));
        vi.stubGlobal('cancelAnimationFrame', vi.fn());
        vi.stubGlobal('devicePixelRatio', 3);
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({} as CanvasRenderingContext2D);
        vi.spyOn(HTMLElement.prototype, 'clientWidth', 'get').mockReturnValue(595);
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('loads PDF.js lazily and renders only pages entering the nearby viewport', async () => {
        const { fixture, observer, wrapper } = await preparePages(2);
        const pageElements = wrapper.findAll('[data-page-number]');

        expect(pageElements).toHaveLength(2);
        expect(fixture.getPage).not.toHaveBeenCalled();
        expect(observer.options).toMatchObject({ rootMargin: '100% 0px' });

        observer.trigger(pageElements[0].element, true);
        await flushPromises();

        expect(fixture.getPage).toHaveBeenCalledOnce();
        expect(fixture.getPage).toHaveBeenCalledWith(1);
        expect(fixture.render).toHaveBeenCalledWith(
            expect.objectContaining({
                canvas: expect.any(HTMLCanvasElement),
                transform: [2, 0, 0, 2, 0, 0],
            }),
        );
        expect(wrapper.emitted('status-change')).toEqual([['loading'], ['ready']]);
        expect(wrapper.get('canvas').attributes('aria-label')).toBe('Página 1 de 2');
    });

    it('releases an offscreen canvas and renders it again after resizing', async () => {
        const { fixture, observer, wrapper } = await preparePages();
        const pageElement = wrapper.get('[data-page-number]');
        observer.trigger(pageElement.element, true);
        await flushPromises();

        observer.trigger(pageElement.element, false);

        const canvas = wrapper.get('canvas').element as HTMLCanvasElement;
        expect(canvas.width).toBe(0);
        expect(canvas.height).toBe(0);

        observer.trigger(pageElement.element, true);
        await flushPromises();
        expect(fixture.render).toHaveBeenCalledTimes(2);

        ResizeObserverMock.instances[0].trigger();
        const resizeCallback = vi.mocked(requestAnimationFrame).mock.calls[0][0];
        resizeCallback(0);
        await flushPromises();

        expect(fixture.render).toHaveBeenCalledTimes(3);
    });

    it('cancels active work and destroys the previous document when its source changes or it unmounts', async () => {
        let resolveRender: () => void = () => undefined;
        const pendingRender = new Promise<void>((resolve) => {
            resolveRender = resolve;
        });
        const first = documentFixture(1, pendingRender);
        const wrapper = mount(CvPdfPreview, { props: { source: source('first') } });
        await flushPromises();
        const firstObserver = IntersectionObserverMock.instances[0];
        firstObserver.trigger(wrapper.get('[data-page-number]').element, true);
        await flushPromises();

        const second = documentFixture(1);
        await wrapper.setProps({ source: source('second') });
        await flushPromises();

        expect(first.cancel).toHaveBeenCalledOnce();
        expect(first.destroy).toHaveBeenCalledOnce();
        expect(firstObserver.disconnect).toHaveBeenCalledOnce();

        wrapper.unmount();
        expect(second.destroy).toHaveBeenCalledOnce();
        resolveRender();
        await flushPromises();
    });

    it('shows a safe error while leaving the generated PDF available to the parent', async () => {
        const destroy = vi.fn().mockResolvedValue(undefined);
        getDocument.mockReturnValue({ promise: Promise.reject(new Error('private parser details')), destroy });
        const wrapper = mount(CvPdfPreview, { props: { source: source('broken') } });
        await flushPromises();

        expect(wrapper.text()).toContain('No se pudo mostrar el preview');
        expect(wrapper.text()).toContain('Puedes descargar el PDF');
        expect(wrapper.text()).not.toContain('private parser details');
        expect(wrapper.emitted('status-change')).toEqual([['loading'], ['error']]);
    });

    it('has no detectable semantic accessibility violations', async () => {
        const { observer, wrapper } = await preparePages();
        observer.trigger(wrapper.get('[data-page-number]').element, true);
        await flushPromises();
        const result = await axe.run(wrapper.element, { rules: { 'color-contrast': { enabled: false } } });

        expect(result.violations).toEqual([]);
        wrapper.unmount();
    });
});
