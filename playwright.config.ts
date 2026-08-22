import { defineConfig, devices } from '@playwright/test';

const externalBaseUrl = process.env.PLAYWRIGHT_BASE_URL;
const baseURL = externalBaseUrl ?? 'http://127.0.0.1:8010';
const serverEnvironment = {
    ...process.env,
    APP_ENV: 'e2e',
    APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    APP_DEBUG: 'false',
    APP_URL: baseURL,
    CACHE_STORE: 'array',
    DB_CONNECTION: 'pgsql',
    DB_HOST: process.env.E2E_DB_HOST ?? '127.0.0.1',
    DB_PORT: process.env.E2E_DB_PORT ?? '5432',
    DB_DATABASE: 'vitaetex_test',
    DB_USERNAME: process.env.E2E_DB_USERNAME ?? 'vitaetex',
    DB_PASSWORD: process.env.E2E_DB_PASSWORD ?? 'vitaetex',
    DB_SSLMODE: process.env.E2E_DB_SSLMODE ?? 'disable',
    LOG_CHANNEL: 'stderr',
    LOG_LEVEL: 'error',
    MAIL_MAILER: 'array',
    QUEUE_CONNECTION: 'sync',
    SECURITY_CSP_ENABLED: 'false',
    SECURITY_HSTS_MAX_AGE: '0',
    SESSION_DRIVER: 'database',
    TRUSTED_HOSTS: new URL(baseURL).hostname,
} as Record<string, string>;

export default defineConfig({
    testDir: './tests/Browser',
    testMatch: '**/*.e2e.ts',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['line'], ['html', { open: 'never' }]] : 'list',
    use: {
        ...devices['Desktop Chrome'],
        baseURL,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        video: 'retain-on-failure',
    },
    webServer: externalBaseUrl
        ? undefined
        : {
              command: 'php artisan config:clear && php artisan migrate:fresh --force && php artisan serve --host=127.0.0.1 --port=8010',
              env: serverEnvironment,
              stderr: 'pipe',
              stdout: 'pipe',
              timeout: 120_000,
              url: `${baseURL}/up`,
          },
});
