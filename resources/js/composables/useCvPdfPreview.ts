import type { Ref } from 'vue';
import { computed, readonly, ref } from 'vue';

export type CvPdfPreviewStatus = 'idle' | 'generating' | 'ready' | 'error';

interface CvPdfPreviewOptions {
    endpoint: string;
    csrfToken: string;
    hasUnsavedChanges: Readonly<Ref<boolean>>;
    currentRevision: Readonly<Ref<number>>;
}

const fallbackErrorMessage = 'No fue posible generar el PDF. Inténtalo nuevamente.';

class CvPdfPreviewError extends Error {}

export const useCvPdfPreview = ({ endpoint, csrfToken, hasUnsavedChanges, currentRevision }: CvPdfPreviewOptions) => {
    const status = ref<CvPdfPreviewStatus>('idle');
    const previewUrl = ref<string>();
    const previewFilename = ref('cv.pdf');
    const revision = ref<number>();
    const errorMessage = ref<string>();
    let activeRequest: AbortController | undefined;
    let disposed = false;

    const isStale = computed(() => previewUrl.value !== undefined && (hasUnsavedChanges.value || revision.value !== currentRevision.value));
    const canDownload = computed(() => previewUrl.value !== undefined && status.value !== 'generating' && !isStale.value);

    const responseError = async (response: Response): Promise<string> => {
        if (!response.headers.get('Content-Type')?.includes('application/json')) {
            return fallbackErrorMessage;
        }

        try {
            const body: unknown = await response.json();

            if (typeof body === 'object' && body !== null && 'message' in body && typeof body.message === 'string' && body.message.trim() !== '') {
                return body.message;
            }
        } catch {
            return fallbackErrorMessage;
        }

        return fallbackErrorMessage;
    };

    const generate = async (): Promise<void> => {
        if (disposed || hasUnsavedChanges.value || status.value === 'generating' || (previewUrl.value && !isStale.value)) {
            return;
        }

        const request = new AbortController();
        activeRequest = request;
        errorMessage.value = undefined;
        status.value = 'generating';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/pdf, application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                signal: request.signal,
            });

            if (!response.ok) {
                throw new CvPdfPreviewError(await responseError(response));
            }

            if (!response.headers.get('Content-Type')?.includes('application/pdf')) {
                throw new CvPdfPreviewError(fallbackErrorMessage);
            }

            const responseRevision = Number(response.headers.get('X-CV-Revision'));

            if (!Number.isSafeInteger(responseRevision) || responseRevision < 1) {
                throw new CvPdfPreviewError(fallbackErrorMessage);
            }

            const pdf = await response.blob();

            if (pdf.size === 0) {
                throw new CvPdfPreviewError(fallbackErrorMessage);
            }

            const nextUrl = URL.createObjectURL(pdf);

            if (disposed || request.signal.aborted) {
                URL.revokeObjectURL(nextUrl);

                return;
            }

            if (previewUrl.value) {
                URL.revokeObjectURL(previewUrl.value);
            }

            previewUrl.value = nextUrl;
            previewFilename.value = filenameFrom(response);
            revision.value = responseRevision;
            status.value = 'ready';
        } catch (error) {
            if (request.signal.aborted) {
                return;
            }

            errorMessage.value = error instanceof CvPdfPreviewError ? error.message : fallbackErrorMessage;
            status.value = 'error';
        } finally {
            if (activeRequest === request) {
                activeRequest = undefined;
            }
        }
    };

    const filenameFrom = (response: Response): string => {
        const disposition = response.headers.get('Content-Disposition');
        const filename = disposition?.match(/filename="([^"\\/\r\n]+\.pdf)"/i)?.[1];

        return filename ?? 'cv.pdf';
    };

    const download = (): void => {
        if (!canDownload.value || !previewUrl.value) {
            return;
        }

        const link = document.createElement('a');
        link.href = previewUrl.value;
        link.download = previewFilename.value;
        link.hidden = true;
        document.body.append(link);
        link.click();
        link.remove();
    };

    const dispose = (): void => {
        disposed = true;
        activeRequest?.abort();
        activeRequest = undefined;

        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
            previewUrl.value = undefined;
        }
    };

    return {
        status: readonly(status),
        previewUrl: readonly(previewUrl),
        previewFilename: readonly(previewFilename),
        revision: readonly(revision),
        errorMessage: readonly(errorMessage),
        isStale,
        canDownload,
        generate,
        download,
        dispose,
    };
};
