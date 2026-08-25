import { defineConfig, devices } from '@playwright/test';

const confirmation = process.env.PLAYWRIGHT_REMOTE_SMOKE_CONFIRM;
const configuredBaseUrl = process.env.PLAYWRIGHT_BASE_URL;

if (confirmation !== '1') {
    throw new Error('Set PLAYWRIGHT_REMOTE_SMOKE_CONFIRM=1 to acknowledge that the smoke test mutates the configured deployment.');
}

if (!configuredBaseUrl) {
    throw new Error('Set PLAYWRIGHT_BASE_URL to the absolute URL of the deployment under test.');
}

const baseUrl = new URL(configuredBaseUrl);

if (
    !['http:', 'https:'].includes(baseUrl.protocol) ||
    baseUrl.username ||
    baseUrl.password ||
    baseUrl.search ||
    baseUrl.hash ||
    baseUrl.pathname !== '/'
) {
    throw new Error('PLAYWRIGHT_BASE_URL must be an HTTP(S) origin without credentials, path, query, or fragment.');
}

export default defineConfig({
    testDir: './tests/Browser',
    testMatch: 'render-smoke.e2e.ts',
    fullyParallel: false,
    workers: 1,
    forbidOnly: true,
    retries: 0,
    reporter: 'list',
    timeout: 180_000,
    expect: {
        timeout: 30_000,
    },
    use: {
        baseURL: baseUrl.origin,
        actionTimeout: 30_000,
        navigationTimeout: 60_000,
        screenshot: 'only-on-failure',
        trace: 'off',
        video: 'off',
    },
    projects: [
        {
            name: 'render-smoke-chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
