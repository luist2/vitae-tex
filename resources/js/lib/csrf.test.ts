import { afterEach, describe, expect, it, vi } from 'vitest';

import { currentCsrfHeaders } from './csrf';

describe('currentCsrfHeaders', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('uses and decodes the current XSRF cookie instead of a stale meta token', () => {
        vi.stubGlobal('document', {
            cookie: 'theme=dark; XSRF-TOKEN=encrypted%3Dlogin-token%3D; sidebar=open',
            querySelector: vi.fn().mockReturnValue({ content: 'pre-login-token' }),
        });

        expect(currentCsrfHeaders()).toEqual({
            'X-XSRF-TOKEN': 'encrypted=login-token=',
        });
    });

    it('reads the cookie again for every request', () => {
        let cookie = 'XSRF-TOKEN=token-before-login';

        vi.stubGlobal('document', {
            get cookie() {
                return cookie;
            },
            querySelector: vi.fn().mockReturnValue({ content: 'initial-meta-token' }),
        });

        expect(currentCsrfHeaders()).toEqual({ 'X-XSRF-TOKEN': 'token-before-login' });

        cookie = 'XSRF-TOKEN=token-after-login';

        expect(currentCsrfHeaders()).toEqual({ 'X-XSRF-TOKEN': 'token-after-login' });
    });

    it('falls back to the document meta token when the cookie is unavailable', () => {
        vi.stubGlobal('document', {
            cookie: '',
            querySelector: vi.fn().mockReturnValue({ content: 'document-token' }),
        });

        expect(currentCsrfHeaders()).toEqual({ 'X-CSRF-TOKEN': 'document-token' });
    });
});
