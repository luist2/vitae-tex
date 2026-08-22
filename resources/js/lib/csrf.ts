export const currentCsrfHeaders = (): Record<string, string> => {
    const xsrfCookie = document.cookie.split(';').find((cookie) => cookie.trim().startsWith('XSRF-TOKEN='));

    if (xsrfCookie) {
        const encodedToken = xsrfCookie.trim().slice('XSRF-TOKEN='.length);

        try {
            return { 'X-XSRF-TOKEN': decodeURIComponent(encodedToken) };
        } catch {
            // Fall back to the token rendered in the current document.
        }
    }

    const metaToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    return metaToken ? { 'X-CSRF-TOKEN': metaToken } : {};
};
