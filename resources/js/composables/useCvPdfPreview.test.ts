import { afterEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

import { useCvPdfPreview } from './useCvPdfPreview';

const pdfResponse = (revision = 1, filename = 'cv-backend.pdf') =>
    new Response(new Blob(['%PDF-1.7 preview'], { type: 'application/pdf' }), {
        headers: {
            'Content-Disposition': `inline; filename="${filename}"`,
            'Content-Type': 'application/pdf',
            'X-CV-Revision': String(revision),
        },
    });

const stubObjectUrls = () => {
    const createObjectURL = vi.fn().mockReturnValueOnce('blob:cv-preview').mockReturnValueOnce('blob:regenerated-preview');
    const revokeObjectURL = vi.fn();
    vi.stubGlobal('URL', { createObjectURL, revokeObjectURL });

    return { createObjectURL, revokeObjectURL };
};

describe('useCvPdfPreview', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('generates a PDF only after the explicit action and exposes its revision', async () => {
        let resolveResponse: (response: Response) => void = () => undefined;
        const pendingResponse = new Promise<Response>((resolve) => {
            resolveResponse = resolve;
        });
        const fetchMock = vi.fn().mockReturnValue(pendingResponse);
        const { createObjectURL, revokeObjectURL } = stubObjectUrls();
        vi.stubGlobal('fetch', fetchMock);

        const preview = useCvPdfPreview({
            endpoint: '/cvs/7/generate/pdf',
            csrfToken: 'csrf-token',
            hasUnsavedChanges: ref(false),
            currentRevision: ref(1),
        });

        expect(fetchMock).not.toHaveBeenCalled();
        expect(preview.status.value).toBe('idle');

        const generation = preview.generate();

        expect(preview.status.value).toBe('generating');
        expect(fetchMock).toHaveBeenCalledOnce();
        expect(fetchMock).toHaveBeenCalledWith(
            '/cvs/7/generate/pdf',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/pdf, application/json',
                    'X-CSRF-TOKEN': 'csrf-token',
                },
                signal: expect.any(AbortSignal),
            }),
        );

        resolveResponse(pdfResponse());
        await generation;

        expect(createObjectURL).toHaveBeenCalledOnce();
        expect(preview.previewUrl.value).toBe('blob:cv-preview');
        expect(preview.previewFilename.value).toBe('cv-backend.pdf');
        expect(preview.revision.value).toBe(1);
        expect(preview.status.value).toBe('ready');
        expect(preview.isStale.value).toBe(false);
        expect(preview.canDownload.value).toBe(true);
        expect(preview.errorMessage.value).toBeUndefined();
        expect(revokeObjectURL).not.toHaveBeenCalled();
    });

    it('does not generate while the editor has unsaved changes', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        const preview = useCvPdfPreview({
            endpoint: '/cvs/7/generate/pdf',
            csrfToken: 'csrf-token',
            hasUnsavedChanges: ref(true),
            currentRevision: ref(1),
        });

        await preview.generate();

        expect(fetchMock).not.toHaveBeenCalled();
        expect(preview.status.value).toBe('idle');
    });

    it('shows the safe server error and permits a later initial retry', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(Response.json({ message: 'No fue posible generar el PDF. Inténtalo nuevamente.' }, { status: 503 }))
            .mockResolvedValueOnce(pdfResponse());
        vi.stubGlobal('fetch', fetchMock);
        stubObjectUrls();

        const preview = useCvPdfPreview({
            endpoint: '/cvs/7/generate/pdf',
            csrfToken: 'csrf-token',
            hasUnsavedChanges: ref(false),
            currentRevision: ref(1),
        });

        await preview.generate();

        expect(preview.status.value).toBe('error');
        expect(preview.errorMessage.value).toBe('No fue posible generar el PDF. Inténtalo nuevamente.');

        await preview.generate();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(preview.status.value).toBe('ready');
        expect(preview.errorMessage.value).toBeUndefined();
    });

    it('tracks changes, regenerates, and downloads without compiling again', async () => {
        const hasUnsavedChanges = ref(false);
        const currentRevision = ref(1);
        const fetchMock = vi.fn().mockResolvedValueOnce(pdfResponse(1)).mockResolvedValueOnce(pdfResponse(2, 'cv-actualizado.pdf'));
        const { revokeObjectURL } = stubObjectUrls();
        const link = { href: '', download: '', hidden: false, click: vi.fn(), remove: vi.fn() };
        const append = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('document', {
            createElement: vi.fn().mockReturnValue(link),
            body: { append },
        });

        const preview = useCvPdfPreview({
            endpoint: '/cvs/7/generate/pdf',
            csrfToken: 'csrf-token',
            hasUnsavedChanges,
            currentRevision,
        });
        await preview.generate();

        hasUnsavedChanges.value = true;

        expect(preview.isStale.value).toBe(true);
        expect(preview.canDownload.value).toBe(false);
        await preview.generate();
        expect(fetchMock).toHaveBeenCalledOnce();

        hasUnsavedChanges.value = false;
        expect(preview.isStale.value).toBe(false);

        currentRevision.value = 2;
        expect(preview.isStale.value).toBe(true);
        expect(fetchMock).toHaveBeenCalledOnce();

        preview.download();
        expect(link.click).not.toHaveBeenCalled();

        await preview.generate();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(revokeObjectURL).toHaveBeenCalledOnce();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:cv-preview');
        expect(preview.previewUrl.value).toBe('blob:regenerated-preview');
        expect(preview.previewFilename.value).toBe('cv-actualizado.pdf');
        expect(preview.revision.value).toBe(2);
        expect(preview.isStale.value).toBe(false);
        expect(preview.canDownload.value).toBe(true);

        preview.download();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(link.href).toBe('blob:regenerated-preview');
        expect(link.download).toBe('cv-actualizado.pdf');
        expect(append).toHaveBeenCalledWith(link);
        expect(link.click).toHaveBeenCalledOnce();
        expect(link.remove).toHaveBeenCalledOnce();
    });

    it('keeps the previous preview when regeneration fails', async () => {
        const currentRevision = ref(1);
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(pdfResponse(1))
            .mockResolvedValueOnce(Response.json({ message: 'No fue posible generar el PDF. Inténtalo nuevamente.' }, { status: 503 }));
        const { revokeObjectURL } = stubObjectUrls();
        vi.stubGlobal('fetch', fetchMock);

        const preview = useCvPdfPreview({
            endpoint: '/cvs/7/generate/pdf',
            csrfToken: 'csrf-token',
            hasUnsavedChanges: ref(false),
            currentRevision,
        });
        await preview.generate();
        currentRevision.value = 2;

        await preview.generate();

        expect(preview.status.value).toBe('error');
        expect(preview.previewUrl.value).toBe('blob:cv-preview');
        expect(preview.revision.value).toBe(1);
        expect(preview.isStale.value).toBe(true);
        expect(preview.canDownload.value).toBe(false);
        expect(revokeObjectURL).not.toHaveBeenCalled();
    });

    it('revokes the temporary URL when the preview is disposed', async () => {
        const { revokeObjectURL } = stubObjectUrls();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(pdfResponse()));

        const preview = useCvPdfPreview({
            endpoint: '/cvs/7/generate/pdf',
            csrfToken: 'csrf-token',
            hasUnsavedChanges: ref(false),
            currentRevision: ref(1),
        });
        await preview.generate();

        preview.dispose();

        expect(revokeObjectURL).toHaveBeenCalledOnce();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:cv-preview');
        expect(preview.previewUrl.value).toBeUndefined();
    });
});
