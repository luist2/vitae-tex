import type { Ref } from 'vue';
import { computed, readonly, ref } from 'vue';

export type CvPdfPreviewStatus = 'idle' | 'generating' | 'ready' | 'error';

interface CvPdfPreviewOptions {
    endpoint: string;
    csrfHeaders: () => Record<string, string>;
    hasUnsavedChanges: Readonly<Ref<boolean>>;
    currentRevision: Readonly<Ref<number>>;
}

const fallbackErrorMessage = 'No fue posible generar el PDF. Inténtalo nuevamente.';
const fallbackRetryAfterSeconds = 60;
const maximumRetryAfterSeconds = 60 * 60;

class CvPdfPreviewError extends Error {}

class CvPdfPreviewRateLimitError extends CvPdfPreviewError {
    constructor(readonly retryAfterSeconds: number) {
        super();
    }
}

export const useCvPdfPreview = ({ endpoint, csrfHeaders, hasUnsavedChanges, currentRevision }: CvPdfPreviewOptions) => {
    const status = ref<CvPdfPreviewStatus>('idle');
    const previewBlob = ref<Blob>();
    const previewUrl = ref<string>();
    const previewFilename = ref('cv.pdf');
    const revision = ref<number>();
    const errorMessage = ref<string>();
    const retryAfterSeconds = ref(0);
    let activeRequest: AbortController | undefined;
    let cooldownTimer: ReturnType<typeof setInterval> | undefined;
    let disposed = false;

    const isStale = computed(() => previewUrl.value !== undefined && (hasUnsavedChanges.value || revision.value !== currentRevision.value));
    const canDownload = computed(() => previewUrl.value !== undefined && status.value !== 'generating' && !isStale.value);

    const rateLimitMessage = (seconds: number): string =>
        `Has generado varios PDFs seguidos. Podrás intentarlo nuevamente en ${seconds} ${seconds === 1 ? 'segundo' : 'segundos'}.`;

    const stopCooldown = (): void => {
        if (cooldownTimer !== undefined) {
            clearInterval(cooldownTimer);
            cooldownTimer = undefined;
        }

        retryAfterSeconds.value = 0;
    };

    const startCooldown = (seconds: number): void => {
        stopCooldown();
        const expiresAt = Date.now() + seconds * 1000;

        const updateCooldown = (): void => {
            const remaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
            retryAfterSeconds.value = remaining;

            if (remaining > 0) {
                errorMessage.value = rateLimitMessage(remaining);

                return;
            }

            stopCooldown();
            errorMessage.value = undefined;
            status.value = previewUrl.value ? 'ready' : 'idle';
        };

        updateCooldown();
        cooldownTimer = setInterval(updateCooldown, 250);
    };

    const retryAfterFrom = (response: Response): number => {
        const header = response.headers.get('Retry-After')?.trim();

        if (header && /^\d+$/.test(header)) {
            return Math.min(Math.max(Number(header), 1), maximumRetryAfterSeconds);
        }

        const retryAt = header ? Date.parse(header) : Number.NaN;

        if (Number.isFinite(retryAt)) {
            return Math.min(Math.max(Math.ceil((retryAt - Date.now()) / 1000), 1), maximumRetryAfterSeconds);
        }

        return fallbackRetryAfterSeconds;
    };

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
        if (
            disposed ||
            hasUnsavedChanges.value ||
            retryAfterSeconds.value > 0 ||
            status.value === 'generating' ||
            (previewUrl.value && !isStale.value)
        ) {
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
                    ...csrfHeaders(),
                },
                signal: request.signal,
            });

            if (!response.ok) {
                if (response.status === 429) {
                    throw new CvPdfPreviewRateLimitError(retryAfterFrom(response));
                }

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
            previewBlob.value = pdf;
            previewFilename.value = filenameFrom(response);
            revision.value = responseRevision;
            stopCooldown();
            status.value = 'ready';
        } catch (error) {
            if (request.signal.aborted) {
                return;
            }

            status.value = 'error';

            if (error instanceof CvPdfPreviewRateLimitError) {
                startCooldown(error.retryAfterSeconds);
            } else {
                errorMessage.value = error instanceof CvPdfPreviewError ? error.message : fallbackErrorMessage;
            }
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
        stopCooldown();

        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
            previewUrl.value = undefined;
        }

        previewBlob.value = undefined;
    };

    return {
        status: readonly(status),
        previewBlob: readonly(previewBlob),
        previewUrl: readonly(previewUrl),
        previewFilename: readonly(previewFilename),
        revision: readonly(revision),
        errorMessage: readonly(errorMessage),
        retryAfterSeconds: readonly(retryAfterSeconds),
        isStale,
        canDownload,
        generate,
        download,
        dispose,
    };
};
