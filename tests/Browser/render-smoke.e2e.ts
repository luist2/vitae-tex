import { expect, test, type Download, type Page } from '@playwright/test';

const password = 'VitaeTex-Remote-Smoke-Password-123!';

const downloadBytes = async (download: Download): Promise<Buffer> => {
    const stream = await download.createReadStream();

    if (!stream) {
        throw new Error('The browser did not expose the downloaded file stream.');
    }

    const chunks: Buffer[] = [];

    for await (const chunk of stream) {
        chunks.push(Buffer.isBuffer(chunk) ? chunk : Buffer.from(chunk));
    }

    return Buffer.concat(chunks);
};

const isPdfGeneration = (method: string, url: string): boolean => method === 'POST' && /\/cvs\/\d+\/generate\/pdf$/.test(new URL(url).pathname);

const deleteAccount = async (page: Page, email: string): Promise<void> => {
    await page.goto('/settings/profile');

    if (/\/login$/.test(new URL(page.url()).pathname)) {
        await page.getByLabel('Email').fill(email);
        await page.getByLabel('Contraseña', { exact: true }).fill(password);
        await page.getByRole('button', { name: 'Iniciar sesión' }).click();

        try {
            await expect(page).toHaveURL(/\/cvs$/, { timeout: 10_000 });
        } catch {
            return;
        }

        await page.goto('/settings/profile');
    }

    await page.getByRole('button', { name: 'Eliminar cuenta', exact: true }).click();

    const dialog = page.getByRole('dialog', { name: '¿Quieres eliminar tu cuenta?' });
    await dialog.getByPlaceholder('Contraseña').fill(password);

    const deletionResponse = page.waitForResponse(
        (response) => response.request().method() === 'DELETE' && new URL(response.url()).pathname === '/settings/profile',
    );

    await dialog.getByRole('button', { name: 'Eliminar cuenta permanentemente' }).click();
    expect((await deletionResponse).status()).toBeLessThan(400);
    await expect(page).toHaveURL(/\/login$/);
};

test('completa el flujo real del MVP y elimina sus datos ficticios', async ({ page }) => {
    const runId = `${Date.now().toString(36)}-${crypto.randomUUID().slice(0, 8)}`;
    const email = `remote-smoke-${runId}@example.test`;
    const title = `Smoke Render ${runId}`;
    const copyTitle = `${title} (copia)`;
    const originalName = `VitaeTex Smoke ${runId}`;
    const copiedName = `VitaeTex Copia ${runId}`;
    let accountCreated = false;
    let accountDeleted = false;
    let flowError: unknown;
    let pdfGenerationRequests = 0;

    page.on('request', (request) => {
        if (isPdfGeneration(request.method(), request.url())) {
            pdfGenerationRequests++;
        }
    });

    try {
        await page.goto('/register');
        await page.getByLabel('Email').fill(email);
        await page.getByLabel('Contraseña', { exact: true }).fill(password);
        await page.getByLabel('Confirmar contraseña').fill(password);
        await page.getByRole('button', { name: 'Crear cuenta' }).click();

        await expect(page).toHaveURL(/\/cvs$/);
        accountCreated = true;

        await page.getByRole('button', { name: 'Crear CV', exact: true }).first().click();
        const creationDialog = page.getByRole('dialog', { name: 'Crear CV' });
        await creationDialog.getByLabel('Título').fill(title);
        await creationDialog.getByRole('button', { name: 'Crear CV', exact: true }).click();

        await expect(page).toHaveURL(/\/cvs\/\d+\/edit$/);
        await page.getByRole('button', { name: 'Cargar datos de ejemplo' }).click();
        await page.getByLabel('Nombre completo').fill(originalName);

        const saveResponse = page.waitForResponse(
            (response) => response.request().method() === 'PATCH' && /\/cvs\/\d+$/.test(new URL(response.url()).pathname),
        );
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        expect((await saveResponse).status()).toBeLessThan(400);
        await expect(page.getByRole('button', { name: 'Guardar cambios' })).toBeDisabled();

        await page.reload();
        await expect(page.getByLabel('Nombre completo')).toHaveValue(originalName);

        const texLink = page.getByRole('link', { name: 'Descargar .tex' });
        const texHref = await texLink.getAttribute('href');

        if (!texHref) {
            throw new Error('The LaTeX download link has no destination.');
        }

        const texResponse = await page.request.get(new URL(texHref, page.url()).toString());
        expect(texResponse.status()).toBe(200);
        expect(texResponse.headers()['content-type']).toContain('application/x-tex');
        expect(texResponse.headers()['cache-control']).toContain('no-store');

        const texSource = (await texResponse.body()).toString('utf8');
        expect(texSource).toContain('\\documentclass');
        expect(texSource).toContain(originalName);

        const texDownloadPromise = page.waitForEvent('download');
        await texLink.click();

        const texDownload = await texDownloadPromise;
        expect(texDownload.suggestedFilename()).toMatch(/\.tex$/);
        expect(await texDownload.failure()).toBeNull();
        expect((await downloadBytes(texDownload)).toString('utf8')).toBe(texSource);

        const pdfResponsePromise = page.waitForResponse((response) => isPdfGeneration(response.request().method(), response.url()), {
            timeout: 60_000,
        });
        await page.getByRole('button', { name: 'Generar CV', exact: true }).click();

        const pdfResponse = await pdfResponsePromise;
        expect(pdfResponse.status()).toBe(200);
        expect(pdfResponse.headers()['content-type']).toContain('application/pdf');
        expect(pdfResponse.headers()['cache-control']).toContain('no-store');
        expect(Number(pdfResponse.headers()['x-cv-revision'])).toBeGreaterThan(0);

        await expect(page.getByText('Preview actualizado', { exact: true })).toBeVisible();
        await expect(page.getByTitle('Preview PDF del CV')).toBeVisible();

        const pdfDownloadPromise = page.waitForEvent('download');
        await page.getByRole('button', { name: 'Descargar PDF' }).click();
        const pdfDownload = await pdfDownloadPromise;
        expect(pdfDownload.suggestedFilename()).toMatch(/\.pdf$/);
        expect(await pdfDownload.failure()).toBeNull();

        const downloadedPdf = await downloadBytes(pdfDownload);
        expect(downloadedPdf.subarray(0, 5).toString('ascii')).toBe('%PDF-');
        expect(downloadedPdf.length).toBeGreaterThan(1_024);
        expect(pdfGenerationRequests).toBe(1);

        await page.getByRole('link', { name: 'Volver a mis CVs' }).click();
        await expect(page).toHaveURL(/\/cvs$/);
        await page.getByRole('button', { name: 'Duplicar', exact: true }).click();
        await expect(page.getByRole('heading', { name: copyTitle })).toBeVisible();
        await expect(page.getByLabel('Nombre completo')).toHaveValue(originalName);

        await page.getByLabel('Nombre completo').fill(copiedName);
        const copySaveResponse = page.waitForResponse(
            (response) => response.request().method() === 'PATCH' && /\/cvs\/\d+$/.test(new URL(response.url()).pathname),
        );
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        expect((await copySaveResponse).status()).toBeLessThan(400);
        await expect(page.getByRole('button', { name: 'Guardar cambios' })).toBeDisabled();

        await page.getByRole('link', { name: 'Volver a mis CVs' }).click();
        await expect(page).toHaveURL(/\/cvs$/);
        await expect(page.getByRole('heading', { name: copyTitle, exact: true })).toBeVisible();
        await page.getByRole('button', { name: `Eliminar ${copyTitle}` }).click();
        const copyDeletionDialog = page.getByRole('dialog', { name: 'Eliminar CV permanentemente' });
        await copyDeletionDialog.getByRole('button', { name: 'Eliminar permanentemente' }).click();
        await expect(page.getByText(copyTitle, { exact: true })).toHaveCount(0);

        await page.getByRole('link', { name: 'Abrir' }).click();
        await expect(page.getByLabel('Nombre completo')).toHaveValue(originalName);

        await page.getByRole('link', { name: 'Volver a mis CVs' }).click();
        await expect(page).toHaveURL(/\/cvs$/);
        await page.getByRole('button', { name: `Eliminar ${title}` }).click();
        const originalDeletionDialog = page.getByRole('dialog', { name: 'Eliminar CV permanentemente' });
        await originalDeletionDialog.getByRole('button', { name: 'Eliminar permanentemente' }).click();
        await expect(page.getByText('Crea tu primer CV')).toBeVisible();

        await deleteAccount(page, email);
        accountDeleted = true;
    } catch (error) {
        flowError = error;
    }

    let cleanupError: unknown;

    if (accountCreated && !accountDeleted) {
        try {
            await deleteAccount(page, email);
        } catch (error) {
            cleanupError = error;
        }
    }

    if (flowError && cleanupError) {
        throw new AggregateError([flowError, cleanupError], 'The remote smoke flow failed and its disposable account could not be removed.');
    }

    if (flowError) {
        throw flowError;
    }

    if (cleanupError) {
        throw cleanupError;
    }
});
