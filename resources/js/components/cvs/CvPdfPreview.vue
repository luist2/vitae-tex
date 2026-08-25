<script setup lang="ts">
import { FileWarning, LoaderCircle } from 'lucide-vue-next';
import type { PDFDocumentLoadingTask, PDFDocumentProxy, RenderTask } from 'pdfjs-dist';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

export type CvPdfDisplayStatus = 'loading' | 'ready' | 'error';

interface PreviewPage {
    number: number;
    aspectRatio: number;
    rendered: boolean;
    rendering: boolean;
}

const props = defineProps<{
    source: Blob;
}>();

const emit = defineEmits<{
    'status-change': [status: CvPdfDisplayStatus];
}>();

const a4AspectRatio = 210 / 297;
const container = ref<HTMLElement>();
const pages = ref<PreviewPage[]>([]);
const displayStatus = ref<CvPdfDisplayStatus>('loading');
const pageElements = new Map<number, HTMLElement>();
const renderTasks = new Map<number, RenderTask>();
const renderVersions = new Map<number, number>();
const visiblePages = new Set<number>();
let loadingTask: PDFDocumentLoadingTask | undefined;
let pdfDocument: PDFDocumentProxy | undefined;
let intersectionObserver: IntersectionObserver | undefined;
let resizeObserver: ResizeObserver | undefined;
let resizeFrame: number | undefined;
let activeRun = 0;

let pdfJsPromise: Promise<typeof import('pdfjs-dist')> | undefined;

const loadPdfJs = async () => {
    pdfJsPromise ??= import('pdfjs-dist').then((pdfjs) => {
        pdfjs.GlobalWorkerOptions.workerSrc = new URL('pdfjs-dist/build/pdf.worker.min.mjs', import.meta.url).toString();

        return pdfjs;
    });

    return pdfJsPromise;
};

const setDisplayStatus = (status: CvPdfDisplayStatus) => {
    if (displayStatus.value === status) {
        return;
    }

    displayStatus.value = status;
    emit('status-change', status);
};

const canvasFor = (pageNumber: number): HTMLCanvasElement | undefined =>
    pageElements.get(pageNumber)?.querySelector<HTMLCanvasElement>('canvas') ?? undefined;

const cancelPageRender = (pageNumber: number) => {
    renderVersions.set(pageNumber, (renderVersions.get(pageNumber) ?? 0) + 1);
    renderTasks.get(pageNumber)?.cancel();
    renderTasks.delete(pageNumber);

    const page = pages.value[pageNumber - 1];

    if (page) {
        page.rendering = false;
        page.rendered = false;
    }

    const canvas = canvasFor(pageNumber);

    if (canvas) {
        canvas.width = 0;
        canvas.height = 0;
    }
};

const disposeDocument = () => {
    activeRun += 1;
    intersectionObserver?.disconnect();
    resizeObserver?.disconnect();
    intersectionObserver = undefined;
    resizeObserver = undefined;
    visiblePages.clear();

    if (resizeFrame !== undefined) {
        cancelAnimationFrame(resizeFrame);
        resizeFrame = undefined;
    }

    for (const pageNumber of renderTasks.keys()) {
        cancelPageRender(pageNumber);
    }

    const previousLoadingTask = loadingTask;
    loadingTask = undefined;
    pdfDocument = undefined;
    void previousLoadingTask?.destroy();
};

const failDisplay = (run: number) => {
    if (run !== activeRun) {
        return;
    }

    intersectionObserver?.disconnect();

    for (const pageNumber of renderTasks.keys()) {
        cancelPageRender(pageNumber);
    }

    pages.value = [];
    setDisplayStatus('error');
};

const renderPage = async (pageNumber: number, run: number) => {
    const state = pages.value[pageNumber - 1];
    const element = pageElements.get(pageNumber);
    const document = pdfDocument;

    if (!state || !element || !document || run !== activeRun || state.rendered || state.rendering) {
        return;
    }

    state.rendering = true;
    const renderVersion = (renderVersions.get(pageNumber) ?? 0) + 1;
    renderVersions.set(pageNumber, renderVersion);
    let renderTask: RenderTask | undefined;

    try {
        const page = await document.getPage(pageNumber);

        if (run !== activeRun || renderVersions.get(pageNumber) !== renderVersion) {
            return;
        }

        const unscaledViewport = page.getViewport({ scale: 1 });
        const pageWidth = Math.max(element.clientWidth, 1);
        const scale = pageWidth / unscaledViewport.width;
        const viewport = page.getViewport({ scale });
        const outputScale = Math.min(window.devicePixelRatio || 1, 2);
        const canvas = canvasFor(pageNumber);
        const context = canvas?.getContext('2d');

        if (!canvas || !context) {
            throw new Error('Canvas unavailable');
        }

        state.aspectRatio = unscaledViewport.width / unscaledViewport.height;
        canvas.width = Math.max(1, Math.floor(viewport.width * outputScale));
        canvas.height = Math.max(1, Math.floor(viewport.height * outputScale));
        canvas.style.width = `${Math.floor(viewport.width)}px`;
        canvas.style.height = `${Math.floor(viewport.height)}px`;

        renderTask = page.render({
            canvas,
            canvasContext: context,
            viewport,
            transform: outputScale === 1 ? undefined : [outputScale, 0, 0, outputScale, 0, 0],
        });
        renderTasks.set(pageNumber, renderTask);
        await renderTask.promise;

        if (run !== activeRun || renderVersions.get(pageNumber) !== renderVersion) {
            return;
        }

        state.rendered = true;
        setDisplayStatus('ready');
    } catch (error) {
        if (error instanceof Error && error.name === 'RenderingCancelledException') {
            return;
        }

        failDisplay(run);
    } finally {
        const currentState = pages.value[pageNumber - 1];

        if (currentState && renderVersions.get(pageNumber) === renderVersion) {
            currentState.rendering = false;
        }

        if (renderTask && renderTasks.get(pageNumber) === renderTask) {
            renderTasks.delete(pageNumber);
        }
    }
};

const observePages = (run: number) => {
    const root = container.value;

    if (!root || run !== activeRun) {
        return;
    }

    intersectionObserver = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                const pageNumber = Number((entry.target as HTMLElement).dataset.pageNumber);

                if (!Number.isSafeInteger(pageNumber)) {
                    continue;
                }

                if (entry.isIntersecting) {
                    visiblePages.add(pageNumber);
                    void renderPage(pageNumber, run);
                } else {
                    visiblePages.delete(pageNumber);
                    cancelPageRender(pageNumber);
                }
            }
        },
        { root, rootMargin: '100% 0px' },
    );

    for (const element of pageElements.values()) {
        intersectionObserver.observe(element);
    }

    resizeObserver = new ResizeObserver(() => {
        if (resizeFrame !== undefined) {
            cancelAnimationFrame(resizeFrame);
        }

        resizeFrame = requestAnimationFrame(() => {
            resizeFrame = undefined;

            for (const pageNumber of visiblePages) {
                cancelPageRender(pageNumber);
                void renderPage(pageNumber, run);
            }
        });
    });
    resizeObserver.observe(root);
};

const openDocument = async (source: Blob) => {
    disposeDocument();
    const run = activeRun;
    pageElements.clear();
    renderVersions.clear();
    pages.value = [];
    displayStatus.value = 'loading';
    emit('status-change', 'loading');

    try {
        const data = new Uint8Array(await source.arrayBuffer());
        const pdfjs = await loadPdfJs();

        if (run !== activeRun) {
            return;
        }

        const nextLoadingTask = pdfjs.getDocument({ data, verbosity: pdfjs.VerbosityLevel.ERRORS });
        loadingTask = nextLoadingTask;
        const nextDocument = await nextLoadingTask.promise;

        if (run !== activeRun) {
            void nextLoadingTask.destroy();

            return;
        }

        pdfDocument = nextDocument;
        pages.value = Array.from({ length: nextDocument.numPages }, (_, index) => ({
            number: index + 1,
            aspectRatio: a4AspectRatio,
            rendered: false,
            rendering: false,
        }));
        await nextTick();
        observePages(run);
    } catch {
        failDisplay(run);
    }
};

const setPageElement = (element: Element | null, pageNumber: number) => {
    if (element instanceof HTMLElement) {
        pageElements.set(pageNumber, element);
    } else {
        pageElements.delete(pageNumber);
    }
};

watch(
    () => props.source,
    (source) => void openDocument(source),
    { immediate: true },
);

onBeforeUnmount(disposeDocument);
</script>

<template>
    <div
        ref="container"
        role="region"
        aria-label="Documento PDF del CV"
        aria-describedby="cv-pdf-preview-description"
        :aria-busy="displayStatus === 'loading'"
        tabindex="0"
        class="h-full min-h-[32rem] w-full overflow-y-auto bg-muted/50 p-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring sm:p-5 lg:min-h-0"
    >
        <p id="cv-pdf-preview-description" class="sr-only">Vista visual del documento. Descarga el PDF para acceder al archivo completo.</p>

        <div v-if="displayStatus === 'error'" role="alert" class="flex h-full min-h-80 items-center justify-center p-6">
            <div class="max-w-sm text-center">
                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full border bg-background shadow-sm">
                    <FileWarning class="size-6 text-destructive" aria-hidden="true" />
                </div>
                <h2 class="font-semibold">No se pudo mostrar el preview</h2>
                <p class="mt-2 text-sm text-muted-foreground">Puedes descargar el PDF para abrirlo en otro visor.</p>
            </div>
        </div>

        <div v-else-if="pages.length === 0" role="status" class="flex h-full min-h-80 items-center justify-center p-6">
            <div class="text-center">
                <LoaderCircle class="mx-auto size-7 animate-spin text-muted-foreground" aria-hidden="true" />
                <p class="mt-3 text-sm text-muted-foreground">Preparando el preview…</p>
            </div>
        </div>

        <div v-else class="mx-auto flex max-w-[52rem] flex-col items-center gap-4 sm:gap-6">
            <div
                v-for="page in pages"
                :key="page.number"
                :ref="(element) => setPageElement(element as Element | null, page.number)"
                :data-page-number="page.number"
                :style="{ aspectRatio: String(page.aspectRatio) }"
                class="relative w-full shrink-0 overflow-hidden bg-white shadow-md ring-1 ring-black/10"
            >
                <canvas role="img" :aria-label="`Página ${page.number} de ${pages.length}`" class="absolute inset-0 h-full w-full bg-white" />
                <div v-if="page.rendering" class="absolute inset-0 flex items-center justify-center bg-white" aria-hidden="true">
                    <LoaderCircle class="size-6 animate-spin text-slate-400" />
                </div>
            </div>
        </div>
    </div>
</template>
