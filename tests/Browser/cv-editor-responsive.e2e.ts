import { expect, type Page, test } from '@playwright/test';

const password = 'VitaeTex-E2E-Password-123!';

const minimalPdf = (): Buffer => {
    const objects = [
        '1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n',
        '2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n',
        '3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R >>\nendobj\n',
        '4 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n',
    ];
    let source = '%PDF-1.4\n%\xE2\xE3\xCF\xD3\n';
    const offsets = [0];

    for (const object of objects) {
        offsets.push(Buffer.byteLength(source, 'latin1'));
        source += object;
    }

    const xrefOffset = Buffer.byteLength(source, 'latin1');
    const entries = offsets
        .slice(1)
        .map((offset) => `${offset.toString().padStart(10, '0')} 00000 n \n`)
        .join('');

    source += `xref\n0 ${objects.length + 1}\n0000000000 65535 f \n${entries}`;
    source += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF\n`;

    return Buffer.from(source, 'latin1');
};

const mockPdfGeneration = async (page: Page, revisions: number[]): Promise<void> => {
    let requestIndex = 0;

    await page.route(/\/cvs\/\d+\/generate\/pdf$/, async (route) => {
        const revision = revisions[Math.min(requestIndex, revisions.length - 1)];
        requestIndex += 1;

        await route.fulfill({
            status: 200,
            headers: {
                'Cache-Control': 'private, no-store',
                'Content-Disposition': 'inline; filename="vitaetex-e2e.pdf"',
                'Content-Type': 'application/pdf',
                'X-CV-Revision': String(revision),
            },
            body: minimalPdf(),
        });
    });
};

const registerAndCreateCv = async (page: Page, title: string): Promise<void> => {
    const uniqueEmail = `e2e-${title.toLowerCase().replaceAll(/[^a-z0-9]+/g, '-')}-${Date.now()}@example.test`;

    await page.goto('/register');
    await page.getByLabel('Email').fill(uniqueEmail);
    await page.getByLabel('Contraseña', { exact: true }).fill(password);
    await page.getByLabel('Confirmar contraseña').fill(password);
    await page.getByRole('button', { name: 'Crear cuenta' }).click();

    await expect(page).toHaveURL(/\/cvs$/);
    await page.getByRole('button', { name: 'Crear CV', exact: true }).first().click();

    const dialog = page.getByRole('dialog', { name: 'Crear CV' });
    await dialog.getByLabel('Título').fill(title);
    await dialog.getByRole('button', { name: 'Crear CV', exact: true }).click();

    await expect(page).toHaveURL(/\/cvs\/\d+\/edit$/);
    await expect(page.getByRole('heading', { name: title })).toBeVisible();
};

const loadExampleAndSave = async (page: Page): Promise<void> => {
    await page.getByRole('button', { name: 'Cargar datos de ejemplo' }).click();
    await expect(page.getByLabel('Nombre completo')).toHaveValue('Camila Torres Rojas');

    const saveResponse = page.waitForResponse(
        (response) => response.request().method() === 'PATCH' && /\/cvs\/\d+$/.test(new URL(response.url()).pathname),
    );
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    await saveResponse;

    await expect(page.getByRole('button', { name: 'Generar CV', exact: true })).toBeEnabled();
};

const expectNoHorizontalOverflow = async (page: Page): Promise<void> => {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(1);
};

test.describe('editor en escritorio', () => {
    test.use({ viewport: { width: 1440, height: 900 } });

    test('mantiene el formulario y preview utilizables durante el ciclo completo', async ({ page }) => {
        await mockPdfGeneration(page, [2, 3]);
        await registerAndCreateCv(page, 'CV escritorio E2E');

        const editorPanel = page.locator('#editor-panel');
        const previewPanel = page.locator('#preview-panel');
        const actions = page.getByRole('region', { name: 'Acciones del CV' });

        await expect(editorPanel).toBeVisible();
        await expect(previewPanel).toBeVisible();

        const editorBox = await editorPanel.boundingBox();
        const previewBox = await previewPanel.boundingBox();
        expect(editorBox).not.toBeNull();
        expect(previewBox).not.toBeNull();
        expect(previewBox!.x).toBeGreaterThan(editorBox!.x);

        await loadExampleAndSave(page);
        await page.getByRole('button', { name: 'Generar CV', exact: true }).click();

        await expect(page.getByText('Preview actualizado', { exact: true })).toBeVisible();
        const previewFrame = page.getByTitle('Preview PDF del CV');
        await expect(previewFrame).toBeVisible();
        const firstPreviewUrl = await previewFrame.getAttribute('src');
        expect(firstPreviewUrl).toMatch(/^blob:/);

        const downloadPromise = page.waitForEvent('download');
        await page.getByRole('button', { name: 'Descargar PDF' }).click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toBe('vitaetex-e2e.pdf');

        await page.getByLabel('Nombre completo').fill('Camila Torres Actualizada');
        await expect(page.getByText('Preview desactualizado', { exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Regenerar CV' })).toBeDisabled();

        const saveResponse = page.waitForResponse(
            (response) => response.request().method() === 'PATCH' && /\/cvs\/\d+$/.test(new URL(response.url()).pathname),
        );
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        await saveResponse;
        await expect(page.getByRole('button', { name: 'Regenerar CV' })).toBeEnabled();
        await page.getByRole('button', { name: 'Regenerar CV' }).click();

        await expect(page.getByText('Preview actualizado', { exact: true })).toBeVisible();
        const regeneratedPreviewUrl = await previewFrame.getAttribute('src');
        expect(regeneratedPreviewUrl).toMatch(/^blob:/);
        expect(regeneratedPreviewUrl).not.toBe(firstPreviewUrl);

        await editorPanel.evaluate((element) => element.scrollTo(0, element.scrollHeight));
        await expect(actions).toBeInViewport();
        await expectNoHorizontalOverflow(page);

        await page.getByLabel('Nombre completo').fill('Cambio pendiente');
        const dialogPromise = page.waitForEvent('dialog');
        const navigationAttempt = page.getByRole('link', { name: 'Volver a mis CVs' }).click();
        const leaveDialog = await dialogPromise;
        expect(leaveDialog.message()).toContain('Tienes cambios sin guardar');
        await leaveDialog.dismiss();
        await navigationAttempt;
        await expect(page).toHaveURL(/\/cvs\/\d+\/edit$/);
    });
});

test.describe('editor en móvil', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('alterna paneles sin perder estado y mantiene las acciones accesibles', async ({ page }) => {
        await mockPdfGeneration(page, [2]);
        await registerAndCreateCv(page, 'CV móvil E2E');

        const editorTab = page.getByRole('tab', { name: 'Editor' });
        const previewTab = page.getByRole('tab', { name: 'Preview' });
        const editorPanel = page.locator('#editor-panel');
        const previewPanel = page.locator('#preview-panel');
        const actions = page.getByRole('region', { name: 'Acciones del CV' });

        await expect(editorTab).toHaveAttribute('aria-selected', 'true');
        await expect(editorPanel).toBeVisible();
        await expect(previewPanel).toBeHidden();

        await page.getByRole('button', { name: 'Cargar datos de ejemplo' }).click();
        await page.getByLabel('Nombre completo').fill('Estado conservado en móvil');
        await previewTab.click();

        await expect(previewTab).toHaveAttribute('aria-selected', 'true');
        await expect(editorPanel).toBeHidden();
        await expect(previewPanel).toBeVisible();

        await editorTab.click();
        await expect(page.getByLabel('Nombre completo')).toHaveValue('Estado conservado en móvil');

        await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
        await expect(actions).toBeInViewport();
        await expectNoHorizontalOverflow(page);

        const saveResponse = page.waitForResponse(
            (response) => response.request().method() === 'PATCH' && /\/cvs\/\d+$/.test(new URL(response.url()).pathname),
        );
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        await saveResponse;
        await page.getByRole('button', { name: 'Generar CV', exact: true }).click();

        await expect(previewTab).toHaveAttribute('aria-selected', 'true');
        await expect(page.getByText('Preview actualizado', { exact: true })).toBeVisible();
        await expect(page.getByTitle('Preview PDF del CV')).toBeVisible();
        await expectNoHorizontalOverflow(page);
    });
});
