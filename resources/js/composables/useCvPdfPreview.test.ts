import { afterEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

import { useCvPdfPreview } from './useCvPdfPreview';

const pdfResponse = () =>
    new Response(new Blob(['%PDF-1.7 preview'], { type: 'application/pdf' }), {
        headers: {
            'Content-Type': 'application/pdf',
            'X-CV-Revision': '2026-08-20T12:34:56.000Z',
        },
    });

describe('useCvPdfPreview', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('generates a PDF only after the explicit action and exposes its temporary URL', async () => {
        let resolveResponse: (response: Response) => void = () => undefined;
        const pendingResponse = new Promise<Response>((resolve) => {
            resolveResponse = resolve;
        });
        const fetchMock = vi.fn().mockReturnValue(pendingResponse);
        const createObjectURL = vi.fn().mockReturnValue('blob:cv-preview');
        const revokeObjectURL = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('URL', { createObjectURL, revokeObjectURL });

        const preview = useCvPdfPreview({
            endpoint: '/cvs/7/generate/pdf',
            csrfToken: 'csrf-token',
            hasUnsavedChanges: ref(false),
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
        expect(preview.revision.value).toBe('2026-08-20T12:34:56.000Z');
        expect(preview.status.value).toBe('ready');
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
        });

        await preview.generate();

        expect(fetchMock).not.toHaveBeenCalled();
        expect(preview.status.value).toBe('idle');
    });

    it('shows the safe server error and permits a later retry', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(Response.json({ message: 'No fue posible generar el PDF. Inténtalo nuevamente.' }, { status: 503 }))
            .mockResolvedValueOnce(pdfResponse());
        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal('URL', {
            createObjectURL: vi.fn().mockReturnValue('blob:retry-preview'),
            revokeObjectURL: vi.fn(),
        });

        const preview = useCvPdfPreview({
            endpoint: '/cvs/7/generate/pdf',
            csrfToken: 'csrf-token',
            hasUnsavedChanges: ref(false),
        });

        await preview.generate();

        expect(preview.status.value).toBe('error');
        expect(preview.errorMessage.value).toBe('No fue posible generar el PDF. Inténtalo nuevamente.');

        await preview.generate();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(preview.status.value).toBe('ready');
        expect(preview.errorMessage.value).toBeUndefined();
    });

    it('revokes the temporary URL when the preview is disposed', async () => {
        const revokeObjectURL = vi.fn();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(pdfResponse()));
        vi.stubGlobal('URL', {
            createObjectURL: vi.fn().mockReturnValue('blob:cv-preview'),
            revokeObjectURL,
        });

        const preview = useCvPdfPreview({
            endpoint: '/cvs/7/generate/pdf',
            csrfToken: 'csrf-token',
            hasUnsavedChanges: ref(false),
        });
        await preview.generate();

        preview.dispose();

        expect(revokeObjectURL).toHaveBeenCalledOnce();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:cv-preview');
        expect(preview.previewUrl.value).toBeUndefined();
    });
});
