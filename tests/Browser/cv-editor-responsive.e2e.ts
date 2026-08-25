import { expect, type Page, test } from '@playwright/test';

const password = 'VitaeTex-E2E-Password-123!';

const minimalPdf = (pageCount = 2): Buffer => {
    const pageIds = Array.from({ length: pageCount }, (_, index) => 3 + index * 2);
    const objects = [
        '1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n',
        `2 0 obj\n<< /Type /Pages /Kids [${pageIds.map((id) => `${id} 0 R`).join(' ')}] /Count ${pageCount} >>\nendobj\n`,
    ];

    for (const pageId of pageIds) {
        const contentId = pageId + 1;
        objects.push(`${pageId} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents ${contentId} 0 R >>\nendobj\n`);
        objects.push(`${contentId} 0 obj\n<< /Length 0 >>\nstream\n\nendstream\nendobj\n`);
    }
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
    await expect(page.getByText('CV creado correctamente.', { exact: true })).toBeVisible();
};

const loadExampleAndSave = async (page: Page): Promise<void> => {
    await page.getByRole('button', { name: 'Cargar datos de ejemplo' }).click();
    await expect(page.getByLabel('Nombre completo')).toHaveValue('Camila Torres Rojas');

    const saveResponse = page.waitForResponse(
        (response) => response.request().method() === 'PATCH' && /\/cvs\/\d+$/.test(new URL(response.url()).pathname),
    );
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    await saveResponse;

    await expect(page.getByText('CV guardado correctamente.', { exact: true }).last()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Generar CV', exact: true })).toBeEnabled();
};

const expectNoHorizontalOverflow = async (page: Page): Promise<void> => {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(1);
};

test.describe('editor en escritorio', () => {
    test.use({ viewport: { width: 1440, height: 900 } });

    test('muestra cada confirmación aunque dos guardados consecutivos tengan el mismo mensaje', async ({ page }) => {
        await registerAndCreateCv(page, 'CV notificaciones E2E');
        await loadExampleAndSave(page);

        const closeButtons = page.getByRole('button', { name: 'Cerrar notificación' });
        for (const closeButton of await closeButtons.all()) {
            await closeButton.click();
        }
        await expect(closeButtons).toHaveCount(0);

        const saveWithName = async (name: string) => {
            await page.getByLabel('Nombre completo').fill(name);
            const saveResponse = page.waitForResponse(
                (response) => response.request().method() === 'PATCH' && /\/cvs\/\d+$/.test(new URL(response.url()).pathname),
            );
            await page.getByRole('button', { name: 'Guardar cambios' }).click();
            await saveResponse;
        };

        await saveWithName('Camila Torres Segunda');
        await expect(page.locator('[data-sonner-toast]', { hasText: 'CV guardado correctamente.' })).toHaveCount(1);

        await saveWithName('Camila Torres Tercera');
        await expect(page.locator('[data-sonner-toast]', { hasText: 'CV guardado correctamente.' })).toHaveCount(2);
        await expect(page.getByText('CV guardado.', { exact: true })).toBeVisible();
    });

    test('edita y persiste fechas mensuales sin depender del picker nativo', async ({ page }) => {
        await registerAndCreateCv(page, 'CV fechas mensuales E2E');
        await page.getByRole('button', { name: 'Cargar datos de ejemplo' }).click();

        await page.locator('#work-experience-0-start-date').selectOption('08');
        await page.locator('#work-experience-0-start-date-year').fill('2022');
        await page.locator('#certification-0-expires-on').selectOption('');
        await page.locator('#certification-0-expires-on-year').fill('');

        const saveResponse = page.waitForResponse(
            (response) => response.request().method() === 'PATCH' && /\/cvs\/\d+$/.test(new URL(response.url()).pathname),
        );
        await page.getByRole('button', { name: 'Guardar cambios' }).click();
        await saveResponse;
        await page.reload();

        await expect(page.locator('#work-experience-0-start-date')).toHaveValue('08');
        await expect(page.locator('#work-experience-0-start-date-year')).toHaveValue('2022');
        await expect(page.locator('#certification-0-expires-on')).toHaveValue('');
        await expect(page.locator('#certification-0-expires-on-year')).toHaveValue('');
    });

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
        const pdfPreview = page.getByRole('region', { name: 'Documento PDF del CV' });
        await expect(pdfPreview).toBeVisible();
        await expect(pdfPreview.getByRole('img')).toHaveCount(2);
        await expect(pdfPreview.getByRole('img', { name: 'Página 1 de 2' })).toBeVisible();
        expect(await pdfPreview.getByRole('img', { name: 'Página 1 de 2' }).evaluate((canvas: HTMLCanvasElement) => canvas.width)).toBeGreaterThan(0);

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
        await expect(pdfPreview.getByRole('img', { name: 'Página 1 de 2' })).toBeVisible();
        expect(await pdfPreview.getByRole('img', { name: 'Página 1 de 2' }).evaluate((canvas: HTMLCanvasElement) => canvas.width)).toBeGreaterThan(0);

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
        await expect(page.getByRole('region', { name: 'Documento PDF del CV' })).toBeVisible();
        await expect(page.getByRole('img', { name: 'Página 1 de 2' })).toBeVisible();
        await expectNoHorizontalOverflow(page);
    });
});
